# Writing a block type

A block type is a piece of a page an editor can add, fill in and move: a hero, a text column,
a gallery, a section that holds other blocks. It is made of five things, all of them yours to
write through `blocks_create` and `blocks_update`:

| Field      | What it is                                                                 |
| ---------- | -------------------------------------------------------------------------- |
| `schema`   | The fields an editor fills in — screen nodes, see `blocks://fields`        |
| `template` | Blade that prints the block from those fields                              |
| `styles`   | CSS for this block alone                                                   |
| `script`   | Optional: the body of an initialiser run once per block on the page        |
| `sample`   | Values for every field, used for the thumbnail, the checks and the preview |

Before making a new type read `blocks://catalog`: if a type already does the job, use it or
extend it. Five hero blocks that differ in a margin are the failure mode to avoid.

## Schema

A list of screen nodes: `{ "id": "title", "type": "wx-input", "label": "Title" }`. The `id` is
the variable the template gets and the key in `sample`, so it must be a valid PHP variable name
in snake_case — an id with a space or a dot is refused, and a `type` the site does not know comes
back as the warning `unknown-field-type` (the panel draws a warning in its place and nothing
checks its values). `label` is what the editor sees; `props` are the component's props (`placeholder`,
`options` for a select, `rows` for a textarea). Fields may sit inside `wx-card`, `wx-tabs` or
`wx-row` nodes for layout; the values stay flat.

A `wx-blocks` node makes the type a container: its value is a list of nested blocks,
`props.allow` says which types may go inside and `props.max` how many. A field without
`props.allow` takes the type's own `allow`; with neither, any type. Print it with
`@blocks('content')`. Keep the schema small — a block with fifteen fields is two blocks.

The schema's rules are the server's too: `min`/`max` of a number, the `options` of a select, the
`max` of a rating, a checkbox group or a repeater, a date, a colour, a link (`http(s)`, `mailto`,
`tel`, a relative path or an `#anchor` — never `javascript:`), a library file for `wx-media`. A
value that breaks one is refused on every write — `blocks_set_content`, `blocks_edit_content`,
the panel's save, a publication — with the block's key and the field named.

`localized: true` on a field keeps one value per language. Switching it on a type pages already
use is announced by `blocks_update` (`localized-changes`) and done on `blocks_publish`: plain
values become the main language's, and taking it off keeps the main language — refused while
other languages hold text, unless you pass `drop_translations: true`.

## Template

Blade, with the schema's fields as variables: `{{ $title }}`, `@if ($subtitle)`, `@foreach
($items as $item)`. Also available: `$block` (`key`, `type`, `version`, `depth`,
`value('name', default)`) and `$entity`, the page or article the block stands on. Nothing else:
no facades that reach for the database, no `@php` that does work a controller should.

- The root element carries `data-wx-block="{slug}"` — the exact slug, letter for letter: the
  runtime finds the block by it, and the panel highlights it by it. Another word there is refused
  on publishing. One root element per block.
- Escape by default (`{{ }}`); `{!! !!}` only for a field that is rich text on purpose.
- A text field may hold shortcodes (`[phone]`, `[dot]` — `blocks://shortcodes`); they arrive
  resolved. `{{ $title }}` prints them as HTML with the text around them escaped, and needs
  nothing more. In an attribute (`alt`, `title`, `aria-label`) print `@shortcodesPlain($title)`.
- Every field may be empty. A template that throws on an empty value is refused at publish
  time; render it on empty values before you are done.
- A `wx-media` field (when the media module is installed) holds the file the editor picked as
  `{ path, alt, title }` — the path on the media disk and this block's own words for the
  picture. The template gets `url` alongside them, worked out by the media module when the
  block is printed: `<img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}">`. Take `alt`
  from the value rather than inventing one, and do not build an address from `path` yourself —
  a library that moves to another disk changes `url` and nothing else.
- Several pictures are a `wx-gallery` field, not a `wx-repeater` holding a `wx-media` — the
  editor picks them all in one trip and drags them into order. `wx-files` is the same for
  documents. Each item carries the keys `wx-media` does, plus `thumb`, `name`, `extension`,
  `mime`, `size`, `width` and `height`: print `width` and `height` on an `<img>` so the page
  does not jump, and label a download with `name` and `size`. An item whose file has been
  deleted has `url` of `null`, so guard it rather than assuming it is there.
- Links go through the site's addresses, not hard-coded paths.

## Content

When you write the content of a page rather than a type, read `blocks://shortcodes`: the
phone, the e-mail and whatever else the site keeps in one place is a shortcode — write `[phone]`,
never the number — and a shortcode already in a text (`[dot]` above all) stays as it is.
`blocks_render` shows them resolved.

## Styles

- Every selector starts with the block's class, `.b-{slug}`, and names its parts in BEM:
  `.b-hero`, `.b-hero__title`, `.b-hero--wide`. A bare element selector (`h2`, `a`, `img`)
  or a class outside the prefix leaks into the rest of the site and is reported as a warning.
- Width decisions are container queries, not media queries: the block does not know whether it
  is the whole page or a third of it. Put `container-type: inline-size` on the root and use
  `@container (min-width: 40rem)`.
- Colours, spacing and type come from the site's custom properties where it has them (a WebX
  site exposes `--wx-*` tokens); a literal colour is a last resort.
