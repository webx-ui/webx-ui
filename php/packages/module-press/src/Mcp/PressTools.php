<?php

declare(strict_types=1);

namespace WebxUi\Press\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;
use WebxUi\Press\Panel\OutletForm;
use WebxUi\Press\Panel\OutletList;
use WebxUi\Press\Panel\OutletNames;
use WebxUi\Press\Rendering\When;
use WebxUi\Press\Support\Kinds;

/**
 * What an agent can do with outlets and their articles (§4.11).
 *
 * The same doors the panel uses: the list is {@see OutletList}, the order is {@see Ordering}, and
 * every write goes through {@see OutletForm::save()} — the screen checks the values, the form
 * checks the rows, and all of it is one transaction. The articles have tools of their own so that
 * an agent adding one article does not send back the whole list, but underneath they are the same
 * save of the outlet with one row more, fewer or changed: a refused article leaves the outlet as it
 * was, and a refusal of a row is the panel's refusal of that row.
 *
 * The one write that is not a save of the form is an article moved to another outlet: the form of
 * one outlet cannot take a row of another's, and dropping it here to type it in there would give
 * it a new id.
 *
 * An outlet is named by its id or by its title in any language — a proper name, and the way a
 * person refers to it. An article is named by its id: titles repeat from one outlet to the next.
 */
final class PressTools
{
    /** The outlet's translated fields, which take one language as a string or every one as a map. */
    private const TRANSLATED = ['title', 'slug', 'summary'];

    /** An article's own fields as an agent sends them. */
    private const ARTICLE = ['title', 'excerpt', 'kind', 'published_on', 'date_precision', 'url', 'file', 'is_hidden'];

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $outlet = [
            'type' => ['integer', 'string'],
            'description' => 'An outlet: its id, or its name in any language. press_list and press://catalog have both.',
        ];
        $article = [
            'type' => ['integer', 'string'],
            'description' => 'The article\'s id — press_get and press://catalog have them.',
        ];
        $text = [
            'type' => ['string', 'object'],
            'description' => 'One language as a string (the default language), or every language as { "en": "…", "ru": "…" }.',
        ];
        $kinds = Kinds::all();
        $articleFields = [
            'title' => $text + ['description' => 'The headline, as the outlet printed it. An article is seen only in the languages its title is written in: no other language stands in for it.'],
            'excerpt' => $text + ['description' => 'A sentence or two on what it says, plain text. Not shown in a language it is not written in.'],
            'kind' => [
                'type' => ['string', 'null'],
                'description' => $kinds === []
                    ? 'The site has no kinds of article; leave it out.'
                    : 'What sort of piece it is, one of: '.implode(', ', $kinds).'. Null or left out is no kind.',
            ],
            'published_on' => ['type' => ['string', 'null'], 'description' => 'When it ran, YYYY-MM-DD. With a precision of month or year only that much of it is shown.'],
            'date_precision' => ['type' => 'string', 'enum' => Article::PRECISIONS, 'description' => 'How much of the date is known: day (the default), month or year.'],
            'url' => ['type' => ['string', 'null'], 'description' => 'Where the article is on the web, http(s) only. It needs this, a PDF, or both.'],
            'file' => [
                'type' => ['string', 'object', 'null'],
                'description' => 'A PDF from the library: its key ("media/ab/cd/scan.pdf", as media_list_files gives it). '
                    .'With an address too, the title leads to the address and the PDF is a second link.',
            ],
            'is_hidden' => ['type' => 'boolean', 'description' => 'Kept, but not shown anywhere. False when omitted.'],
        ];

