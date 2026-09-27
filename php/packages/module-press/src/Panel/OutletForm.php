<?php

declare(strict_types=1);

namespace WebxUi\Press\Panel;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;
use WebxUi\Press\PressServiceProvider;
use WebxUi\Press\Rendering\Cards;
use WebxUi\Press\Support\Kinds;
use WebxUi\Seo\Fields;

/**
 * The editor's screen on the server side: what its fields hold, and what a save writes (§4.6).
 *
 * The screen is `press.outlet-form`, keyed by field name, so what an outlet is made of is decided
 * by the description: a project's field arrives as a patch and is saved here by being on the
 * screen at all. What this class knows is which names are the outlet's own; everything else is
 * `extra`.
 *
 * The articles are a repeater on the form and a table in the database (decision 16). The rows are
 * matched by `id`: a row with the id of one of this outlet's articles updates it, a row without an
 * id is a new article, and an article no row names is deleted. The order of the rows is the order
 * of the articles. An id of somebody else's article is refused rather than quietly made a new one:
 * it means the form is not the form of this outlet.
 *
 * What a repeater cannot say of a row — which row, which field, which language — is checked here
 * before the screen is, so that a refusal lands under `articles.<n>.<field>` and a translated
 * field under `articles.<n>.title.<language>`, where the form looks for it.
 *
 * No draft (decision 9): a save is what the site shows, at once. In one transaction: a refusal half
 * way leaves no article behind, and a new outlet not at all.
 */
final class OutletForm
{
    /**
     * The outlet's own fields.
     *
     * @var list<string>
     */
    private const OWN = ['title', 'slug', 'summary', 'logo', 'website_url', 'featured', 'published'];

    /**
     * The screen's fields stored beside the outlet rather than in it.
     *
     * @var list<string>
     */
    private const TAKEN = ['articles', Fields::SCREEN];

    /**
     * An article's own fields, in a row of the repeater. Anything else in a row is a project's.
     *
     * @var list<string>
     */
    private const ROW = ['id', 'title', 'excerpt', 'kind', 'published_on', 'date_precision', 'url', 'file', 'is_hidden'];

