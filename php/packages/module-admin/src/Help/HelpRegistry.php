<?php

declare(strict_types=1);

namespace WebxUi\Admin\Help;

/**
 * Where the help of the panel lies, and the parts of one key put together.
 *
 * A package registers its `resources/help` from its provider and nothing else: the name of a
 * file is its key, so there is nothing to wire. Only the directories are remembered at boot;
 * a file is read when somebody asks, because a session opens help on a screen or two out of
 * a hundred and thirty (WEBX_UI_PANEL_HELP.md §3.1).
 *
 * Order is registration order, as with the patches of a screen: the provider of the project
 * boots last, so a field it describes is taken from it.
 */
final class HelpRegistry
{
    /** The language every text is written in; what any other language falls back on. */
    public const string FALLBACK = 'en';

    /** The file whose descriptions fit a node with that id on any screen. */
    public const string SHARED = '_fields';

    /** @var list<string> */
    private array $directories = [];

    /** @var array<string, HelpFile> Files already taken apart, by path. */
    private array $files = [];

    public function directory(string $path): void
    {
        $path = rtrim($path, '/');

        if (! in_array($path, $this->directories, true)) {
            $this->directories[] = $path;
        }
    }

    /**
     * @return list<string>
     */
    public function directories(): array
    {
        return $this->directories;
    }

    /**
     * Every key a page can be put together for, sorted. The English files decide: `en` is the
     * reference of what exists (§2.5), and the list must not depend on the reader's language.
     *
     * A key only satellites speak of has no page, so it is left out: a button for it would open
     * an error. So is a file that cannot be parsed, because this list goes into the manifest, and
     * a typo on one screen must not take the panel down. Opening that key still says what is wrong.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = [];

        foreach ($this->directories as $directory) {
            $english = $directory.'/'.self::FALLBACK;

            // A project registers its folder before it has written anything into it.
            foreach (is_dir($english) ? (scandir($english) ?: []) : [] as $name) {
                $key = substr($name, 0, -3);

                if (! str_ends_with($name, '.md') || ! self::validKey($key)) {
                    continue;
                }

                try {
                    if ($this->file($english.'/'.$name, self::FALLBACK)->isOwner()) {
                        $keys[$key] = true;
                    }
                } catch (HelpException) {
                    continue;
                }
            }
        }

        $keys = array_map('strval', array_keys($keys));
        sort($keys);

        return $keys;
    }

    /**
     * The page of a key in a language, or null when nobody owns that key.
     *
     * The title, the about, the guide and the includes are the owner's alone. The owner's
     * scenarios go first whichever provider booted first; the rest follow in the order of
     * registration, and a field described twice is taken from the last part.
     */
    public function page(string $key, string $locale): ?HelpPage
    {
        $parts = $this->parts($key, $locale);
        $owner = null;

        foreach ($parts as $part) {
            if ($part->isOwner()) {
                $owner = $part;
                break;
            }
        }

        if ($owner === null) {
            return null;
        }

        $others = array_filter($parts, static fn (HelpFile $part): bool => $part !== $owner);

        $howto = [];
        $fields = [];

        foreach ([$owner, ...$others] as $part) {
            $howto = [...$howto, ...$part->howto];
            $fields = array_replace($fields, $part->fields);
        }

        return new HelpPage(
            key: $key,
            locale: $owner->locale,
            title: (string) $owner->title,
            about: $owner->about,
            guide: $owner->guide,
            include: $owner->include,
            howto: $howto,
            fields: $fields,
            shared: $this->shared($locale),
        );
    }

    /**
     * The files of a key in a language, one per directory that has it, in the order of
     * registration.
     *
     * Each part goes down the chain on its own and is taken whole, never paragraph by paragraph:
     * a page in two languages reads worse than one in English. The chain is the language of the
     * panel, then `en`. The site's own fallback language is not in it: help is the panel's
     * words, not the site's content (§2.5).
     *
     * @return list<HelpFile>
     */
    public function parts(string $key, string $locale): array
    {
        if (! self::validKey($key)) {
            return [];
        }

        return $this->resolve($key, $locale);
    }

    /**
     * What `_fields.md` of every directory describes, later directories over earlier ones.
     *
     * @return array<string, string>
     */
    public function shared(string $locale): array
    {
        $fields = [];

        foreach ($this->resolve(self::SHARED, $locale) as $part) {
            if ($part->title !== null || $part->about !== '' || $part->howto !== []) {
                throw HelpException::invalid($part->path, 'a file of shared fields holds only # fields');
            }

            $fields = array_replace($fields, $part->fields);
        }

        return $fields;
    }

    /**
     * @return list<HelpFile>
     */
    private function resolve(string $name, string $locale): array
    {
        $parts = [];

        foreach ($this->directories as $directory) {
            foreach (self::chain($locale) as $language) {
                $path = "{$directory}/{$language}/{$name}.md";

                if (is_file($path)) {
                    $parts[] = $this->file($path, $language);
                    break;
                }
            }
        }

        return $parts;
    }

    /**
     * The key and the language come from a request and end up in a path, so only their own
     * shape gets that far. A language that is not one is simply nobody's: English answers.
     *
     * @return list<string>
     */
    private static function chain(string $locale): array
    {
        if ($locale === self::FALLBACK || preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]+)*$/D', $locale) !== 1) {
            return [self::FALLBACK];
        }

        return [$locale, self::FALLBACK];
    }

    private static function validKey(string $key): bool
    {
        // Two parts or more: a page with no tree takes the name of its route, and some of those
        // have three (`catalog.exchange.profiles`).
        return preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/D', $key) === 1;
    }

    private function file(string $path, string $locale): HelpFile
    {
        return $this->files[$path] ??= HelpFile::read($path, $locale);
    }
}
