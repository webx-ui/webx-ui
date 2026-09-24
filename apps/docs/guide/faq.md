# FAQ

`@webx-ui/module-faq` is questions and answers as two sections of the panel, and
`webx-ui/module-faq` on the server is what they edit. This page is both, because neither is useful
alone.

A question has **no page of its own**, and the module has **no public route**. A question reaches
the site inside a block: «every question, with a filter» on a page at `/faq`, «the payment
questions» at the bottom of a service. The block is the first user of
[collections](/guide/collections) — the contract any module uses to put its records into a block —
and the categories are the panel's shared [categories](/guide/categories), without addresses.

## Install

```bash
pnpm add @webx-ui/module-faq
composer require webx-ui/module-faq
php artisan migrate
php artisan webx:blocks:offered --install --module=faq
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { faq } from '@webx-ui/module-faq'
import '@webx-ui/module-faq/style.css'

createAdmin({
  modules: [...faq()],
})
```

`faq()` returns two modules — **Questions** and **Categories** — which arrive under one heading
because the server puts both in the `faq` group. `faq({ path: '/help' })` moves them inside the
panel.

The last command installs the block type the module **offers** — **FAQ**, slug `faq` — and
publishes it. Block types live in the database and are the site's to rewrite, so the module never
puts it there by itself, and a type the site already has under that slug is never touched.
`webx:setup` runs the command for a new site; on a site that already has a panel it is the one line
to run by hand. [Offered block types](/guide/collections#the-block-types-a-module-offers) explains
the mechanism.

Permissions: `faq.view` opens the list, `faq.manage` writes (the order too), and
`faq.categories.manage` covers the categories.

`webx-ui/module-seo` prints the markup and `webx-ui/module-pages` gives the block a page to stand
on. Both are suggested rather than required: without the first there is no `FAQPage`, without the
second the block goes wherever else the site has blocks.

## The FAQ page is a page with a block

There is no «FAQ index» to switch on. The site's FAQ page is an **ordinary page** of
[`module-pages`](/guide/pages) with the FAQ block on it, set to every category with the filter:

1. **Pages** → a new page «FAQ» with the slug `faq`;
2. **Content** → add the block **FAQ**; leave **Categories** empty (that is «All categories») and turn
   **A filter by category above the list** on;
3. publish.

Everything a page has comes from the page: the address and its translations, the SEO card, the
menu entry, the sitemap line, the breadcrumbs. The module has no route that could collide with an
address of the site, and a site that does not want a FAQ page simply does not make one.

The same block with categories chosen is «the questions about X» anywhere else — a service, an
article, the home page. One chosen category shows that category's own order; none or several show
the order of the whole list, each question once.

**A question is shown in a language when it has both the question and the answer in it.** No
other language stands in: a FAQ with half its answers in another language is worse than a short
one. The list in the panel says where each question is seen, and marks the published one that is
seen nowhere.

**Links to a question** are `/faq#paying-by-card`. The anchor is made once, when the question is
created, from the question in the default language, and it never changes — not when the question is
reworded, not when it goes to the bin and back. The editor shows it read-only with «Copy the link»,
which copies the fragment: the address in front of it is whichever page the block stands on. The
block's script opens the question a link points at, on load and when the hash changes.

## The markup and its flag

A FAQ block puts one schema.org `FAQPage` into the page's `<head>` for the questions it shows. Two
blocks on one page give **one** `FAQPage`, and a question they share is in it once — the source
lays the markup under one key with `Seo::put('faq', …)` rather than pushing a block each time.

Whether a block marks up is a flag on the block, **Markup for search engines**, with a default
that depends on the choice:

| The block shows           | Markup by default |
| ------------------------- | ----------------- |
| every category            | on                |
| the categories you picked | off               |

«The payment questions» stand on every service page, and search engines ask not to see the same
question marked up on twenty pages — that belongs to the one page that answers them all. The editor
can turn it either way: the page is theirs. Touched, the flag stays as set; «Back to the default»
under it returns it to `null`, which is «decide by the categories».

`webx-faq.markup` (`WEBX_FAQ_MARKUP=false`) takes the flag away from every block — for a site whose
own layout prints the markup, or that wants none.

::: warning The layout prints the head last
The markup works because the content of a page is rendered before the layout that prints
`@webxSeo`: the blocks have gathered their questions by the time the `<head>` is written. That is
how `module-pages`, `module-blog` and `module-services` render. A site whose own layout prints the
`<head>` before rendering the blocks gets no `FAQPage` — nothing breaks, the markup is simply not
there. Check with the page source, not with the block.
:::

## Fields of the project

A clinic wants «Specialist who answers» on a question, a shop wants «Applies to product». Neither is
a column of the package: the site lays a patch over the screen, and whatever the screen draws that
the model has no column for is kept in `extra`.

Both screens — `faq.form` for a question, `faq.category-form` for a category — keep an empty card
with the public id `project-fields`. The renderer does not draw it until a patch puts something in.

`resources/screens/faq.form.json` in the site:

```json
[
  {
    "op": "add",
    "target": "project-fields",
    "node": {
      "id": "specialist",
      "type": "wx-input",
      "name": "specialist",
      "label": "Who answers",
      "localized": true
    }
  }
]
```

and in a service provider of the site:

```php
use WebxUi\Admin\Facades\Screens;

public function boot(): void
{
    Screens::extend('faq.form', resource_path('screens/faq.form.json'));
}
```

The field appears in the editor of a question, is checked by its type on every save — the panel's
and an agent's — and is kept in `faq_questions.extra`. A question has no draft, so it is on the site
the moment it is saved. In the block's template, or anywhere else:

```php
$question->extra('specialist'); // in the language of the page, because the field is localized
```

A site that wants it printed by the block rewrites the block's template in the panel — the type is
the site's once installed.

## The panel

**Questions** is a list and an editor side by side (`WxListDetail`): the whole FAQ on one screen,
without pages. Filters: a category and words. Without a category the drag writes the order of the
whole list; with one, only that category's order, and every other category keeps its own. While a
search narrows the list there are no grips. **New question** is a row of the list that opens an
empty form; the question is created by its first save. On a phone the editor slides over the list
and draws its own «Back».

The editor is the screen `faq.form`: the question, the answer (rich text — library pictures are
kept as keys and pointed at where they live on every read), the categories, **Published**, the
anchor and the project's card. Save with the button or `Ctrl+S`; leaving with unsaved changes asks
first.

**Categories** is the shared category list and page (`faq.category-form`): the name, whether it is
shown, the project's card. No address, no SEO, no blocks — a category is a button in a filter and a
way to pick questions. A category with questions in it refuses to go into the bin and says how
many. A hidden category is still pickable in a block; it just never becomes a button.

```
GET    /api/cms/faq/questions              category, trashed, search — no pages
POST   /api/cms/faq/questions              { values } — created from the form
GET    /api/cms/faq/questions/{id}         { question, values }
PUT    /api/cms/faq/questions/{id}         { values } — 422 under the field's name
DELETE /api/cms/faq/questions/{id}         to the bin · POST /restore
POST   /api/cms/faq/questions/reorder      { ids, category? }

GET    /api/cms/faq/categories             the shared categories API
```

## For an agent: MCP

With the panel's MCP server on (see [AI agents](/guide/agents)), both sections are tools too:

| Tool                  | What it does                                                                |
| --------------------- | --------------------------------------------------------------------------- |
| `faq_list`            | Every question, or a category in its order, or words — or the bin           |
| `faq_get`             | One question in full: every language, the categories, the project's fields  |
| `faq_create`          | A question at the end of the list; unpublished unless asked                 |
| `faq_update`          | The values — on the site at once, a question has no draft                   |
| `faq_delete`          | To the bin, keeping its anchor                                              |
| `faq_reorder`         | The whole list's order, or one category's with `category`                   |
| `faq_categories_list` | The categories in their order, with how many questions each holds           |
| `faq_categories_*`    | `create`, `update`, `delete`, `reorder` — behind the categories' permission |

A question is named by its id or its anchor (`"how-do-i-pay"`), a category by its id or its title.
Every tool that changes something takes `dry_run: true`. The values go through the same form as the
panel's: a project's field is written under its own name and refused where the panel would refuse
it, and `faq_create` writes the question, its categories and those fields in one transaction — a
refusal leaves nothing behind. A category id that does not exist is refused, not quietly dropped.

Every row says `visible_in` — the languages a reader sees the question in — beside `published`, so
an agent can tell «published but written in one language» from «on the site».

Before writing, an agent reads **`faq://catalog`**: every category in its order, hidden ones too,
with its questions in that category's order, unpublished ones included and marked, and the
questions in no category at the end. It is there so that an agent asked for «a question about
payment» finds the one that is already there.

Putting a FAQ block on a page is not a FAQ tool: it is `blocks_edit_content` on the page, with a
`faq` block whose `questions` value is `{ "categories": [3], "limit": null, "filter": false,
"markup": null }`.

## Demo content

`php artisan webx:demo` seeds three categories and ten questions in the two languages of the demo,
as far as the site has them. One question is in two categories and stands in a different place in
each; one is not published; one is written in English only, so the second language does not show
it. The offered block type is installed if the site has not taken it yet, and then the block goes
where a site would put it:

- with `module-pages` — a page `/faq` with every category and the filter, and so with `FAQPage`;
- with `module-services` — «Questions about payment» at the end of one demo service, with the
  markup left to the default, which is off there.

`--remove` takes all of it back out, the block type too when the demo installed it. A FAQ that
already has anything in it is left alone.

## Config

`config/webx-faq.php`:

| Key      | Default | What it is                                                 |
| -------- | ------- | ---------------------------------------------------------- |
| `markup` | `true`  | `FAQPage` at all; off takes the flag away from every block |