    /** The longest title of an article, per language. */
    private const TITLE_MAX = 500;

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly Locales $locales,
        private readonly ConnectionInterface $db,
        private readonly MediaFiles $files,
        private readonly Cards $cards,
        private readonly Config $config,
    ) {}

    /**
     * An outlet and the values of its screen — what `GET`, `POST` and `PUT` answer (§4.10).
     *
     * @return array<string, mixed>
     */
    public function describe(Outlet $outlet): array
    {
        return [
            'outlet' => [
                'id' => (int) $outlet->getKey(),
                'title' => OutletNames::of($outlet, $this->locales),
                'published' => $outlet->published,
                'deleted_at' => $outlet->deleted_at?->toAtomString(),
                'url' => $outlet->trashed() ? null : $this->cards->url($outlet, $this->locales->current()),
            ],
            'values' => $this->values($outlet),
            'prefix' => $this->pages() ? PressServiceProvider::prefix($this->config) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(Outlet $outlet): array
    {
        return [
            // The project's fields first, so that none of them can stand in for one of the
            // outlet's own.
            ...($outlet->extraRaw() ?? []),
            'title' => $outlet->getTranslations('title'),
            'slug' => $outlet->getTranslations('slug'),
            'summary' => $outlet->getTranslations('summary'),
            'logo' => $outlet->logo,
            'website_url' => $outlet->website_url,
            'featured' => $outlet->featured,
            'published' => $outlet->published,
            'articles' => $outlet->articles()->get()->map(static fn (Article $article): array => [
                ...($article->extraRaw() ?? []),
                'id' => (int) $article->getKey(),
                'title' => $article->getTranslations('title'),
                'excerpt' => $article->getTranslations('excerpt'),
                'kind' => $article->kind,
                'published_on' => $article->published_on?->toDateString(),
                'date_precision' => $article->date_precision,
                'url' => $article->url,
                'file' => $article->file,
                'is_hidden' => $article->is_hidden,
            ])->values()->all(),
            Fields::SCREEN => $outlet->seoValue(),
        ];
    }

    /**
     * Check what came in against the screen and write it — a new outlet or an existing one, the
     * panel's door and an agent's alike.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Outlet $outlet, array $input, ?callable $can = null): Outlet
    {
        $this->check($outlet, $input);

        $split = $this->record->split(Outlet::SCREEN, $input, self::OWN, self::TAKEN, $can);

        return $this->db->transaction(fn (): Outlet => Outlet::holdingAddresses(function () use ($outlet, $split): Outlet {
            foreach ($split->own as $field => $value) {
                $this->write($outlet, $field, $value);
            }

            if ($split->extra !== []) {
                $outlet->setAttribute('extra', $this->record->merge(Outlet::SCREEN, $outlet->extraRaw(), $split->extra));
            }

            $outlet->save();

            if (array_key_exists('articles', $split->taken)) {
                $rows = $split->taken['articles'];

                $this->writeArticles($outlet, is_array($rows) ? array_values($rows) : []);
            }

            // Only when it travelled — a save of another tab must not empty a card nobody opened.
            if (array_key_exists(Fields::SCREEN, $split->taken)) {
                $value = $split->taken[Fields::SCREEN];

                $outlet->saveSeo(is_array($value) ? $value : null);
            }

            // Once, at the end of the hold, whatever changed: a language the editor emptied the
            // slug of gets it back while it has articles (see `Outlet::fillSlugs()`).
            $outlet->syncAddresses();

            return $outlet;
        }))->refresh();
    }

    /**
     * What the screen cannot check of a row, checked first and named by row, field and language.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    private function check(Outlet $outlet, array $input): void
    {
        $errors = [];

        if (array_key_exists('website_url', $input) && ! self::isWebAddress($input['website_url'])) {
            $errors['website_url'] = [(string) __('webx-press::errors.website-url')];
        }

        $rows = $input['articles'] ?? null;

        if (! is_array($rows)) {
            $this->refuse($errors);

            return;
        }

        $own = $outlet->exists
            ? $outlet->articles()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all()
            : [];

        // The library rows of every PDF on the form in one query, for the check of their kind.
        $this->files->load(array_values(array_filter(array_map(
            static fn (mixed $row): ?string => is_array($row) && is_array($row['file'] ?? null) && is_string($row['file']['path'] ?? null) ? $row['file']['path'] : null,
            $rows,
        ))));

        foreach (array_values($rows) as $n => $row) {
            if (! is_array($row)) {
                continue;
            }

            $at = "articles.{$n}";
            $id = $row['id'] ?? null;

            if ($id !== null && $id !== '' && (! is_numeric($id) || ! in_array((int) $id, $own, true))) {
                $errors['articles'][] = (string) __('webx-press::errors.foreign-article', ['number' => $n + 1]);
            }

            foreach ($this->titleProblems($row['title'] ?? null) as $locale => $problem) {
                $errors["{$at}.title.{$locale}"] = [$problem];
            }

            $url = $row['url'] ?? null;
            $hasUrl = is_string($url) && trim($url) !== '';
            $path = is_array($row['file'] ?? null) ? ($row['file']['path'] ?? null) : null;
            $hasFile = is_string($path) && $path !== '';

            if ($hasUrl && ! self::isWebAddress($url)) {
                $errors["{$at}.url"] = [(string) __('webx-press::errors.url')];
            } elseif (! $hasUrl && ! $hasFile) {
                $errors["{$at}.url"] = [(string) __('webx-press::errors.target')];
            }

            if ($hasFile) {
                $file = $this->files->find($path);

                // A key the library does not have any more is left alone, as `wx-file` leaves it:
                // the card shows which one fell out, and a 422 would hold up the whole outlet.
                if ($file instanceof MediaFile && $file->mime !== 'application/pdf') {
                    $errors["{$at}.file"] = [(string) __('webx-press::errors.pdf')];
                }
            }

            $kind = $row['kind'] ?? null;

            if ($kind !== null && $kind !== '' && ! Kinds::has($kind)) {
                $errors["{$at}.kind"] = [(string) __('webx-press::errors.kind', ['kinds' => implode(', ', Kinds::all())])];
            }

            $precision = $row['date_precision'] ?? null;

            if ($precision !== null && $precision !== '' && ! in_array($precision, Article::PRECISIONS, true)) {
                $errors["{$at}.date_precision"] = [(string) __('webx-press::errors.precision')];
            }
        }

        $this->refuse($errors);
    }

    /**
     * What is wrong with a row's title, by language: too long in one, or written in none. An
     * article with no title anywhere is seen nowhere (decision 7), and saving it would be saving
     * something that looks like an article and is not one.
     *
     * @return array<string, string>
     */
    private function titleProblems(mixed $title): array
    {
        $map = is_array($title) ? $title : [$this->locales->current() => $title];
        $problems = [];
        $written = false;

        foreach ($map as $locale => $text) {
            if (! is_string($text) || trim($text) === '') {
                continue;
            }

            $written = true;

            if (mb_strlen(trim($text)) > self::TITLE_MAX) {
                $problems[(string) $locale] = (string) __('webx-press::errors.title-length', ['max' => self::TITLE_MAX]);
            }
        }

        if (! $written) {
            $problems[$this->locales->current()] = (string) __('webx-press::errors.title');
        }

        return $problems;
    }

    /**
     * @param  array<string, list<string>>  $errors
     *
     * @throws ValidationException
     */
    private function refuse(array $errors): void
    {
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function write(Outlet $outlet, string $field, mixed $value): void
    {
        switch ($field) {
            case 'published':
            case 'featured':
                $outlet->setAttribute($field, (bool) $value);
                break;
            case 'website_url':
                $outlet->website_url = is_string($value) && trim($value) !== '' ? trim($value) : null;
                break;
            case 'logo':
                $outlet->logo = is_array($value) ? $value : null;
                break;
            default:
                $this->translate($outlet, $field, $value);
        }
    }

    /**
     * The rows, matched to the articles by id and written in their order (§4.6).
     *
     * @param  list<mixed>  $rows
     */
    private function writeArticles(Outlet $outlet, array $rows): void
    {
        /** @var array<int, Article> $existing */
        $existing = $outlet->articles()->get()->keyBy(static fn (Article $article): int => (int) $article->getKey())->all();
        $kept = [];
        $position = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : null;

            // A row copied with its id is the same article twice: the second is a new one.
            $article = $id !== null && isset($existing[$id]) && ! isset($kept[$id])
                ? $existing[$id]
                : new Article(['outlet_id' => $outlet->getKey()]);

            $article->position = ++$position;
            $this->fillArticle($article, $row);
            $article->save();

            $kept[(int) $article->getKey()] = true;
        }

        foreach ($existing as $id => $article) {
            if (! isset($kept[$id])) {
                $article->delete();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function fillArticle(Article $article, array $row): void
    {
        $extra = [];

        foreach ($row as $field => $value) {
            switch ($field) {
                case 'id':
                    break;
                case 'title':
                case 'excerpt':
                    $this->translate($article, $field, $value);
                    break;
                case 'kind':
                    $article->kind = is_string($value) && $value !== '' ? $value : null;
                    break;
                case 'published_on':
                    $article->setAttribute('published_on', self::day($value));
                    break;
                case 'date_precision':
                    $article->date_precision = is_string($value) ? $value : Article::DAY;
                    break;
                case 'url':
                    $article->url = is_string($value) && trim($value) !== '' ? trim($value) : null;
                    break;
                case 'file':
                    $article->file = is_array($value) ? $value : null;
                    break;
                case 'is_hidden':
                    $article->is_hidden = (bool) $value;
                    break;
                default:
                    if (! in_array($field, self::ROW, true)) {
                        $extra[(string) $field] = $value;
                    }
            }
        }

        if ($extra !== []) {
            $article->setAttribute('extra', [...($article->extraRaw() ?? []), ...$extra]);
        }
    }

    /**
     * A translated field travels as its whole map from the form and as one string from an agent
     * speaking one language; neither is ever written over the whole field. A language the editor
     * emptied comes back as `''` or null and is taken away; a language nobody mentioned is a
     * language nobody meant to delete.
     */
    private function translate(Outlet|Article $model, string $field, mixed $value): void
    {
        if (! in_array($field, $model instanceof Outlet ? Outlet::TRANSLATED : Article::TRANSLATED, true)) {
            return;
        }

        $map = is_array($value) ? $value : [$this->locales->current() => $value];
        $translations = [...$model->getTranslations($field), ...$map];

        $translations = array_map(
            static fn (mixed $text): mixed => is_string($text) ? trim($text) : $text,
            array_filter($translations, static fn (mixed $text): bool => is_string($text) && trim($text) !== ''),
        );

        $model->setTranslations($field, $translations);
    }

    /** A day, whatever the picker sent: the date part of the moment, in the offset it came in. */
    private static function day(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) === 1
            ? trim($value)
            : Carbon::parse($value)->toDateString();
    }

    /**
     * An address to lead a reader to — and nothing a browser would run: `javascript:` in an `href`
     * on the site is somebody else's script on every page it is on. Empty is no address, which is
     * allowed.
     */
    private static function isWebAddress(mixed $value): bool
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        $url = trim($value);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function pages(): bool
    {
        return (bool) $this->config->get('webx-press.pages', true);
    }
}
