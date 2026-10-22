# @webx-ui/module-notifications

## 0.3.1

### Patch Changes

- e42643b: Removing one row of a list asks first, in a small popover by the button («Delete?» — «Yes» / «Cancel»), instead of removing at once. `WxAction` gains `confirm`, `confirmText` and `cancelText`: with a question set, `click` comes only on a yes. `WxPopconfirm` puts the focus on Cancel, goes ahead on Enter and reads the question out as the panel's description. The panel's words are `useRemoveConfirm()` of `@webx-ui/module-admin` (`webx-admin::confirm.*`, ten languages); labels and categories of a record, related records, banner layers, landing products and sets, category properties, intervals, inbox choices, notification chats and emails, SEO links, FAQ rows and the mapping and column rows of an exchange profile ask with it. Deleting a whole record keeps its dialog.
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
  - @webx-ui/module-admin@0.33.0
  - @webx-ui/core@0.45.0
  - @webx-ui/schema@0.9.5

## 0.3.0

### Minor Changes

- 9dcb323: The channel's form: the subject of a letter suggests the event's variables after `{{` as the
  template does (one line), and a variable clicked under the template goes into whichever of the two
  the caret was in last. Under the template, **Insert the default template** puts the event's own
  message in — the subject and the template in every language it has — asking first when something is
  written; a channel whose message is still empty gets it when its event is chosen. A letter whose
  template starts with a tag is previewed as the HTML letter it is, in a frame that runs nothing.

### Patch Changes

- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
  - @webx-ui/module-admin@0.32.0
  - @webx-ui/core@0.44.0
  - @webx-ui/schema@0.9.4

## 0.2.1

### Patch Changes

- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
  - @webx-ui/module-admin@0.31.0

## 0.2.0

### Minor Changes

- 08eabf2: The panel downloads half as much before it can draw anything (webx-ui#16, stage 4). `WxCodeEditor` (CodeMirror and its languages) and `WxRichText` (Tiptap) are async components of the core: same names, props, events and `v-model`, a template ref points at the editor once it has arrived (`null` until then), and a stub by name in a test still replaces them. The pages of every module's routes load when their route opens (`component: () => import(...)`), and the indexes export them as `defineAsyncComponent` under the same names — a page re-exported statically would be pulled back into the index. `createAdmin({ modules })` and a site's `admin.ts` are unchanged. A site with 11 modules: 839 → 477 KB gzipped on first load; the playground with every module: 1012 → 514 KB. A jsdom test that mounts a page or an editor from a package waits for it with `await vi.dynamicImportSettled()`. `@webx-ui/module-blocks`: the word of the `block-usage` cache group.

### Patch Changes

- Updated dependencies [08eabf2]
- Updated dependencies [08eabf2]
  - @webx-ui/module-admin@0.30.0
  - @webx-ui/core@0.43.0
  - @webx-ui/schema@0.9.3

## 0.1.0

### Minor Changes

- c700893: The «Notifications» section of the panel, a new package: channels with their switch, copy and bin; a channel's page with the provider's settings (secrets shown as set, never their value, and forgotten from the head's menu), recipients — Telegram chats with «Find chats», administrators and addresses for a letter, the address the event brings — and the Mustache template in every language of the site in a code editor that suggests the event's variables, lists and their fields after `{{` and closes the open section after `{{/`; a live preview on the event's examples, rendered by the same rules as the server; «Check the connection» and «Send a test»; the delivery log with filters and «Send again». `WxNotificationChannelsField` (`wx-notifications-channels`) is the picker a module chooses its channels for an event with.

### Patch Changes

- Updated dependencies [c700893]
- Updated dependencies [c700893]
  - @webx-ui/core@0.42.0
  - @webx-ui/module-admin@0.29.0
  - @webx-ui/schema@0.9.2
