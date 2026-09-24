# Collections

Some records have no page of their own. A question of the FAQ, a member of the team, a review —
nobody opens them one at a time; they appear on the site as a list inside some other page: "the
questions about payment" on the page of a service, "all the questions, with a filter" on a page
called `/faq`. Such a module has no public routes at all. Its records reach the site **as a
block**, placed on any page by whoever edits it.

This page is for somebody writing such a module. The contract has three parts: a **source** the
module registers, a field type **`wx-collection`** a block's schema uses to name it, and the
**block types the module offers**, which a site installs once. The FAQ is the first module built
on it; Team and Reviews are the next.

## What the editor sees

A block type made for questions has one field of type `wx-collection`:

```json
{ "type": "wx-collection", "id": "questions", "label": "Questions", "props": { "source": "faq" } }
```

On the page, that field is a small form: which categories of the source to show (none chosen —
all of them), how many (empty — all), whether to put a filter by category above the list, and,
when the source can do it, whether to mark the list up for search engines. The records themselves
are not chosen and not copied: a question edited in the FAQ is edited on every page that shows it.

What the page keeps is only the choice:

```json
{ "categories": [3, 5], "limit": null, "filter": false, "markup": null }
```

The source is not part of it. It is written in the schema, so a block built for questions does not
turn into a block of reviews by an edit of its content.

`markup: null` means "by the rule": on when no category is chosen, off when some are. Search
engines ask not to mark the same question up on several pages, and a block with "questions about
payment" usually stands on every service. The editor can switch it either way — it is their page —
and the field says what the rule would give and offers the way back to it.

A source whose records point at another section's can also be narrowed to the ones related to a
chosen record — "the recipes of this service". That choice is a fifth key, `related`, described
on the [Relations](./relations#in-a-block-only-related-to) page.

## The source

A module describes its records once, in `WebxUi\Admin\Collections\CollectionSource`:

```php
use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\Selection;

final class QuestionSource implements CollectionSource
{
    public function key(): string { return 'faq'; }            // what a schema writes in props.source
    public function title(): string { return __('webx-faq::module.title'); }
    public function categories(): ?string { return 'faq/categories'; } // null — no categories
    public function supportsMarkup(): bool { return true; }
    public function permission(): ?string { return 'faq.view'; }       // who may place it

    public function items(Selection $selection, string $locale): array
    {
        $query = Question::query()
            ->where('published', true)
            ->whereNotNull("question->{$locale}") // not in this language — not shown: the source decides
            ->with('categories');

        $items = $selection->apply($query)->get()->map(fn (Question $question): array => [
            'id' => $question->id,
            'anchor' => $question->anchor,
            'categories' => $question->categories->modelKeys(),
            'question' => $question->getTranslation('question', $locale),
            'answer' => $question->answerHtml($locale),
        ])->values()->all();

        if ($selection->markup) {
            // your own JSON-LD — see "One markup per page" below
        }

        return $items;
    }
}
```

- **`items()` returns a plain list**, in the order to show, with `id`, `anchor` and `categories`
  in every element and whatever else the module's records need. A `Collection` there would not
  pass PHPStan: its type is invariant, and a literal element never matches it.
- **What is visible is the source's decision**: published, not in the bin, translated into
  `$locale`. The contract only hands it the language.
- **`Selection::apply()`** is the shared part for a model on `HasCategories`
  ([Categories](/guide/categories)): several categories by a subquery, without repeats; one
  category in its own order (`item_position`), anything else in the general one (`position`); the
  limit. The visibility goes on the query before it.
- **`permission()`** decides who sees the source in the field at all. An administrator without it
  gets a note instead of a form, and `GET /api/cms/collections` does not list it for them.

The source is registered from the provider — not from the route file, which a cached route table
never runs — and so are its categories, under the path `categories()` returns:

```php
public function boot(): void
{
    $this->app->make(CollectionSources::class)->register(new QuestionSource);

    $this->app->make(CategorySources::class)
        ->register('faq/categories', FaqCategory::class, 'webx-faq::errors.unknown-category');
}
```

The second line is what the field lists categories from and what the filter groups are read from.
A category without an address is fine: the FAQ's have none.

## What the template gets

The block's template does not see the stored choice. It sees the records, already read in the
language of the page:

```blade
@if ($questions['filter'])
    <nav class="b-faq__groups">
        @foreach ($questions['groups'] as $group)
            <button data-group="{{ $group['id'] }}">{{ $group['title'] }}</button>
        @endforeach
    </nav>
@endif

@foreach ($questions['items'] as $item)
    <details id="{{ $item['anchor'] }}" data-groups="{{ implode(' ', $item['categories']) }}">
        <summary>{{ $item['question'] }}</summary>
        {!! $item['answer'] !!}
    </details>
@endforeach
```

- `items` — the list from `items()`.
- `groups` — for the filter: `['id', 'title', 'items' => [id…]]`, the visible categories that
  hold at least one shown record, in the categories' order. Empty when the filter is off. A button
  that would empty the list is never offered, chosen category or not.
- `filter` — whether the editor asked for one.

A block placed and never touched shows the whole collection: the field has no value yet, and
`wx-collection` reads a missing value as "all". A source that is gone — the module was removed — is
an empty list rather than an error, and the page goes on living.

## The block types a module offers

Block types are made in the panel and live in the database, and a module does not register one. It
**offers** it: the documents `webx:blocks:export` writes, kept in the module's `resources/blocks`.

```php
// boot() — the key is the module's id in the panel, the one webx:setup asks about
if (class_exists(BlockOffers::class)) {
    $this->app->make(BlockOffers::class)->offer('faq', __DIR__.'/../resources/blocks');
}
```

The site takes them once:

```sh
php artisan webx:blocks:offered            # what is offered, and what of it is already here
php artisan webx:blocks:offered --install  # install and publish what is missing
```

`webx:setup` runs the install for the modules somebody chooses. On a site that adds the module to
an existing panel it is the one command the module's README names. **A type with the same slug is
never touched** — the site may have rebuilt it from top to bottom. An offered type that does not
draw on its own `sample` stays a draft, and the command exits with 1.

## One markup per page

Two FAQ blocks can stand on one page, and one question can be in both. Pushed one after another,
they would give two `FAQPage` blocks with that question twice. So a source that marks up keeps what
it has gathered during the request, without repeats, and puts the whole block again under its key
each time:

```php
$seo = app(\WebxUi\Seo\Rendering\Seo::class);

$this->seen += collect($items)->keyBy('id')->all();   // by id, so a repeat is one entry

$seo->put('faq', [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn (array $item): array => [
        '@type' => 'Question',
        'name' => $item['question'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
    ], array_values($this->seen)),
]);
```

`put()` keeps one block per key and the last one wins; an empty block takes the key away. They are
printed after everything `push()` collected.

::: warning The order the page is drawn in
This works because the content of a page is drawn before the layout it stands in, and the layout is
where `@webxSeo` prints the `<head>`. A site whose layout prints the `<head>` before it renders the
blocks — a layout that renders the content into a variable after the head, a component that draws
its own `<head>` — gets no markup from the blocks at all. The blocks themselves are fine; only the
markup is missing. That is the site's layout to change, not something the module can work around.
:::

## The panel half

Nothing to write. `wx-collection` is a type of the panel (`@webx-ui/module-admin`), registered with
the rest, and it takes the words from `webx-admin::collections.*` and the name of the source from
the server. A module's npm package only needs its own screens.
