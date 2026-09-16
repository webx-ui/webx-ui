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
in snake_case. `label` is what the editor sees; `props` are the component's props (`placeholder`,
`options` for a select, `rows` for a textarea). Fields may sit inside `wx-card`, `wx-tabs` or
`wx-row` nodes for layout; the values stay flat.

A `wx-blocks` node makes the type a container: its value is a list of nested blocks, and
`props.allow` says which types may go inside. Print it with `@blocks('content')`. Keep the
schema small — a block with fifteen fields is two blocks.

## Template

Blade, with the schema's fields as variables: `{{ $title }}`, `@if ($subtitle)`, `@foreach
($items as $item)`. Also available: `$block` (`key`, `type`, `version`, `depth`,
`value('name', default)`) and `$entity`, the page or article the block stands on. Nothing else:
no facades that reach for the database, no `@php` that does work a controller should.

- The root element carries `data-wx-block="{slug}"` — the runtime finds the block by it, and
  the panel highlights it by it. One root element per block.
- Escape by default (`{{ }}`); `{!! !!}` only for a field that is rich text on purpose.
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
of this type on the page. `el` is the root, `values` the block's values. Anything the site's own
bundle shares is reached with `const Swiper = await webx.use('swiper')`; `blocks://site` lists
what this site provides. Do not load libraries from a CDN inside a block. Leave the field empty
when the block needs no behaviour.

## Sample

Fill every field with a believable value in the site's language — it is the thumbnail an editor
picks the block by, the values the publish check runs on, and the clearest documentation of the
data shape. For a container, `sample` may include nested blocks.

## The loop

1. Create the type as a draft with `blocks_create`.
2. Render it with `blocks_render` on the sample and on `{}`; read the warnings; fix; repeat with
   `blocks_update`, which writes a new version each time — that history is the audit log.
3. Report the slug, the fields and anything you were unsure about. Publishing is a separate
   step (`blocks_publish`) that runs the checks on every page the block already stands on;
   leave it to a person unless you were asked to publish.
