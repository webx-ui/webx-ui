# Help in the panel

Every screen of the panel has a «?» beside its title. It opens a side panel that says why the
section exists, how to do the usual things in it, and what each field does. The form stays open
next to it. This page is for whoever writes a section; the design and its reasons are in
`docs/architecture/WEBX_UI_PANEL_HELP.md`.

**The rule:** a new screen or a new field gets its help in the same commit. A test fails the build
on a screen without help and on a field without a description (see
[What goes red](#what-goes-red)).

## Where the texts live

One file is one screen in one language, beside the screen itself:

```
php/packages/module-catalog-stock/resources/
├── screens/stock-status-form.json
└── help/
    ├── en/catalog.stock-status-form.md
    ├── ru/catalog.stock-status-form.md
    └── uk/catalog.stock-status-form.md
```

The name of the file is the key, so there is nothing else to wire. A package registers the folder
once, from its provider:

```php
$this->app->make(HelpRegistry::class)->directory(__DIR__.'/../resources/help');
```

Texts are written in `en`, `ru` and `uk`. The other languages of the panel show English, and the
panel says so in one line.

## The format of a file

```md
---
title: Stock status
guide: catalog#dictionaries-labels-stock-brands
---

# about

Why the section exists, in two or three sentences.

# howto

## How to make a status a product cannot be bought in

1. A step, in the words of the screen.
2. Another step.

# fields

## code

What the field changes on the site, and what it would break.
```

- `title` and `# about` are required. `title` is translated; `guide` and `include` are the same in
  all three languages.
- Under `# fields`, a heading is the **`id` of the node**, not its `name` and not its label. The
  order does not matter: the tabs and their order come from the tree.
- Labels, hints and tabs are not written here. The server takes them from the tree, in the words
  of the form, and leaves out what this administrator cannot see.
- A description goes on from the hint: the hint is shown right above it, so it must not repeat it.
- Paragraphs, lists, inline code, links and emphasis are drawn. Tables, pictures, HTML and headings
  are not, and the test refuses them.

The reader is a shop manager, not a developer: write about what the buyer will see and what will
break, in the words of the screen, without `slug`, `cache` or the names of packages.

## Which key a screen has

| Screen                               | Key                                          |
| ------------------------------------ | -------------------------------------------- |
| A screen described in JSON           | Its name: `catalog.product-form`             |
| A page with no tree (a list, a page) | Its route without `webx.`: `catalog.deleted` |
| A route of one word                  | With `.index`: `webx.faq` is `faq.index`     |

A route names its key itself, and `new` and `edit` of one form name the same one:

```ts
{ path, name: 'webx.faq', meta: { help: 'faq.index' }, component: QuestionsPage }
```

`categoryRoutes` and `dictionarySection` do this on their own. A form inside a list has a head of
its own and says it there: `<wx-screen-head :level="3" help="faq.form" />`. A form in a dialog has
no head, so the page of the list shows its fields with `include: delivery.point-form`.

A key with no file draws no button, so a screen a project adds costs nothing until it has a text.

## Fields of a satellite

A package that adds a field to somebody else's form describes it itself, in a file named after
that form: `module-catalog-stock/resources/help/en/catalog.product-form.md`, with `# fields` and,
if it has something to say, `# howto`. The title and `# about` stay with the owner of the screen.

A package that works without the owner of the form keeps such a file in a folder of its own and
registers it only when the owner is there: `module-configurator` has `resources/help-catalog`, read
when the catalogue is installed. In `resources/help` the file would have no screen on a site without
it, and `webx:help --check` would say that nobody owns the key.

A package that adds the same `id` to many forms writes it once, in `_fields.md` of the language
folder. A file of the screen is stronger than `_fields.md`.

## What goes red

Each package with a screen, a patch or a section carries a test:

```php
final class HelpCoverageTest extends TestCase
{
    use AssertsHelp;

    #[Test]
    public function every_screen_and_field_is_explained(): void
    {
        $this->assertScreensHaveHelp();
    }
}
```

It fails on:

- a screen with no title or `# about` in `en`;
- a field, a row of a repeater included, with no description in `en`;
- a description of an `id` the screen has on no site: a field that a setting of the site takes away
  stays described, and the panel simply does not show the text where the field is off;
- one `id` described by two packages;
- `ru` or `uk` without the file, or with other fields, `guide` or `include`;
- a description that repeats the hint;
- a table, a picture, HTML or a heading in a text;
- a `guide` that is not in `apps/docs/guide/`.

Two more tests look at everything at once. `scripts/help-coverage.test.mjs` reads the built npm
packages and fails on a route with no `meta.help` and on a key with no file.
`HelpPackagesCoverageTest` fails on a package with something to explain that has no
`HelpCoverageTest` or never calls `directory()`.

## The list of what is still to write

`php/packages/module-admin/tests/help-pending.json` holds what has no text yet, with why. It exists
while the panel is being filled, and it only gets shorter:

```json
{
  "catalog.product-form": "filling, wave A",
  "catalog.product-form#stock-status": "filling, wave A"
}
```

- Strike a key out in the commit that brings its text. A key on the list with a file is red too.
- A new screen does not go on the list: it gets its help in the same commit.
- `<key>#<id>` is one field. It is for the moment a form is explained and a satellite that patches
  it is not yet.

## On a real site

A package's test sees the package and its core. Fields that appear only beside a neighbour, and the
ones a project patches in, exist only on a site:

```sh
php artisan webx:help --check
```

It walks the screens of this site and prints what is not explained, one line each, and exits with
1 if there is anything. The site itself is not affected: a field without a description is shown
with its label and hint. Without `--check` the command lists the keys.

A project describes a field it added the same way, from `resources/help` and its own provider:

```php
$this->app->make(HelpRegistry::class)->directory(resource_path('help'));
```

The provider of the project boots last, so what it describes wins.
