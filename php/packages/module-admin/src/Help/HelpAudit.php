<?php

declare(strict_types=1);

namespace WebxUi\Admin\Help;

use Illuminate\Contracts\Translation\Translator;
use WebxUi\Admin\Screens\ScreenRegistry;

/**
 * What is wrong with the help of the screens loaded right now (WEBX_UI_PANEL_HELP.md §6.2).
 *
 * One walk for two readers. A package's `HelpCoverageTest` is `strict`: three languages, and a
 * field belongs to one package. `webx:help --check` on a real site is not, because a project may
 * write one language and may describe a field of a package over it (§2.4).
 *
 * A screen is judged with everything allowed, so a field behind a permission counts. What must be
 * described is what this site has; what may be described is what any site has, so a text stays
 * when a setting takes its field away, and a text of no field at all is still red (§2.3).
 */
final readonly class HelpAudit
{
    /** The languages texts are written in; the others show English, and that is no fault (§2.5). */
    public const array LANGUAGES = ['en', 'ru', 'uk'];

    public function __construct(
        private HelpRegistry $help,
        private HelpBuilder $builder,
        private ScreenRegistry $screens,
        private Translator $translator,
    ) {}

    /**
     * @param  array<string, string>  $pending  Why a key, or one field as `<key>#<id>`, has no text yet.
     * @param  string|null  $guides  The folder of the guides, where it exists: only the monorepo has it.
     * @return list<string> One line per problem; empty is healthy.
     */
    public function problems(array $pending = [], ?string $guides = null, bool $strict = false): array
    {
        $files = $this->files($problems);

        foreach ($files as $languages) {
            foreach ($languages as $parts) {
                foreach ($parts as $file) {
                    array_push($problems, ...$this->markup($file));
                }
            }

            if ($strict) {
                array_push($problems, ...$this->languages($languages));
            }

            array_push($problems, ...$this->guides($languages[HelpRegistry::FALLBACK] ?? [], $guides));
        }

        if ($strict) {
            array_push($problems, ...$this->describedTwice($files));
        }

        // A broken file says so above. Reading on would only throw the same thing.
        if ($problems !== [] && $this->broken($files)) {
            return $problems;
        }

        foreach ($this->screens->names() as $name) {
            if (! array_key_exists($name, $pending)) {
                array_push($problems, ...$this->screen($name, $pending));
            }
        }

        array_push($problems, ...$this->pages($files, $pending, $strict));
        array_push($problems, ...$this->stale($pending));

        if (! $strict) {
            array_push($problems, ...$this->sharedWithoutANode());
        }

        return $problems;
    }

    /**
     * Every file of the three languages, parsed, by directory, language and name. What cannot be
     * parsed is a problem and an empty place, so the rest of the walk still sees the others.
     *
     * @param  list<string>|null  $problems
     *
     * @param-out list<string>  $problems
     *
     * @return array<string, array<string, array<string, HelpFile|null>>>
     */
    private function files(?array &$problems): array
    {
        $problems = [];
        $files = [];

        foreach ($this->help->directories() as $directory) {
            foreach (self::LANGUAGES as $language) {
                $folder = $directory.'/'.$language;

                foreach (is_dir($folder) ? (glob($folder.'/*.md') ?: []) : [] as $path) {
                    $name = basename($path, '.md');

                    if ($name !== HelpRegistry::SHARED && preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/D', $name) !== 1) {
                        $problems[] = "{$path}: the name of the file is not a key, nothing will ever open it";

                        continue;
                    }

                    try {
                        $files[$directory][$language][$name] = HelpFile::read($path, $language);
                    } catch (HelpException $exception) {
                        $files[$directory][$language][$name] = null;
                        $problems[] = $exception->getMessage();
                    }
                }
            }
        }

        return $files;
    }

    /**
     * @param  array<string, array<string, array<string, HelpFile|null>>>  $files
     */
    private function broken(array $files): bool
    {
        foreach ($files as $languages) {
            foreach ($languages as $parts) {
                if (in_array(null, $parts, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * What `renderMarkdown` does not draw, or what would cut the file in the wrong place. Code is
     * text: a `<` or a `|` there is an example.
     *
     * @return list<string>
     */
    private function markup(?HelpFile $file): array
    {
        if ($file === null) {
            return [];
        }

        $texts = [$file->about, ...array_column($file->howto, 'body'), ...array_values($file->fields)];
        $text = implode("\n\n", $texts);
        $text = (string) preg_replace('/^```.*?^```$/ms', '', $text);
        $text = (string) preg_replace('/`[^`\n]*`/', '', $text);

        $found = array_filter([
            'a table' => preg_match('/^\s*\|.*\|\s*$/m', $text),
            'a picture' => preg_match('/!\[[^\]]*\]\(/', $text),
            'HTML' => preg_match('/<\/?[a-zA-Z!][^>]*>/', $text),
            'a heading' => preg_match('/^#{1,6}\s/m', $text),
        ], static fn (int|false $match): bool => $match === 1);

        return array_map(
            static fn (string $what): string => "{$file->path}: {$what} in the text, the panel will not draw it",
            array_keys($found),
        );
    }

    /**
     * `en` is the reference of what exists, and the other two follow it (§2.5). On a page with no
     * tree a heading is the words of a label, so only their number can be compared.
     *
     * @param  array<string, array<string, HelpFile|null>>  $languages
     * @return list<string>
     */
    private function languages(array $languages): array
    {
        $problems = [];
        $english = $languages[HelpRegistry::FALLBACK] ?? [];

        foreach (self::LANGUAGES as $language) {
            if ($language === HelpRegistry::FALLBACK) {
                continue;
            }

            foreach (array_diff_key($languages[$language] ?? [], $english) as $name => $file) {
                $problems[] = ($file === null ? $name : $file->path).': there is no such file in en, and en decides what exists';
            }

            foreach ($english as $name => $reference) {
                if (! array_key_exists($name, $languages[$language] ?? [])) {
                    $path = $reference === null ? $name : $reference->path;
                    $problems[] = "{$path}: no file in {$language}";

                    continue;
                }

                $file = $languages[$language][$name];

                if ($reference !== null && $file !== null) {
                    array_push($problems, ...$this->sameContents((string) $name, $reference, $file));
                }
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private function sameContents(string $name, HelpFile $reference, HelpFile $file): array
    {
        $problems = [];

        if ($file->guide !== $reference->guide) {
            $problems[] = "{$file->path}: guide differs from en";
        }

        if ($file->include !== $reference->include) {
            $problems[] = "{$file->path}: include differs from en";
        }

        $ours = array_keys($file->fields);
        $theirs = array_keys($reference->fields);

        if ($name !== HelpRegistry::SHARED && ! $this->screens->has($name)) {
            if (count($ours) !== count($theirs)) {
                $problems[] = "{$file->path}: ".count($ours).' fields where en has '.count($theirs);
            }

            return $problems;
        }

        foreach (array_diff($theirs, $ours) as $id) {
            $problems[] = "{$file->path}: [{$id}] is described in en and not here";
        }

        foreach (array_diff($ours, $theirs) as $id) {
            $problems[] = "{$file->path}: [{$id}] is described here and not in en";
        }

        return $problems;
    }

    /**
     * A file of `guide` that is not in the guides is a dead link on a site that turns them on.
     *
     * @param  array<string, HelpFile|null>  $english
     * @return list<string>
     */
    private function guides(array $english, ?string $guides): array
    {
        $problems = [];

        foreach ($guides === null ? [] : $english as $file) {
            $guide = $file?->guide;

            if ($file !== null && $guide !== null && ! is_file($guides.'/'.explode('#', $guide)[0].'.md')) {
                $problems[] = "{$file->path}: there is no guide [{$guide}]";
            }
        }

        return $problems;
    }

    /**
     * A package describes its own fields: the same id in two packages means one of them speaks for
     * a field it does not own (§2.4).
     *
     * @param  array<string, array<string, array<string, HelpFile|null>>>  $files
     * @return list<string>
     */
    private function describedTwice(array $files): array
    {
        $seen = [];
        $problems = [];

        foreach ($files as $languages) {
            foreach ($languages[HelpRegistry::FALLBACK] ?? [] as $name => $file) {
                foreach (array_keys($file === null ? [] : $file->fields) as $id) {
                    if (isset($seen[$name][$id])) {
                        $problems[] = "{$name}: [{$id}] is described in {$seen[$name][$id]} and in {$file?->path}";
                    }

                    $seen[$name][$id] = $file?->path;
                }
            }
        }

        return $problems;
    }

    /**
     * @param  array<string, string>  $pending
     * @return list<string>
     */
    private function screen(string $name, array $pending): array
    {
        $page = $this->help->page($name, HelpRegistry::FALLBACK);

        if ($page === null) {
            return ["{$name}: the screen has no help, it needs a title and # about in en"];
        }

        $problems = [];

        foreach ($this->fields($page) as $field) {
            if ($field['body'] === null && ! array_key_exists("{$name}#{$field['id']}", $pending)) {
                $problems[] = "{$name}: the field [{$field['id']}] is not described";
            }
        }

        foreach (array_diff(array_map('strval', array_keys($page->fields)), $this->builder->idsOnAnySite($page)) as $id) {
            $problems[] = "{$name}: [{$id}] is described, and the screen has no such field";
        }

        foreach (self::LANGUAGES as $language) {
            $translated = $this->help->page($name, $language);

            foreach ($translated === null ? [] : $this->fields($translated, $language) as $field) {
                if (self::repeats($field['body'], $field['hint'])) {
                    $problems[] = "{$name}: the description of [{$field['id']}] in {$language} repeats its hint, it has to go on from it";
                }
            }
        }

        return $problems;
    }

    /**
     * The fields of the screen itself: an included screen answers for its own under its own name.
     *
     * @return list<array{id: string, label: string|null, hint: string|null, required: bool, body: string|null}>
     */
    private function fields(HelpPage $page, string $language = HelpRegistry::FALLBACK): array
    {
        $groups = $this->builder->groupsOf(
            $page,
            static fn (string $permission): bool => true,
            function (string $words) use ($language): string {
                $translated = $this->translator->get($words, [], $language);

                return is_string($translated) ? $translated : $words;
            },
        );

        return array_merge(...array_column($groups, 'fields') ?: [[]]);
    }

    private static function repeats(?string $body, ?string $hint): bool
    {
        if ($body === null || $hint === null) {
            return false;
        }

        return str_contains(self::plain($body), self::plain($hint));
    }

    private static function plain(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($text)));
    }

    /**
     * Keys with no tree, and what only a tree cannot show: a part nobody owns and an include that
     * leads nowhere. An include may lead to a package this test has not loaded, and that is the
     * neighbour's business: `HelpPackagesCoverageTest` reads them all.
     *
     * @param  array<string, array<string, array<string, HelpFile|null>>>  $files
     * @param  array<string, string>  $pending
     * @return list<string>
     */
    private function pages(array $files, array $pending, bool $strict): array
    {
        $problems = [];
        $keys = $this->help->keys();

        foreach ($files as $languages) {
            foreach ($languages[HelpRegistry::FALLBACK] ?? [] as $name => $file) {
                $name = (string) $name;

                if ($file === null || $name === HelpRegistry::SHARED) {
                    continue;
                }

                if (! in_array($name, $keys, true) && ! array_key_exists($name, $pending)) {
                    $problems[] = "{$file->path}: nobody owns this key, no file of it has a title and # about";
                }

                foreach ($strict ? [] : $file->include as $include) {
                    if (! in_array($include, $keys, true)) {
                        $problems[] = "{$file->path}: include [{$include}] leads nowhere";
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * What is in the list and has its text by now: forgotten lines must not pile up (§6.5).
     *
     * @param  array<string, string>  $pending
     * @return list<string>
     */
    private function stale(array $pending): array
    {
        $problems = [];

        foreach (array_keys($pending) as $entry) {
            [$key, $id] = [...explode('#', (string) $entry, 2), null];
            $page = $this->help->page($key, HelpRegistry::FALLBACK);

            if ($page !== null && ($id === null || $page->field($id) !== null)) {
                $problems[] = "{$entry}: still in the pending list, and the text is there";
            }
        }

        return $problems;
    }

    /**
     * Whether a shared description fits anything can only be told with every neighbour loaded: a
     * package's own test has none of the forms it patches.
     *
     * @return list<string>
     */
    private function sharedWithoutANode(): array
    {
        $ids = [];

        foreach ($this->screens->names() as $name) {
            $page = $this->help->page($name, HelpRegistry::FALLBACK);

            foreach ($page === null ? [] : $this->builder->idsOnAnySite($page) as $id) {
                $ids[$id] = true;
            }
        }

        $problems = [];

        foreach (array_keys($this->help->shared(HelpRegistry::FALLBACK)) as $id) {
            if (! isset($ids[$id])) {
                $problems[] = "_fields: [{$id}] is described, and no screen has such a field";
            }
        }

        return $problems;
    }
}
