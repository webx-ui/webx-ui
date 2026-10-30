# @webx-ui/module-vacancies

## 0.3.5

### Patch Changes

- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
  - @webx-ui/core@0.46.0
  - @webx-ui/module-admin@0.34.0
  - @webx-ui/schema@0.10.0

## 0.3.4

### Patch Changes

- d0e3145: Panel lists: the rules around a hovered or current row go. Properties, the inbox forms, reviews, press, tariffs,
  services, vacancies and recipes tint their rows through `WxSortableList`'s `hoverable` instead of rules of their own,
  so the lines above and below the tinted row are hidden, and the divider between reviews (press, tariffs) is straight
  rather than curved by the row's radius. `WxSortableList` with `plain` drops the head's own top step — the frame that
  holds a plain list insets it already — and the inbox's column of forms starts at the pane's step with its name on the
  pane head's line. The inbox search box stands the same 8px under the status tabs and over the table.
- Updated dependencies [d0e3145]
  - @webx-ui/core@0.45.3

## 0.3.3

### Patch Changes

- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
  - @webx-ui/module-admin@0.33.0
  - @webx-ui/core@0.45.0
  - @webx-ui/schema@0.9.5

## 0.3.2

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

## 0.3.1

### Patch Changes

- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
  - @webx-ui/module-admin@0.31.0

## 0.3.0

### Minor Changes

- 08eabf2: The panel downloads half as much before it can draw anything (webx-ui#16, stage 4). `WxCodeEditor` (CodeMirror and its languages) and `WxRichText` (Tiptap) are async components of the core: same names, props, events and `v-model`, a template ref points at the editor once it has arrived (`null` until then), and a stub by name in a test still replaces them. The pages of every module's routes load when their route opens (`component: () => import(...)`), and the indexes export them as `defineAsyncComponent` under the same names — a page re-exported statically would be pulled back into the index. `createAdmin({ modules })` and a site's `admin.ts` are unchanged. A site with 11 modules: 839 → 477 KB gzipped on first load; the playground with every module: 1012 → 514 KB. A jsdom test that mounts a page or an editor from a package waits for it with `await vi.dynamicImportSettled()`. `@webx-ui/module-blocks`: the word of the `block-usage` cache group.

### Patch Changes

- Updated dependencies [08eabf2]
- Updated dependencies [08eabf2]
  - @webx-ui/module-admin@0.30.0
  - @webx-ui/core@0.43.0
  - @webx-ui/schema@0.9.3

## 0.2.6

### Patch Changes

- Updated dependencies [c700893]
- Updated dependencies [c700893]
  - @webx-ui/core@0.42.0
  - @webx-ui/module-admin@0.29.0
  - @webx-ui/schema@0.9.2

## 0.2.5

### Patch Changes

- Updated dependencies [152a118]
  - @webx-ui/module-admin@0.28.0

## 0.2.4

### Patch Changes

- Updated dependencies [be30498]
  - @webx-ui/module-admin@0.27.0

## 0.2.3

### Patch Changes

- Updated dependencies [e107a30]
- Updated dependencies [e107a30]
  - @webx-ui/module-admin@0.26.1
  - @webx-ui/core@0.41.0
  - @webx-ui/schema@0.9.1

## 0.2.2

### Patch Changes

- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
- Updated dependencies [8820489]
  - @webx-ui/module-admin@0.26.0
  - @webx-ui/core@0.40.2

## 0.2.1

### Patch Changes

- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
- Updated dependencies [f2556af]
  - @webx-ui/core@0.40.0
  - @webx-ui/schema@0.9.0
  - @webx-ui/module-admin@0.25.0

## 0.2.0

### Minor Changes

- 0f5ed7a: An open editor hears more than the revision. The heartbeat reports the record's state, where it
  sits and what was done to it — published, unpublished, its draft discarded, a version put back,
  moved, put in the bin or taken out — with who and through which door; the editor updates its badge,
  trail and address and says it in one line («Owner restored version 21 and published»). Publishing
  first checks the draft on the server: changes this editor never pulled in are named, with «Publish
  with them» or «Review first», and the publication carries the revision the person agreed to (409
  when it moved on). A record in the bin is said so, with who put it there, the form kept and
  «Restore» — «Restore and save my changes» when there are unsaved ones. Notices and the list of
  drafts name fields the way the form and the block type do, and each draft copy says what it
  changed. Over MCP, publish, unpublish, discard, version restore, move and delete take the revision
  too — required while somebody has the record open, and the refusal names who — and a record has one
  revision whichever tool reads it (`pages_get` and `blocks_get_content` agree).
- 0f5ed7a: Two hands on one record no longer cost either of them their work. A save refused because somebody
  else wrote in between is merged with theirs — by field, by language, by block key — and saved
  again without a question; only a place both sides changed, or a block one removed while the other
  edited it, is listed with its three versions to settle one by one, and «Keep mine» never drops the
  other side's other changes. The banner names who changed it and whether through an agent. While
  an editor is open it sends a heartbeat (`POST /editing/{entity}/{id}`): a save that came in
  meanwhile is offered with «Pull in» before it would be saved over, and the agent's reads answer
  `being_edited_by`. A draft that a save by somebody else replaces is kept as an `overwritten`
  version, listed with the autosaves under «Drafts» in every editor's History and restorable there,
  and through `pages_versions` / `pages_version_restore` with `draft`. Over MCP a write to a page,
  to block content or to a drafted record needs the `revision` its read returned; `force: true` is
  the way for a script that means to overwrite. Shared as `useEditing`, `WxEditingAlerts` and
  `WxDrafts` in `@webx-ui/module-admin` and `EditedRecords`, `Presence`, `LastChange` and
  `AgentRevision` in `webx-ui/module-admin`, wired into pages, layout regions, services, recipes,
  events, articles and vacancies.

### Patch Changes

- 0f5ed7a: An editor open on a record somebody deleted for good says so. A purge is noted as a `purged` event
  that outlives the row for an hour; the heartbeat then answers 410 with who did it, through which
  door and when, and so does a save or a publication through the module's own endpoint (any JSON 404
  from a model `findOrFail` or route binding could not find). The editor says «Agent, through an agent
  deleted this for good · 18:01», stops autosaving, keeps the form and offers «Copy my text» — every
  piece of text in the form under the name the form gives it. `EditedRecords::register()` takes
  `model:` for this; `useEditing` gains `gone`, `stopped`, `text()` and `copyText()`. A purge of a
  record in the bin is no longer heard as a second trip to the bin. In the bin or gone, Save and Publish are
  disabled with the reason as their title (`blocked`), and the save state is hidden.
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
- Updated dependencies [0f5ed7a]
  - @webx-ui/core@0.39.0
  - @webx-ui/module-admin@0.24.0
  - @webx-ui/schema@0.8.1

