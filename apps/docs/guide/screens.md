<script setup>
import ScreensDemo from '../components/demos/ScreensDemo.vue'
import RichTextFieldDemo from '../components/demos/RichTextFieldDemo.vue'
</script>

# Screens

A panel screen written as a template cannot be changed from outside: a slot lets you put
something into a place that was foreseen, but it does not let you drop a card, reorder the tabs
or swap a field for another kind. Projects do exactly that. So a screen is not written — it is
**described**: a tree of nodes in JSON, every node with a stable `id`. The module ships the tree,
the project lays a **patch** over it, and `@webx-ui/schema` draws the result with the core
components.

Try it: the left editor is what a settings module would ship, the right one is what a project
adds on top. Both are live.

<ScreensDemo />

Two things to notice. The tree carries a `wx-media` node — a type this page's registry does not
know, because `module-media` registers it when it is installed — and the patch removes it. Delete
that first operation and a loud placeholder appears where the picker would be: a type nobody
registered is an error you can see, not an empty space. And the "Show the office on a map"
switch controls whether the address field below it renders at all.

## A node

```json
{
  "id": "robots",
  "type": "wx-textarea",
  "name": "seo.robots-txt",
  "label": "trans::webx-settings::screen.robots",
  "help": "trans::webx-settings::screen.robots-help",
  "localized": false,
  "props": { "rows": 8 },
  "children": [],
  "slot": null,
  "visible": true,
  "can": null
}
```

| Key         | Required   | Meaning                                                                                                                  |
| ----------- | ---------- | ------------------------------------------------------------------------------------------------------------------------ |
| `id`        | yes        | Unique within the screen. It is the address patches use, and a public contract: renaming one is a breaking change.       |
| `type`      | yes        | A key in the registry.                                                                                                   |
| `name`      | for fields | Key in the model. A **literal string** — the dots in `seo.robots-txt` are part of the key, not a path.                   |
| `label`     | no         | Text. For a field it goes on the form item; for a card it is the title; see the table below for the rest.                |
| `help`      | no         | Hint under a field.                                                                                                      |
| `localized` | no         | The field is edited per content language; the value is a record keyed by locale. Needs `provideLocales` above.           |
| `props`     | no         | Passed to the component as they are. The renderer does not interpret them.                                               |
| `children`  | no         | Nested nodes.                                                                                                            |
| `slot`      | no         | A named slot of the parent to land in — `sidebar` on a card, `extra` on a header. Without it, the parent's default slot. |
| `visible`   | no         | `true`, `false`, or a condition on the model. A hidden node is not rendered and its value is not touched.                |
| `can`       | no         | A permission such as `settings.manage`. Without it the node is not rendered.                                             |

There are no other keys. The set is closed on purpose — `validateScreen` and the JSON schema
both reject `childrens` — so a typo is an error, not a silently empty tab.

**Translatable strings.** Any string in a node that starts with `trans::` is a dictionary key:
`trans::<namespace>::<key>`. The marker works in `label`, `help` and anywhere inside `props`,
`options[].label` included. The renderer hands `<namespace>::<key>` to its `translate` function;
inside a panel that is the panel's dictionary, on this page it is a six-line object. Without a
dictionary the key itself shows, which is the honest thing to do. A string without the marker is
shown as it is — a project that publishes in one language writes «Телефон» straight into the patch.

## A patch

An ordered list of operations. Targets are bare ids: an id is unique in the screen, and that is
enough.

| Operation | Fields                       | What it does                                                                                                     |
| --------- | ---------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| `add`     | `target`, `node`, `position` | Inserts `node` into the children of `target`. `position`: `first`, `last` (default), `before:<id>`, `after:<id>` |
| `remove`  | `target`                     | Drops the node and everything under it.                                                                          |
| `replace` | `target`, `node`             | Swaps the node for another one. The new id may differ.                                                           |
| `move`    | `target`, `position`, `to?`  | Reorders among siblings, or moves into another parent `to`.                                                      |
| `set`     | `target` + node keys         | Changes keys shallowly: `props` are merged key by key, everything else is replaced. `id` cannot be set.          |

