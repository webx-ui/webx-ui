# @webx-ui/module-vacancies

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
