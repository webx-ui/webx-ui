# @webx-ui/module-reviews

The front end of the reviews section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: reviews — a photo, a name, a job title, the stars and a text — and the categories they are
filed under.

The other half is the Composer package `webx-ui/module-reviews`, which owns the reviews, their
order, the Reviews block, `reviews()` for templates and the API. A section appears in the panel
when both halves are installed.

## Install

```bash
npm install @webx-ui/module-reviews
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { reviews } from '@webx-ui/module-reviews'
import '@webx-ui/module-reviews/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...reviews()],
}).mount()
```

`reviews()` answers with two sections — the reviews and their categories — because the panel draws
one entry per module. The server puts both in the `reviews` group. `reviews({ path: '/testimonials' })`
puts them somewhere else inside the panel.

On the server, the block type the module offers is installed once:

```bash
php artisan webx:blocks:offered --install --module=reviews
```

## Reviews

A list and the form of one review side by side, without pages: the list is where reviews are put
in order, and a drag cannot cross a page boundary. The open review is in the address
(`?review=12`), so a link to it is a link to the list around it too. **New review** is a row that
opens an empty form; the review is created by its first save. On a phone the form slides over the
list and draws its own «Back».

A row shows the photo (or the initials), the name, the stars and the languages a reader sees the
review in; a published review whose text is written in no language is marked — it is seen nowhere.

The list has two orders, and the filter decides which one you are dragging:

- **no category chosen** — the order of the whole list;
- **a category chosen** — the order inside that category only. Every other category keeps its own.

While a search narrows the list, the grips disappear.

The form is the `reviews.form` screen, described in JSON on the server: the photo from the library,
the name, the job title and the text in every language of the site, the stars, the date, a link to
the person's profile, the categories, **Published** and a card for the project's own fields. Save
with the button or `Ctrl+S`; leaving with unsaved changes asks first. A review has no draft: a save
is on the site.

## Categories

The panel's shared category screens with this module's words (`reviewCategoriesOptions()`),
without addresses: a category is a button in a block's filter and a way to pick reviews for a
block.

## On the site

The module has no page of its own. Reviews reach the site in the **Reviews** block — one, a grid, a
slider or a marquee — or through `reviews()` in a template of the site. See the
[guide](https://webx-ui.github.io/webx-ui/guide/reviews.html).

## Licence

MIT
