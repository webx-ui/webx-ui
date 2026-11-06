# @webx-ui/module-users

## 0.1.3

### Patch Changes

- cd801c7: Покрытие справки панели включено: `AssertsHelp` и `HelpCoverageTest` в каждом пакете с экраном, патчем или разделом, `HelpPackagesCoverageTest`, `scripts/help-coverage.test.mjs`, команда `webx:help --check`, гайд `panel-help`. Всё, что ещё без текста, перечислено в `help-pending.json` и вычёркивается волнами наполнения. Пакеты регистрируют `resources/help`, у всех маршрутов панели есть `meta.help`, формы в панели списка передают ключ своей шапке. Кнопка появится, когда у экрана будет текст.
- Updated dependencies [cd801c7]
- Updated dependencies [cd801c7]
- Updated dependencies [cd801c7]
- Updated dependencies [cd801c7]
- Updated dependencies [cd801c7]
  - @webx-ui/core@0.47.0
  - @webx-ui/module-admin@0.36.0
  - @webx-ui/schema@0.11.1

## 0.1.2

### Patch Changes

- Updated dependencies [9c212d0]
- Updated dependencies [9c212d0]
- Updated dependencies [9c212d0]
- Updated dependencies [9c212d0]
  - @webx-ui/module-admin@0.35.0
  - @webx-ui/core@0.46.1
  - @webx-ui/schema@0.11.0

## 0.1.1

### Patch Changes

- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
  - @webx-ui/core@0.46.0
  - @webx-ui/module-admin@0.34.0
  - @webx-ui/schema@0.10.0

## 0.1.0

### Minor Changes

- d0e3145: Customers in the panel (`module-users`, stage 8 of webx-ui#88): the list with search by name, e-mail and phone and the filters by group, confirmed address, way to sign in, blocked and period; the card `users.form` — profile, the site's fields, groups, the account with the ways to sign in and «Unlink» (never the last way), history — with the letters, a confirmation by hand, the block and the deletion with the modules' warnings; a new customer without a password and the invitation «Set a password» (7 days); the customer groups (`users_groups`, a code and a name); the settings `users.settings` — the registration, how much each field is asked, a card per social network with the secret answered «set» only. Permissions `users.view`, `users.manage`, `users.settings`; every change in the journal. First release of `@webx-ui/module-users`.

### Patch Changes

- Updated dependencies [d0e3145]
  - @webx-ui/core@0.45.3