An operation that cannot be applied — its `target` does not exist, its anchor is missing, the
node it adds carries an id that is already taken — is skipped and reported, and the operations
after it still run. `applyPatch` returns the errors; `WxScreenRenderer` logs them and emits
`patch-error`. A project patch that survived a rename in the module must not silence the rest.

`applyPatch` never mutates its input: it returns a new tree.

```ts
import { applyPatch } from '@webx-ui/schema'

const { root, errors } = applyPatch(screen.root, [
  { op: 'remove', target: 'project-logo' },
  { op: 'set', target: 'robots', props: { rows: 16 } },
])
```

## Rendering

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { WxScreenRenderer } from '@webx-ui/schema'

const values = ref({})
const errors = ref({})
</script>

<template>
  <wx-card>
    <template #extra>
      <wx-button type="primary" @click="save">Save</wx-button>
    </template>
    <wx-screen-renderer :root="screen.root" :patch="patch" v-model="values" :errors="errors" />
  </wx-card>
</template>
```

The renderer is a pure function of the tree and the model. It does not fetch anything and does
not know how to save: the page that opens it loads the values, passes them in, and on Save sends
them back and drops a 422 into `errors` — which land under the fields they name, exactly as they
do in a `WxForm`. The buttons are the page's, not the screen's: there is no `action` node type.

| Prop            | Type                              | Description                                                                  |
| --------------- | --------------------------------- | ---------------------------------------------------------------------------- |
| `root`          | `ScreenNode[]`                    | The tree, as the module ships it or as the server hands it back              |
| `patch`         | `Patch`                           | Client-side operations applied on top                                        |
| `modelValue`    | `Record<string, unknown>`         | Values keyed by node `name`; `v-model` receives a new object on every change |
| `errors`        | `Record<string, string[]>`        | Server validation errors by field name                                       |
| `types`         | `TypeRegistry`                    | Project types, merged over the core ones                                     |
| `translate`     | `(key: string) => string`         | Resolves `trans::` strings                                                   |
| `can`           | `(permission: string) => boolean` | Decides `can`; everything is allowed without it                              |
| `disabled`      | `boolean`                         | Disables every field                                                         |
| `labelPosition` | `'top' \| 'left'`                 | Passed to the form                                                           |
| `labelWidth`    | `string`                          | Passed to the form                                                           |
| `size`          | `'sm' \| 'md' \| 'lg'`            | Passed to the form                                                           |

**Events:** `update:modelValue`, `patch-error` (`PatchError[]`). **Exposed:** `tree` — the tree
after the patch, what is actually on screen.

Inside a panel you will not use the renderer directly: `WxScreen` from `@webx-ui/module-admin`
fetches the screen by name, lays the project's patch from `createAdmin({ screens })` over it,
and passes the panel's dictionary, registry and permissions — `<wx-screen name="settings.index"
v-model="values" :errors="errors" />`. [Settings](/guide/settings) is the first page built on it.

## The registry

A type is the full component name — `wx-input`, `wx-card` — so the name says it is the UI kit.
The renderer treats a type by its `kind`:

- **layout** — a container. Children go into its slots; it never touches the model.
- **field** — bound to the model by `name`, wrapped in a `wx-form-item` with the `label` and
  `help`, shows the 422 message for its own name. With `localized` it gets the language selector.
- **display** — draws and edits nothing.

The core types, generated from the registry (a test fails when this table is stale):

<!-- types:start -->

| Type                   | Kind    | Component           | `label` goes to |
| ---------------------- | ------- | ------------------- | --------------- |
| `wx-tabs`              | layout  | `WxScreenTabs`      | —               |
| `wx-tab`               | layout  | `WxTab`             | prop `label`    |
| `wx-card`              | layout  | `WxCard`            | prop `title`    |
| `wx-row`               | layout  | `WxRow`             | —               |
| `wx-col`               | layout  | `WxCol`             | —               |
| `wx-divider`           | layout  | `WxDivider`         | prop `label`    |
| `wx-input`             | field   | `WxInput`           | form item       |
| `wx-textarea`          | field   | `WxTextarea`        | form item       |
| `wx-input-number`      | field   | `WxInputNumber`     | form item       |
| `wx-select`            | field   | `WxSelect`          | form item       |
| `wx-switch`            | field   | `WxSwitch`          | form item       |
| `wx-checkbox`          | field   | `WxCheckbox`        | form item       |
| `wx-radio-group`       | field   | `WxRadioGroup`      | form item       |
| `wx-date-picker`       | field   | `WxDatePicker`      | form item       |
| `wx-color-picker`      | field   | `WxColorPicker`     | form item       |
| `wx-checkbox-group`    | field   | `WxCheckboxGroup`   | form item       |
| `wx-segmented`         | field   | `WxSegmented`       | form item       |
| `wx-slider`            | field   | `WxSlider`          | form item       |
| `wx-rate`              | field   | `WxRate`            | form item       |
| `wx-time-picker`       | field   | `WxTimePicker`      | form item       |
| `wx-date-time-picker`  | field   | `WxDateTimePicker`  | form item       |
| `wx-date-range-picker` | field   | `WxDateRangePicker` | form item       |
| `wx-tags-input`        | field   | `WxTagsInput`       | form item       |
| `wx-autocomplete`      | field   | `WxAutocomplete`    | form item       |
| `wx-icon-picker`       | field   | `WxIconPicker`      | form item       |
| `wx-cascader`          | field   | `WxCascader`        | form item       |
| `wx-tree-select`       | field   | `WxTreeSelect`      | form item       |
| `wx-transfer`          | field   | `WxTransfer`        | form item       |
| `wx-code-editor`       | field   | `WxCodeEditor`      | form item       |
| `wx-repeater`          | field   | `WxScreenRepeater`  | form item       |
| `wx-heading`           | display | `WxHeading`         | default slot    |
| `wx-text`              | display | `WxText`            | default slot    |
| `wx-alert`             | display | `WxAlert`           | prop `title`    |

<!-- types:end -->

What a choice, a number or a date keeps, as `module-admin` checks and stores it on the server. A
list emptied to `[]` and a date cleared to `''` are kept as `null`:

| Type                                          | Value                                                                       |
| --------------------------------------------- | --------------------------------------------------------------------------- |
| `wx-select`, `wx-radio-group`, `wx-segmented` | one of `props.options`                                                      |
| `wx-checkbox-group`                           | a list of `props.options` values; `props.min` / `max` count them            |
| `wx-transfer`                                 | a list of `props.items` values                                              |
| `wx-cascader`                                 | the path of values from the root; the last one alone with `emitPath: false` |
| `wx-tree-select`                              | a key from `props.nodes`; a list of keys with `multiple`                    |
| `wx-slider`                                   | a number, 0–100 unless `props` say; `[from, to]` with `range`               |
| `wx-rate`                                     | a number from 0 to `props.max` (5), halves with `allowHalf`                 |
| `wx-date-picker`                              | `YYYY-MM-DD`                                                                |
| `wx-date-range-picker`                        | `[start, end]`                                                              |
| `wx-time-picker`                              | `HH:mm`, or `HH:mm:ss` with `seconds`                                       |
| `wx-date-time-picker`                         | ISO 8601 with the offset, moved into the application's timezone             |
| `wx-tags-input`                               | a list of strings; only `props.suggestions` with `allowCreate: false`       |
| `wx-autocomplete`, `wx-icon-picker`           | a string                                                                    |
| `wx-code-editor`                              | a string                                                                    |

`wx-date-time-picker` always writes the offset: the registry binds its `valueFormat`, because a
wall clock without a zone is read by the server in its timezone and by the browser in the
reader's, and the same value shows two different hours.

The panel's own frame comes with `module-admin` and is in every screen it draws:

| Type           | From           | Kind     | What it is                             |
| -------------- | -------------- | -------- | -------------------------------------- |
| `wx-list`      | `module-admin` | `layout` | the frame a section's list is drawn in |
| `wx-rich-text` | `module-admin` | `field`  | a document, kept as HTML               |

`wx-list` is one shape for every list in the panel: the section's name on its own line, the one
action the section exists for beside it, the views of the list as tabs under that, and a card
holding nothing but the rows. The search stays inside the table — it narrows the rows, not the
screen. `label` becomes its title; `props` take `views` (`{ value, label, icon? }[]`), `card`,
`padding`, `fill` and `collapseBelow`, and children go inside the card:

```json
{
  "id": "list",
  "type": "wx-list",
  "label": "trans::webx-pages::module.title",
  "props": {
    "views": [
      { "value": "", "label": "All" },
      { "value": "draft", "label": "Drafts" }
    ]
  },
  "children": [{ "id": "rows", "type": "wx-pages-table" }]
}
```

The card has no heading of its own: the tab says which view it is and the line above says which
section, so a second one would be the same words twice. A heading inside a card is for a group of
fields on a form, where there are two or more of them on a screen.

`wx-rich-text` is the editor — [`WxRichText`](/components/rich-text) — as a field of a screen.
The value is an HTML string, it takes `localized`, and everything the node puts in `props`
(`placeholder`, `minHeight`, `tools`) reaches the editor untouched:

```json
{
  "id": "body",
  "type": "wx-rich-text",
  "name": "body",
  "label": "Body",
  "localized": true,
  "props": { "minHeight": "320px", "placeholder": "Write the article…" }
}
```

<RichTextFieldDemo />

It is `module-admin`'s and not the schema package's for two reasons, and both are things the
editor does not know about. The first is language: a component of the design system carries
English defaults and is translated by whoever opens it, so the toolbar's words come from the
panel's own dictionary (`webx-admin::rich-text.*`). The second is pictures. The image button
needs a library, the library is `module-media`, and `module-admin` cannot depend on it — the
dependency runs the other way. So a module offers one:

```ts
export function media(): AdminModule {
  return {
    id: 'media',
    pickImage: async () => {
      const file = await openMediaPicker({ accept: 'image' })

      return file ? { url: file.url, path: file.path } : null
    },
  }
}
```

The panel takes the first module that has one and hands it to every editor on every screen. A
panel with no file manager hands nothing, and the editor then draws no image button — it does
not offer what it cannot do.

Note what the picker answers with: the address **and** the library's key. The key is what the
document keeps.

On the server the value is checked as a string against `props.maxlength`, and stored through an
allowlist: the tags and attributes the editor can produce survive, and a `<script>`, an
`onclick` or a `javascript:` address does not. That is not a courtesy to the editor — the same
field takes a POST that never opened one, and an agent writing through a tool never runs it at
all. An emptied editor leaves `<p></p>` behind, which is stored as `null`, so "did anybody
write anything" stays a check rather than a parse.

That happens on every save that goes through the type: a described screen (`ScreenValues`) and,
since `module-blocks` puts block values through their types as well, a rich text field inside a
block — whether the editor saved it or an agent wrote it through a tool.

### Pictures move; documents do not

`wx-media` keeps the library key and never the address, so a library that moves to another disk
does not rewrite a single page. A rich text field holds its pictures _inside_ a value instead of
being one, and follows the same rule one layer in: the editor writes `data-wx-path` beside every
picture it took from the library, and the type works the address out again on every read.

That is three problems rather than one, and all three are invisible until they bite:

- the same document is deployed against different storage — a CDN on the developer's machine,
  the application's own disk in production;
- a private bucket answers with a **signed** address that expires, so any address written into a
  column stops working within the hour;
- editing an image writes over the same key rather than making a new one, so an address saved
  last week carries a `?v=` stamp for the picture before the crop and a CDN will go on serving
  it forever.

The library is asked once per document, not once per picture, and a key it no longer knows keeps
whatever address it had — a file deleted out from under a page shows one broken picture rather
than refusing to render the page. A picture with no key is somebody else's and is left alone.

The `src` that was stored is kept beside the key as a **cache**, and it is the key that is the
record. It is kept rather than dropped because the panel reads values raw — it edits what is
stored, not what a site would print — and a document with no addresses in it would open in the
editor with a hole where every picture was.

The seam is a contract in `module-admin` that `module-media` binds
(`WebxUiAdminContractsAssetUrls`), for the same reason as `pickImage`: the panel cannot
depend on the module that has the files. A site with no file manager has nothing to ask, and the
document is read exactly as it was written.

A module registers its own the same way — its `types` are merged into every panel screen, as are
`createAdmin({ types })` — so these are here whenever the module is installed on both halves:

| Type         | From            | Value                | The field                         |
| ------------ | --------------- | -------------------- | --------------------------------- |
| `wx-media`   | `module-media`  | `MediaValue \| null` | one picture in a frame            |
| `wx-gallery` | `module-media`  | `MediaValue[]`       | a grid of thumbnails, in order    |
| `wx-file`    | `module-media`  | `MediaValue \| null` | one file card: glyph, name, size  |
| `wx-files`   | `module-media`  | `MediaValue[]`       | those cards a line each, in order |
| `wx-blocks`  | `module-blocks` | a list of blocks     | the constructor                   |

`MediaValue` is `{ path, alt?, title? }` — the key on the media disk and this entity's own words
for it. The address is worked out on read rather than stored, and the same holds for a list; see
[the file manager](/guide/media). `wx-blocks` is the constructor from `module-blocks`: on an
entity's screen it is the tab that builds the content out of blocks, and inside a block's own
schema it makes the type a container — see [Blocks](/guide/blocks).

Which half of a value is stored and which is worked out is the type's to decide, and it decides
both directions: on the server the type says what a read hands over and what a save keeps. A save
puts every value through the type its node names — a colour lowercased, a number that stops being
the string a form sent, pasted markup through an allowlist — and takes the answer whole, `null`
included. A block's fields are these same nodes, so the same holds inside the constructor: the
tree arrives as one value, `module-blocks` walks it and every field of every block goes through
the step its own schema names, whether the save came from the editor or from an agent's tool. Two
things are kept exactly as they came, in both directions: a value whose key the schema does not
name — a field dropped after the page was written — and a value of a type nobody registered, the
nested tree of blocks above all, which is the renderer's to print rather than any field's to keep.

`wx-repeater` is the one type with a nested model: its value is a list of records, and its
children are the fields of one of them, so a `name` inside it is a key of the item rather than a
key of the screen. `WxScreenRepeater` is a thin wrapper — [`WxRepeater`](/components/repeater)
draws the rows, and everything the node puts in `props` (`title`, `itemLabel`, `min`, `max`,
`collapsible`, …) reaches it untouched:

```json
{
  "id": "offices",
  "type": "wx-repeater",
  "name": "contacts.offices",
  "label": "Offices",
  "props": { "itemLabel": "city", "addLabel": "Add an office" },
  "children": [
    { "id": "office-city", "type": "wx-input", "name": "city", "label": "City" },
    { "id": "office-address", "type": "wx-textarea", "name": "address", "label": "Address" }
  ]
}
```

On a screen the rows start **folded** — `collapsed` is on unless the node sets it to `false`:
a list of records opened all at once is a form a kilometre long, and each header already says
which record it is (`#1 · …`, from `itemLabel`). A row added now opens anyway. The words the
core only has in English — add, remove, reorder, the empty text — come from the panel's
dictionary (`webx-admin::screens.repeater.*`); a node's own `addLabel` and the rest win over it.