## 0.1.9

### Patch Changes

- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
  - @webx-ui/core@0.38.0
  - @webx-ui/schema@0.8.0
  - @webx-ui/module-admin@0.23.5

## 0.1.8

### Patch Changes

- a814314: «Discard changes» asks one plain question in every drafted editor — «Discard changes? The published version comes back. Everything changed since publishing is lost…» — with «Keep» beside it instead of «Cancel». Agents get `articles_discard`, `events_discard`, `services_discard`, `recipes_discard` and `vacancies_discard` beside `pages_discard`; a dry run names only the fields that differ from the published ones (`HasDraft::changedFields()`).
- a814314: The editors of pages, services, events, recipes, vacancies and articles have «Take off the site»
  as a button of its own in the head while the record is on the site — before, only the list's ···
  had it. It asks first, saying what happens and that «Publish» brings it back at the same address.
  The words are the panel's (`editor.unpublish*`), shared by every module.
- a814314: Panel lists show an address instead of «No address in this language». They are read in the site's
  content language, not the panel's, so an English-only site in a Russian panel shows its addresses
  plainly; and a record with no address in the language a multilingual list is read in shows its
  address in the site's main language, with an info mark whose tooltip says so (`address_locale` in
  the rows, `PanelAddress` in `routing`, `WxAddressNote` in `module-admin`). Pages, articles,
  events, recipes, services, vacancies and every module's categories.
- a814314: The publish question names the address the record will have after publishing. A slug renamed in
  the draft moved the address only on publishing, while the question still named the old one; rows
  now carry `next_path` (`PanelAddress::afterPublishing()`), and the question adds that the old
  address will lead to the new one.
- a814314: «Publish» with an unsaved edit saves first and then asks, so the question names the address the page will publish at rather than the one it had before the edit.
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
  - @webx-ui/core@0.37.1
  - @webx-ui/module-admin@0.23.4
  - @webx-ui/schema@0.7.4

## 0.1.7

### Patch Changes

- Updated dependencies [3dfe40a]
  - @webx-ui/core@0.37.0
  - @webx-ui/module-admin@0.23.2
  - @webx-ui/schema@0.7.3

## 0.1.6

### Patch Changes

- Updated dependencies [62497fc]
  - @webx-ui/core@0.36.0
  - @webx-ui/module-admin@0.23.1
  - @webx-ui/schema@0.7.2

## 0.1.5

### Patch Changes

- Updated dependencies [41f2059]
  - @webx-ui/core@0.35.0
  - @webx-ui/module-admin@0.23.0
  - @webx-ui/schema@0.7.1

## 0.1.4

### Patch Changes

- Updated dependencies [3a08c6c]
- Updated dependencies [67e44aa]
  - @webx-ui/module-admin@0.22.0
  - @webx-ui/schema@0.7.0
  - @webx-ui/core@0.34.3

## 0.1.3

### Patch Changes

- Updated dependencies [38c5b08]
- Updated dependencies [5ebd999]
  - @webx-ui/module-admin@0.21.0
  - @webx-ui/core@0.34.2

## 0.1.2

### Patch Changes

- Updated dependencies [2ba4290]
  - @webx-ui/module-admin@0.20.0

## 0.1.1

### Patch Changes

- Updated dependencies [d1ff63d]
  - @webx-ui/module-admin@0.19.0

## 0.1.0

### Minor Changes

- e3bc0f0: The vacancies in the panel: the list with the open, closed and all vacancies and the bin, the
  order dragged by hand, «Duplicate» and «Close the hiring» in the row menu; the editor with the
  tabs Vacancy · Settings · SEO · History, autosave, revision and preview; the categories on the
  panel's shared screens.