        return [
            Tool::read(
                'list',
                'Outlets in the order they stand in — the order of the strip of logos and the catalogue: each with '
                .'its name, whether it is published and in the strip, how many articles it has and the languages '
                .'a reader sees it in. An outlet is seen only in a language one of its articles is written in. '
                .'Read this (or press://catalog) first: the same outlet typed in twice is a duplicate.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'search' => ['type' => 'string', 'description' => 'Outlets whose name or site, or the title of one of whose articles, contains this — in any language.'],
                    'trashed' => ['type' => 'boolean', 'description' => 'What is in the bin, newest first.'],
                ]],
                permission: ['press.view', 'press.manage'],
            ),

            Tool::read(
                'get',
                'One outlet in full: the values of its editor — name, address and description in every language, '
                .'the logo, the site, the flags, the SEO card and any field the project added — and its articles '
                .'in their order, each with where a reader sees it and where its title leads.',
                fn (array $arguments): array => $this->get($arguments),
                ['properties' => ['outlet' => $outlet], 'required' => ['outlet']],
                permission: ['press.view', 'press.manage'],
            ),

            Tool::mutating(
                'create',
                'Add an outlet — a magazine, a paper, a portal that wrote about the site\'s owner — at the end of '
                .'the list, with its articles if you have them. It is not on the site until it is published, and '
                .'even then only in the languages one of its articles is written in; pass published: true only '
                .'when a person asked for that.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->create($arguments, $user)),
                ['properties' => [
                    'title' => $text + ['description' => 'The outlet\'s name. A name in one language is the name in all of them.'],
                    'summary' => $text + ['description' => 'A line or two on what the outlet is, plain text.'],
                    'slug' => $text + ['description' => 'The address under the prefix. Made from the name when left out.'],
                    'website_url' => ['type' => ['string', 'null'], 'description' => 'The outlet\'s own site, http(s) only.'],
                    'logo' => ['type' => ['string', 'object', 'null'], 'description' => 'A picture from the library: its key, as media_list_files gives it. Without one the site shows the name.'],
                    'featured' => ['type' => 'boolean', 'description' => 'In the strip of logos. False when omitted.'],
                    'published' => ['type' => 'boolean', 'description' => 'On the site at once. False when omitted.'],
                    'articles' => [
                        'type' => 'array',
                        'items' => ['type' => 'object', 'properties' => $articleFields],
                        'description' => 'Its articles, in the order they stand in on its page.',
                    ],
                    'values' => ['type' => 'object', 'description' => 'The fields the project added to the editor, as press_get returns them.'],
                ], 'required' => ['title']],
                permission: 'press.manage',
            ),

            Tool::mutating(
                'update',
                'Change an outlet — any of title, slug, summary, logo, website_url, featured, published, seo and '
                .'the fields the project added. A field left out keeps what it had, and so does a language left '
                .'out of a translated field: send "" for a language to take it away. Its articles are changed '
                .'with press_articles_*, not here. It is on the site at once: outlets have no draft.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    'outlet' => $outlet,
                    'values' => ['type' => 'object', 'description' => 'Field name → value, as press_get returns them. title, slug and summary take a string (the default language) or { "en": "…" }; logo takes a library key.'],
                ], 'required' => ['outlet', 'values']],
                permission: 'press.manage',
            ),

            Tool::mutating(
                'delete',
                'Put an outlet in the bin, its articles with it. It leaves the site and every block at once; the '
                .'bin in the panel brings it back with its articles.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['outlet' => $outlet], 'required' => ['outlet']],
                permission: 'press.manage',
            ),

            Tool::mutating(
                'reorder',
                'Put outlets in a new order — the order of the strip of logos and the catalogue. Name them in the '
                .'order they should stand in; the ones you leave out stay where they are.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorder($arguments)),
                ['properties' => [
                    'outlets' => ['type' => 'array', 'items' => $outlet, 'description' => 'The outlets, first to last.'],
                ], 'required' => ['outlets']],
                permission: 'press.manage',
            ),

            Tool::mutating(
                'articles_add',
                'Add an article to an outlet: at the end of its list, or at a position. Write down what the '
                .'outlet really printed — the headline as it ran, the address it is at: an article made up is '
                .'not press.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->addArticle($arguments, $user)),
                ['properties' => [
                    'outlet' => $outlet,
                    ...$articleFields,
                    'position' => ['type' => 'integer', 'description' => 'Where it stands in the outlet, 1 being the first. The end when left out.'],
                    'values' => ['type' => 'object', 'description' => 'The fields the project added to an article, as press_get returns them.'],
                ], 'required' => ['outlet', 'title']],
                permission: 'press.manage',
            ),

            Tool::mutating(
                'articles_update',
                'Change an article — any of title, excerpt, kind, published_on, date_precision, url, file, '
                .'is_hidden and the fields the project added. A field left out keeps what it had, and so does a '
                .'language left out of title or excerpt: send "" for a language to take it away.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->updateArticle($arguments, $user)),
                ['properties' => [
                    'article' => $article,
                    'values' => ['type' => 'object', 'description' => 'Field name → value, as press_get returns an article.'],
                ], 'required' => ['article', 'values']],
                permission: 'press.manage',
            ),

            Tool::mutating(
                'articles_delete',
                'Delete an article for good — articles have no bin. To keep it but not show it, set is_hidden '
                .'with press_articles_update instead.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->deleteArticle($arguments, $user)),
                ['properties' => ['article' => $article], 'required' => ['article']],
                permission: 'press.manage',
            ),

            Tool::mutating(
                'articles_move',
                'Move an article to another place in its outlet, or to another outlet — one that was entered '
                .'twice, say. It keeps its id and everything in it.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->moveArticle($arguments, $user)),
                ['properties' => [
                    'article' => $article,
                    'position' => ['type' => 'integer', 'description' => 'Where it stands in the outlet, 1 being the first. The end when left out.'],
                    'outlet' => $outlet + ['description' => 'The outlet to move it into. Its own when left out.'],
                ], 'required' => ['article']],
                permission: 'press.manage',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        // The panel's own query: no pages, because the order is only an order when all of it is in
        // view.
        $outlets = $this->container->make(OutletList::class)->build(Request::create('/', 'GET', [
            'search' => (string) ($arguments['search'] ?? ''),
            'trashed' => ($arguments['trashed'] ?? false) === true ? '1' : '0',
        ]))->get();

        return [
            'locales' => $this->locales()->codes(),
            'default_locale' => $this->locales()->defaultCode(),
            'kinds' => Kinds::all(),
            'count' => $outlets->count(),
            'outlets' => $outlets->map(fn (Outlet $outlet): array => $this->summary($outlet))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments): array
    {
        return $this->describe($this->outlet($arguments['outlet'] ?? null));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
        $values = $arguments['values'] ?? [];

        if (! is_array($values)) {
            throw new ToolFailure('`values` must be an object of field name → value.');
        }

        // The named arguments win over the same names in `values`: they are what the tool says it
        // takes, and an agent that sent both meant the one it could see.
        $values = [
            ...$values,
            'title' => $this->text($arguments['title'] ?? null, 'title'),
            'published' => ($arguments['published'] ?? false) === true,
            'featured' => ($arguments['featured'] ?? false) === true,
        ];

        foreach (['summary', 'slug'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $values[$field] = $this->text($arguments[$field], $field, empty: true);
            }
        }

        foreach (['website_url', 'logo'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $values[$field] = $arguments[$field];
            }
        }

        $articles = $arguments['articles'] ?? [];

        if (! is_array($articles)) {
            throw new ToolFailure('`articles` is a list of articles, each an object like press_articles_add takes.');
        }

        $values['articles'] = array_map(fn (mixed $one): array => $this->row([], is_array($one) ? $one : []), array_values($articles));
        $values = $this->prepare($values);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_create' => [
                    'title' => $values['title'],
                    'published' => $values['published'],
                    'featured' => $values['featured'],
                    'articles' => count($values['articles']),
                ],
            ];
        }

        $outlet = $this->form()->save(new Outlet, $values, $this->can($user));

        return $this->describe($outlet);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $outlet = $this->writable($arguments['outlet'] ?? null);
        $values = $arguments['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new ToolFailure('`values` must be a non-empty object of field name → value. press_get says what the fields are.');
        }

        // Merged language by language, and a language the site does not have refused — dry run
        // included: `{"slug": {"de": …}}` changes the German address and leaves the others.
        $values = $this->container->make(ScreenValues::class)->patch(Outlet::SCREEN, $this->form()->values($outlet), $values);

        if (array_key_exists('articles', $values)) {
            // The whole list in one value is the list as the agent last read it, and an article
            // added since would be deleted by leaving it out.
            throw new ToolFailure('Articles are changed one at a time: press_articles_add, press_articles_update, press_articles_delete and press_articles_move.');
        }

        foreach (self::TRANSLATED as $field) {
            // A plain string is the default language, as in press_create — not the language of a
            // request that never chose one.
            if (is_string($values[$field] ?? null)) {
                $values[$field] = [$this->locales()->defaultCode() => $values[$field]];
            }
        }

        $values = $this->prepare($values);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'fields' => array_keys($values), 'outlet' => $this->reference($outlet)];
        }

        $this->form()->save($outlet, $values, $this->can($user));

        return $this->describe($outlet);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $outlet = $this->outlet($arguments['outlet'] ?? null);

        if ($outlet->trashed()) {
            return ['trashed' => true, 'id' => (int) $outlet->getKey(), 'already' => true];
        }

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_trash' => $this->reference($outlet),
                'published' => $outlet->published,
                'articles' => $outlet->articles()->count(),
            ];
        }

        $outlet->delete();

        return ['trashed' => true, 'id' => (int) $outlet->getKey()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $given = $arguments['outlets'] ?? null;

        if (! is_array($given) || $given === []) {
            throw new ToolFailure('`outlets` is the list of outlets in their new order.');
        }

        $ids = array_values(array_unique(array_map(fn (mixed $one): int => (int) $this->writable($one)->getKey(), $given)));

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_order' => $ids];
        }

        Ordering::move(Outlet::class, $ids);

        return $this->list([]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function addArticle(array $arguments, ?Authenticatable $user): array
    {
        $outlet = $this->writable($arguments['outlet'] ?? null);
        $rows = $this->rows($outlet);
        $row = $this->prepareRow($this->row([], $arguments));
        $at = $this->position($arguments['position'] ?? null, count($rows));

        array_splice($rows, $at, 0, [$row]);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_add' => $row['title'], 'outlet' => $this->reference($outlet), 'position' => $at + 1];
        }

        $this->saveRows($outlet, $rows, $user);

        $added = $outlet->articles()->get()->values()->get($at);

        return [
            'article' => $added instanceof Article ? $this->articleSummary($added) : null,
            ...$this->describe($outlet),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function updateArticle(array $arguments, ?Authenticatable $user): array
    {
        $article = $this->findArticle($arguments['article'] ?? null);
        $outlet = $this->writable($article->outlet_id);
        $values = $arguments['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new ToolFailure('`values` must be a non-empty object of field name → value. press_get says what an article holds.');
        }

        $rows = $this->rows($outlet);
        $n = $this->indexOf($rows, $article);
        $rows[$n] = $this->prepareRow($this->row($rows[$n], $values));

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'fields' => array_keys($values), 'article' => (int) $article->getKey(), 'outlet' => $this->reference($outlet)];
        }

        $this->saveRows($outlet, $rows, $user);

        return [
            'article' => $this->articleSummary($article->refresh()),
            ...$this->describe($outlet),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deleteArticle(array $arguments, ?Authenticatable $user): array
    {
        $article = $this->findArticle($arguments['article'] ?? null);
        $outlet = $this->writable($article->outlet_id);
        $rows = $this->rows($outlet);

        array_splice($rows, $this->indexOf($rows, $article), 1);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_delete' => (int) $article->getKey(), 'outlet' => $this->reference($outlet)];
        }

        $this->saveRows($outlet, $rows, $user);

        return ['deleted' => true, 'id' => (int) $article->getKey(), ...$this->describe($outlet)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function moveArticle(array $arguments, ?Authenticatable $user): array
    {
        $article = $this->findArticle($arguments['article'] ?? null);
        $from = $this->writable($article->outlet_id);
        $to = array_key_exists('outlet', $arguments) && $arguments['outlet'] !== null
            ? $this->writable($arguments['outlet'])
            : $from;

        if ((int) $to->getKey() === (int) $from->getKey()) {
            $rows = $this->rows($from);
            $n = $this->indexOf($rows, $article);
            [$row] = array_splice($rows, $n, 1);
            $at = $this->position($arguments['position'] ?? null, count($rows));
            array_splice($rows, $at, 0, [$row]);

            if ($this->dryRun($arguments)) {
                return ['dry_run' => true, 'would_move' => (int) $article->getKey(), 'position' => $at + 1];
            }

            $this->saveRows($from, $rows, $user);

            return ['article' => $this->articleSummary($article->refresh()), ...$this->describe($from)];
        }

        $ids = $to->articles()->pluck('id')->map(intval(...))->all();
        $at = $this->position($arguments['position'] ?? null, count($ids));
        array_splice($ids, $at, 0, [(int) $article->getKey()]);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_move' => (int) $article->getKey(), 'into' => $this->reference($to), 'position' => $at + 1];
        }

        // Not a save of either form — a row of one outlet is refused by the form of another — so
        // the transaction and the one pass over the addresses are taken here, as the form takes
        // them.
        $this->container->make(ConnectionInterface::class)->transaction(static fn (): bool => Outlet::holdingAddresses(static function () use ($article, $from, $to, $ids): bool {
            $article->outlet_id = (int) $to->getKey();
            $article->save();

            foreach ([$to->getKey() => $ids, $from->getKey() => $from->articles()->pluck('id')->map(intval(...))->all()] as $outlet => $order) {
                foreach (array_values($order) as $position => $id) {
                    Article::query()->whereKey($id)->where('outlet_id', $outlet)->update(['position' => $position + 1]);
                }
            }

            return true;
        }));

        return ['article' => $this->articleSummary($article->refresh()), ...$this->describe($to)];
    }

    /**
     * The rows of the articles repeater, as the form holds them now.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(Outlet $outlet): array
    {
        $rows = $this->form()->values($outlet)['articles'] ?? [];

        return array_values(array_filter(is_array($rows) ? $rows : [], is_array(...)));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function indexOf(array $rows, Article $article): int
    {
        foreach ($rows as $n => $row) {
            if ((int) ($row['id'] ?? 0) === (int) $article->getKey()) {
                return $n;
            }
        }

        throw new ToolFailure("Article #{$article->getKey()} is not in its outlet any more — press_get it again.");
    }

    /**
     * The list written back through the outlet's form, and a refused row named the way the agent
     * named it, not by its number on a form it never saw.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function saveRows(Outlet $outlet, array $rows, ?Authenticatable $user): void
    {
        try {
            $this->form()->save($outlet, ['articles' => array_values($rows)], $this->can($user));
        } catch (ValidationException $invalid) {
            $errors = [];

            foreach ($invalid->errors() as $field => $messages) {
                $name = preg_replace_callback('/^articles\.(\d+)\./', static function (array $match) use ($rows): string {
                    $id = $rows[(int) $match[1]]['id'] ?? null;

                    return is_numeric($id) ? "article #{$id} " : 'the new article ';
                }, $field);

                $errors[(string) $name] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * An article's row: what it holds now, with what the agent sent over it. Translated fields
     * merge by language — a language left out keeps its text, `""` takes it away.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $given
     * @return array<string, mixed>
     */
    private function row(array $row, array $given): array
    {
        $values = $given['values'] ?? [];

        if (array_key_exists('values', $given) && ! is_array($values)) {
            throw new ToolFailure('`values` must be an object of field name → value.');
        }

        // The project's fields first, as the form writes them, so none can stand in for an
        // article's own.
        $row = [...$row, ...(is_array($values) ? $values : [])];

        foreach ($given as $field => $value) {
            if (! in_array($field, self::ARTICLE, true)) {
                continue;
            }

            if ($field === 'title' || $field === 'excerpt') {
                $current = is_array($row[$field] ?? null) ? $row[$field] : [];
                $row[$field] = [...$current, ...$this->text($value, $field, empty: $field === 'excerpt' || $current !== [], keep: true)];

                continue;
            }

            $row[$field] = $value;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function prepareRow(array $row): array
    {
        if (is_string($row['file'] ?? null)) {
            $row['file'] = trim($row['file']) === '' ? null : ['path' => trim($row['file'])];
        }

        $this->exists(is_array($row['file'] ?? null) ? ($row['file']['path'] ?? null) : null);

        return $row;
    }

    /**
     * What the screen takes, from what an agent is likely to send: a logo named by its key becomes
     * the value `wx-media` stores, and so does every article's PDF.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function prepare(array $values): array
    {
        if (is_string($values['logo'] ?? null)) {
            $values['logo'] = trim($values['logo']) === '' ? null : ['path' => trim($values['logo'])];
        }

        $this->exists(is_array($values['logo'] ?? null) ? ($values['logo']['path'] ?? null) : null);

        if (is_array($values['articles'] ?? null)) {
            $values['articles'] = array_map(fn (array $row): array => $this->prepareRow($row), array_values(array_filter($values['articles'], is_array(...))));
        }

        return $values;
    }

    /**
     * `wx-media` and `wx-file` let a key the library does not have through — the panel only offers
     * keys it has. An agent types them, and a typo would leave an article with nowhere to lead.
     */
    private function exists(mixed $path): void
    {
        if (is_string($path) && $path !== '' && ! MediaFile::query()->where('path', $path)->exists()) {
            throw new ToolFailure("The library has no file [{$path}]. media_search_files finds one by name.");
        }
    }

    /** A position given from 1, as the index it is inserted at; the end when not given. */
    private function position(mixed $given, int $count): int
    {
        if ($given === null) {
            return $count;
        }

        if (! is_int($given) && ! (is_string($given) && ctype_digit($given))) {
            throw new ToolFailure('`position` is a number, 1 being the first.');
        }

        return max(0, min($count, (int) $given - 1));
    }

    /**
     * An outlet and what it holds, as the agent needs it after reading or writing it.
     *
     * @return array<string, mixed>
     */
    private function describe(Outlet $outlet): array
    {
        $outlet->refresh()->load('articles');
        $values = $this->form()->values($outlet);
        unset($values['articles']);

        return [
            'outlet' => $this->summary($outlet),
            'values' => $values,
            'articles' => $outlet->articles->map(fn (Article $article): array => $this->articleSummary($article))->values()->all(),
        ];
    }

    /**
     * One outlet: its name in every language, and where a reader sees it — a published outlet seen
     * in no language is the one worth pointing out (decision 7).
     *
     * @return array<string, mixed>
     */
    private function summary(Outlet $outlet): array
    {
        $outlet->loadMissing('articles');
        $has = $outlet->localesWithArticles();

        $summary = [
            'id' => (int) $outlet->getKey(),
            'title' => $outlet->getTranslations('title'),
            'website_url' => $outlet->website_url,
            'logo' => $outlet->logoPath(),
            'published' => $outlet->published,
            'featured' => $outlet->featured,
            'visible_in' => $outlet->published && ! $outlet->trashed() ? $has : [],
            'has_articles_in' => $has,
            'articles' => $outlet->articles->count(),
            'position' => (int) $outlet->position,
            'updated_at' => $outlet->updated_at?->toAtomString(),
        ];

        if ($outlet->trashed()) {
            $summary['deleted_at'] = $outlet->deleted_at?->toAtomString();
        }

        return $summary;
    }

    /**
     * One article: every language of it, and where it is seen and where it leads.
     *
     * @return array<string, mixed>
     */
    private function articleSummary(Article $article): array
    {
        $codes = $this->locales()->codes();
        $written = array_values(array_filter($codes, static fn (string $code): bool => $article->text('title', $code) !== ''));

        return [
            'id' => (int) $article->getKey(),
            'title' => $article->getTranslations('title'),
            'excerpt' => $article->getTranslations('excerpt'),
            'kind' => $article->kind,
            'published_on' => $article->published_on?->toDateString(),
            'date_precision' => $article->date_precision,
            'when' => When::of($article, $this->locales()->defaultCode()),
            'url' => $article->url,
            'file' => $article->filePath(),
            'target' => $article->target(),
            'is_hidden' => $article->is_hidden,
            'position' => (int) $article->position,
            'written_in' => $written,
            // Its outlet's publication aside: whether it is shown once the outlet is.
            'visible_in' => array_values(array_filter($codes, $article->visibleIn(...))),
            ...(($article->extraRaw() ?? []) === [] ? [] : ['values' => $article->extraRaw()]),
        ];
    }

    private function reference(Outlet $outlet): string
    {
        return sprintf('#%s (%s)', $outlet->getKey(), OutletNames::of($outlet, $this->locales()));
    }

    /**
     * An outlet by id or by its name in any language. Two outlets by one name is a question for
     * the agent, not a guess.
     */
    private function outlet(mixed $reference): Outlet
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit(ltrim(trim($reference), '#')))) {
            $id = (int) ltrim(trim((string) $reference), '#');
            $outlet = Outlet::withTrashed()->find($id);

            return $outlet instanceof Outlet ? $outlet : throw new ToolFailure("No outlet has the id [{$id}].");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure('An outlet is its id or its name — press_list has both.');
        }

        $name = mb_strtolower(trim($reference));
        // Compared here rather than by `LIKE`: sqlite folds the case of ASCII only, and an outlet
        // named in Russian in lower case would not be found. A site has tens of outlets, not more.
        $found = Outlet::query()->get()
            ->filter(static fn (Outlet $outlet): bool => in_array($name, array_map(
                static fn (mixed $title): string => is_string($title) ? mb_strtolower(trim($title)) : '',
                $outlet->getTranslations('title'),
            ), true))
            ->values();

        if ($found->count() > 1) {
            throw new ToolFailure(sprintf(
                'More than one outlet is called [%s]: #%s. Name it by its id.',
                trim($reference),
                $found->map(static fn (Outlet $outlet): int => (int) $outlet->getKey())->implode(', #'),
            ));
        }

        $outlet = $found->first();

        return $outlet instanceof Outlet
            ? $outlet
            : throw new ToolFailure('No outlet is called ['.trim($reference).']. press_list says what there is.');
    }

    /** An outlet that can be written to — not one in the bin. */
    private function writable(mixed $reference): Outlet
    {
        $outlet = $this->outlet($reference);

        if ($outlet->trashed()) {
            throw new ToolFailure("Outlet #{$outlet->getKey()} is in the bin. Bring it back in the panel before editing it.");
        }

        return $outlet;
    }

    /** An article by id — titles repeat from one outlet to the next. */
    private function findArticle(mixed $reference): Article
    {
        if (! is_int($reference) && ! (is_string($reference) && ctype_digit(ltrim(trim($reference), '#')))) {
            throw new ToolFailure('An article is its id — press_get has them.');
        }

        $id = (int) ltrim(trim((string) $reference), '#');
        $article = Article::query()->find($id);

        return $article instanceof Article
            ? $article
            : throw new ToolFailure("No article has the id [{$id}].");
    }

    /**
     * Run a change, and turn a refusal into something the agent can read.
     *
     * @param  callable(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(callable $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        }
    }

    /**
     * A translated field as the form takes it: a map of languages. A plain string is the default
     * language — the form would read it as the language of the request, and an agent's request has
     * none it chose.
     *
     * With `keep`, an emptied language stays in the map as `""`: merged over what the field holds,
     * that is what takes the language away.
     *
     * @return array<string, string>
     */
    private function text(mixed $value, string $field, bool $empty = false, bool $keep = false): array
    {
        if (is_string($value)) {
            if (trim($value) !== '') {
                return [$this->locales()->defaultCode() => trim($value)];
            }

            return $empty ? [] : throw new ToolFailure("`{$field}` cannot be empty.");
        }

        if ($value === null && $empty) {
            return [];
        }

        if (! is_array($value)) {
            throw new ToolFailure("`{$field}` is a string, or an object keyed by language code.");
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            // Words in a language the site is not published in are words nobody reads — and
            // an address in one is an address that answers nowhere.
            if (! $this->locales()->has((string) $locale)) {
                throw new ToolFailure("`{$field}` has a value in [{$locale}], which this site is not published in. It has: ".implode(', ', $this->locales()->codes()).'.');
            }

            if (is_string($text) && trim($text) !== '') {
                $texts[(string) $locale] = trim($text);
            } elseif ($keep && ($text === '' || $text === null)) {
                $texts[(string) $locale] = '';
            }
        }

        return array_filter($texts) === [] && ! $empty ? throw new ToolFailure("`{$field}` cannot be empty.") : $texts;
    }

    /**
     * The permission check the screen asks for a field behind one — the same question the panel
     * asks of its editor.
     *
     * @return (callable(string): bool)|null
     */
    private function can(?Authenticatable $user): ?callable
    {
        return $user instanceof HasPermissions
            ? static fn (string $permission): bool => $user->hasPermission($permission)
            : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function form(): OutletForm
    {
        return $this->container->make(OutletForm::class);
    }

    private function locales(): Locales
    {
        return $this->container->make(Locales::class);
    }
}