- No resets, no global rules, no `!important`.

## Script

The `script` field is the body of `async (el, values) => { … }`, run once for each root element
of this type on the page. `el` is the root, `values` whatever the root carries in `data-wx-values` —
`{}` unless the template prints it: `data-wx-values="{{ json_encode(['speed' => $speed]) }}"`.
Print only what the script needs; everything there is in the page's markup. Anything the site's own
bundle shares is reached with `const Swiper = await webx.use('swiper')`; `blocks://site` lists
what this site provides. Do not load libraries from a CDN inside a block. Leave the field empty
when the block needs no behaviour.

## Sample

Fill every field with a believable value in the site's language — it is the thumbnail an editor
picks the block by, the values the publish check runs on, and the clearest documentation of the
data shape. For a container, `sample` may include nested blocks.

## Components

A type with `kind: "component"` is not added to pages by editors: templates call it by tag, the
way a partial is included. Make one for a piece of markup that repeats **inside** other
templates — a card in a list, a badge, a price line — so that changing it once changes it
everywhere.

```blade
<x-webx-block type="recipe-card" :card="$card" />
<x-webx-block type="section" :title="$title">
    …body, printed as {{ $slot }}…
    <x-slot:aside>…</x-slot:aside>
</x-webx-block>
```

- The schema is the component's input. A `wx-data` node is a value the caller passes from code
  (`:card="$card"`); its `props.shape` names a form a module registered, and `blocks_get` lists
  its keys under `shape`. A `wx-slot` node is a named slot. Any other field works too, as an
  attribute: `tone="dark"`.
- `type` must be a literal. `:type="$x"` works, but nothing then knows who calls what, and the
  checks below cannot protect the caller.
- A change to a component reaches every type that calls it. Before you change one, read its
  `used_by`; publishing is refused when the new version breaks one of them on its sample or on
  a page it stands on, and the refusal names which.
- Modules declare places they call a component from (`declared` in `blocks_list`), such as
  `recipe-card`. Until the site customises one, the module's own view prints there.
  `blocks_create` with that slug and no template starts the component from that view, with the
  module's input and a real sample; publishing it switches the site over. `blocks_delete` on it
  brings the module's look back — ask a person first: it deletes the type with its history.
- Calls from the site's own view files are invisible to all of this. A component such a view
  calls should be given a `fallback` there.

## Regions

A region is a named place of the site's layout — the header, the footer — whose content is a
tree of blocks, edited like a page's: `blocks_get_content` / `blocks_edit_content` with
`entity: "region"` and the region's name as `id`, then `blocks_region_publish`. It is not a
component: a component is code called from templates, a region is one place an editor fills.
`blocks_regions` lists them, with the view the layout prints while a region is empty or
unpublished (`fallback`).

- A type meant only for a region says so: `allowed_in: ["region:header"]`. The region's own
  `allow` and `max` limit its top level.
- A block in a region sees `$entity` — the entity of the page the visitor is on, or null — and
  `$region`: `$region->name`, `$region->data('compact')` (the tag's attributes),
  `$region->path()`. Outside a region, and on the sample, `$region` is null: write
  `$region?->data('compact')`.
- The HTML of a region is never cached, so a template may call `menu('header')`, `settings()`,
  `auth()` and `@csrf` and be right on every page.
- On the site a region in which any block throws prints its fallback entirely. Render every type
  you put there with `blocks_render` first; `blocks_region_publish` refuses a draft that fails.

## History, usage, renames

- `blocks_versions` lists a type's versions (source, author, comment, which is the draft and which
  is published); `blocks_get` with `version` reads one; `blocks_version_restore` makes one the
  draft again. Pages and services have the same pair: `pages_versions` /
  `pages_version_restore`, `services_versions` / `services_version_restore` — the way a bad
  content edit is undone. Regions: `blocks_region_versions` / `blocks_region_restore`,
  `blocks_region_discard` for the draft.
- `blocks_usage` says where a type stands (entity and id as `blocks_get_content` takes them,
  title, address, live or draft) and which types call it. `blocks_delete` is refused while it
  stands anywhere or is called.
- `blocks_update` with `rename_to` gives a type a new slug and rewrites the pages, regions and
  `allow` lists that name it, and its own `data-wx-block` and `.b-{slug}` prefix. Refused while
  another template calls it by tag. `blocks_update` answers with a short summary; `full: true`
  for the whole type. `content` sent beside `rename_to` is carried to the new slug.
- The type remembers its former slugs: restoring a version of a page, a service or a region from
  before a rename brings its blocks back under the new slug (the dry run lists them under
  `blocks.renamed`). A block of a type that exists under no slug is refused on publishing —
  `pages_publish` / `services_publish` with `dry_run` list it under `refused`.
- `blocks_list` is one short row per type; `full: true` adds every setting and the fields, and
  `blocks_get` reads one type whole.

## The loop

1. Create the type as a draft with `blocks_create`.
2. Render it with `blocks_render` on the sample and on `{}`; read the warnings; fix; repeat with
   `blocks_update`, which writes a new version each time — that history is the audit log.
3. Report the slug, the fields and anything you were unsure about. Publishing is a separate
   step (`blocks_publish`) that runs the checks on every page the block already stands on;
   leave it to a person unless you were asked to publish.
