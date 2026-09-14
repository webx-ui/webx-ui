<script setup>
import ScreensDemo from '../components/demos/ScreensDemo.vue'
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

| Type              | Kind    | Component       | `label` goes to |
| ----------------- | ------- | --------------- | --------------- |
| `wx-tabs`         | layout  | `WxTabs`        | —               |
| `wx-tab`          | layout  | `WxTab`         | prop `label`    |
| `wx-card`         | layout  | `WxCard`        | prop `title`    |
| `wx-row`          | layout  | `WxRow`         | —               |
| `wx-col`          | layout  | `WxCol`         | —               |
| `wx-divider`      | layout  | `WxDivider`     | prop `label`    |
| `wx-input`        | field   | `WxInput`       | form item       |
| `wx-textarea`     | field   | `WxTextarea`    | form item       |
| `wx-input-number` | field   | `WxInputNumber` | form item       |
| `wx-select`       | field   | `WxSelect`      | form item       |
| `wx-switch`       | field   | `WxSwitch`      | form item       |
| `wx-checkbox`     | field   | `WxCheckbox`    | form item       |
| `wx-radio-group`  | field   | `WxRadioGroup`  | form item       |
| `wx-date-picker`  | field   | `WxDatePicker`  | form item       |
| `wx-color-picker` | field   | `WxColorPicker` | form item       |
| `wx-text`         | display | `WxText`        | default slot    |
| `wx-alert`        | display | `WxAlert`       | prop `title`    |

<!-- types:end -->

`wx-media` is `WxMediaField` from `module-media`, which registers it when installed (a module's
`types` are merged into every panel screen, and so are `createAdmin({ types })`); `wx-repeater`
— a list of items each edited with the same nested fields — is the one type with a nested model
and is not written yet.

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
