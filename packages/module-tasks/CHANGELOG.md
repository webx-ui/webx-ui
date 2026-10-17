# @webx-ui/module-tasks

## 0.2.0

### Minor Changes

- 08eabf2: The panel downloads half as much before it can draw anything (webx-ui#16, stage 4). `WxCodeEditor` (CodeMirror and its languages) and `WxRichText` (Tiptap) are async components of the core: same names, props, events and `v-model`, a template ref points at the editor once it has arrived (`null` until then), and a stub by name in a test still replaces them. The pages of every module's routes load when their route opens (`component: () => import(...)`), and the indexes export them as `defineAsyncComponent` under the same names — a page re-exported statically would be pulled back into the index. `createAdmin({ modules })` and a site's `admin.ts` are unchanged. A site with 11 modules: 839 → 477 KB gzipped on first load; the playground with every module: 1012 → 514 KB. A jsdom test that mounts a page or an editor from a package waits for it with `await vi.dynamicImportSettled()`. `@webx-ui/module-blocks`: the word of the `block-usage` cache group.

### Patch Changes

- Updated dependencies [08eabf2]
- Updated dependencies [08eabf2]
  - @webx-ui/module-admin@0.30.0
  - @webx-ui/core@0.43.0
  - @webx-ui/schema@0.9.3

## 0.1.2

### Patch Changes

- Updated dependencies [c700893]
- Updated dependencies [c700893]
  - @webx-ui/core@0.42.0
  - @webx-ui/module-admin@0.29.0
  - @webx-ui/schema@0.9.2

## 0.1.1

### Patch Changes

- Updated dependencies [152a118]
  - @webx-ui/module-admin@0.28.0

## 0.1.0

### Minor Changes

- be30498: `webx-ui/module-tasks`, T3 — the panel: the list of tasks with its views («Waiting for me», in work, drafts, closed), one task with its specification, stages, reports and journal and the buttons the rules allow (put to work, accept, send back, answer a question, release, cancel), the form as the described screen `tasks.form` with the rules of §3 laid over it, and the projects. `@webx-ui/module-tasks` is its npm half; the section is in the `webx:setup` catalogue, off by default. `@webx-ui/module-admin`: a module may put a count under `meta.badge` of its manifest, and the menu shows it beside the entry — tasks show what waits for a person.
- be30498: `webx-ui/module-tasks`, T4 — notifications: a person hears from Telegram that a stage waits for their review, that an agent asked a question, that a task is done or a stage hung in progress (and, when switched on, that a stage was sent back or waits for an agent's review). Queued after the move commits, three attempts, the last failure written to the task's journal; the bot token is stored encrypted and never read back. «Tasks → Settings» has the token, the chats, the events and a test message; another channel is a binding of the `Notifier` contract.

### Patch Changes

- Updated dependencies [be30498]
  - @webx-ui/module-admin@0.27.0