Fields of an item can stand in columns: a `wx-row` of `wx-col` among the children, as anywhere
else on a screen. A column's `sm`/`md` count the width of the row, not of the window, so a
repeater in a narrow column stacks them and a wide one lays them side by side.

A condition inside a row is read against that row: `{ "when": "hq", "is": true }` on a child asks
about the item being edited, not about the screen. A type of your own can draw its children the
same way — `nested: true` on the entry, and the component is handed `node` and `context` on top of
the model binding, with `WxScreenNodes` to render them.

A project registers its own types the same way, under any name:

```ts
import { defineTypes } from '@webx-ui/schema'

const types = defineTypes({
  map: { component: WxMapField, kind: 'field' },
  'og-preview': { component: OgPreview, kind: 'display' },
})
```

and passes them as `types` — or, inside a panel, as `createAdmin({ types })`. A `TypeEntry` has
`component`, `kind`, an optional `childrenSlot` (where children without a `slot` go), `labelProp`
(where the node's `label` goes — `title` on a card), and `bind`, a function from the node to
extra props: a tab, for instance, takes its `value` from the node's id.

A `field` is wrapped in a `WxFormItem`, which stops its control at the width a field is read at.
An entry whose control is not a field in that sense — an editor, a table, a list of blocks — says
`wide: true`, and the form item lets it take the whole width.

Two things the renderer does without being asked. A container — a card, a tab, a column — whose
children are all hidden, or that was described with `children: []`, is not drawn: a screen can
keep an empty card for a project's fields (`project-fields`) and nobody sees a heading over
nothing. And when the page hands it `errors`, a refusal named by language (`slug.en`) is shown
under the field (`slug`), and the tabs open the first one holding a failing field unless the tab
on screen has one of its own.

## Conditional visibility

"Show this when that field holds this value":

```json
{ "id": "indexing", "type": "wx-switch", "name": "seo.indexing", "label": "Allow indexing" },
{
  "id": "sitemap-url",
  "type": "wx-input",
  "name": "seo.sitemap-url",
  "label": "Sitemap URL",
  "visible": { "when": "seo.indexing", "is": true }
}
```

The forms are `{ when, is }`, `{ when, in: [...] }`, `{ when, not }`, `{ all: [...] }` and
`{ any: [...] }`, and they nest. `when` is a field `name`; the comparison is by value, so
`{ "is": ["a"] }` matches an array field. It is evaluated on the client against the current model
and works on a card or a tab as well as on a field. Anything more than "a field depends on a
field" is a component of its own.

## Validation and the JSON schemas

`validateScreen(root)` and `validatePatch(patch)` return a list of `{ path, message }` — required
keys, the closed key set, value types, unique ids, the shape of a condition. Empty means sound.
Both halves check descriptions when they register them, and a broken description is an exception
there, not a warning.

The same rules as JSON schemas, for editors that highlight as you type:

- `https://webx-ui.github.io/webx-ui/schema/screen.json`
- `https://webx-ui.github.io/webx-ui/schema/patch.json`

```json
{
  "$schema": "https://webx-ui.github.io/webx-ui/schema/screen.json",
  "screen": "settings.index",
  "root": []
}
```

They ship with the package too, as `@webx-ui/schema/schemas/screen.json` and `patch.json`.

## Where the tree lives

In a panel, the reference tree of a screen lives in the module's Composer package, next to the
rules for writing it and the permissions — the server needs it no less than the renderer does.
The front end fetches a screen with `GET /api/cms/screens/<name>` when its page opens: the tree,
server patches applied in order (other packages first, the project last), nodes without
permission already cut out, `trans::` strings already translated. Client patches from `admin.ts`
go on top. That endpoint is `GET /api/cms/screens/<name>` in `module-admin`, with
`Screens::register` and `Screens::extend` behind it; the design in full is
[`docs/architecture/WEBX_UI_SCREENS.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_SCREENS.md).
