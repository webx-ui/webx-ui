# Relations

A record of one section often points at records of another: a recipe at the services it is made
for, at the recipes that go with it; later a review at the service it is about. That is a
**relation**. It is not a category — a category has no life outside its module — and not a link
(`wx-link` is one address to go to, not data a template reads).

Relations live in one shared table of `module-admin`, not in a table per pair of modules: the
target is somebody else's module, and it may not be installed. Installing it later needs no
migration, removing it leaves the rows where they were, and putting it back brings them back.

This page is for somebody adding a relation to a module's screen. The full design, the server half
included, is in `docs/architecture/WEBX_UI_MODULE_RECIPES.md` §3.

## The field

A screen asks for a relation with one node of type `wx-relations`:

```json
{
  "id": "services",
  "name": "services",
  "type": "wx-relations",
  "label": "Services",
  "props": { "target": "service", "max": null }
}
```

- `target` is the key the other module registered its records under — the same name its addresses
  carry in `routing` (`service`, `recipe`).
- `name` is the **role** of the relation: the field `services` writes the role `services`. Two
  fields may point at the same target under different roles.
- `max` limits how many can be chosen; `null` is any number.

The value is a list of ids **in order** — the order the editor dragged them into, because a
template reads it ("the main service first"):

```json
[7, 3]
```

If the target is not registered — the services module is not installed — the server takes the
node off the screen before the panel sees it. The recipe form simply has no "Services" field; the
value stays in the database, untouched.

## What the editor sees

The chosen records are rows: a picture when the target has one, the name, a line under it, a grip
to drag by (or to move with the keyboard: space, arrows, space), and a button to take the row out.
Under the list, a search box adds more; it asks the server as the editor types and never offers
what is already chosen.

A chosen record the site does not show — a draft, one in the bin — stays in the list with the
mark **"Not on the site"**: removing it silently would change the record nobody touched, and the
site skips it anyway. One the server no longer knows at all is drawn by number, marked
**"Not found"**.

An administrator who may not see the target's records (its permission, `403`) gets the list of what
is chosen and nothing else: no search, no removing, no dragging.

"Similar recipes" on a recipe is a relation of a recipe to recipes, and the search must not offer
the recipe itself. A described screen cannot say which record it edits, so the editor that hosts it
does:

```ts
import { provideRelationOwner } from '@webx-ui/module-admin'

provideRelationOwner({ type: 'recipe', id: computed(() => recipe.value?.id ?? null) })
```

## What the field asks

Both questions go to one address, answered by the target's registration on the server:

```
GET /api/cms/relations/{target}?q=lentil      → search, for the box
GET /api/cms/relations/{target}?ids[]=7&ids[]=3 → names of what the form opened with
```

```json
{
  "data": [
    { "id": 7, "title": "Landing page", "subtitle": "Websites", "thumb": "…", "visible": true }
  ]
}
```

Everything is already in the panel's language. A name arrives with the search as well, so a record
picked from the box is never asked about again.

## In a block: "only related to"

A block that shows records of another section (see [Collections](./collections)) can narrow them to
the ones related to something: "the recipes of this service" on the page of a service. The source
says which targets its records can be related to (`CollectionSource::relations()`); the
`wx-collection` field then draws one more choice under the categories — the target, when there is
more than one, and the records of it:

```json
{
  "categories": [],
  "related": { "type": "service", "ids": [7] },
  "limit": 3,
  "filter": false,
  "markup": null
}
```

Nothing chosen is written as `related: null`. A narrowed list is a part of the collection, so the
default markup is off for it, as for a chosen category. A source without relations shows no such
choice at all.
