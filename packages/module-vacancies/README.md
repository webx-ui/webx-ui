# @webx-ui/module-vacancies

The front end of the vacancies section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: who the organisation is looking for — where and how, the terms and the pay, the duties, the
requirements and what is offered — and the categories they are grouped under.

The other half is the Composer package `webx-ui/module-vacancies`, which owns the vacancies, their
pages and addresses, the `JobPosting` markup, `vacancies()` for templates and the API. A section
appears in the panel when both halves are installed.

## Install

```bash
npm install @webx-ui/module-vacancies
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { vacancies } from '@webx-ui/module-vacancies'
import '@webx-ui/module-vacancies/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...vacancies()],
}).mount()
```

`vacancies()` answers with two sections — the vacancies and their categories — because the panel
draws one entry per module. The server puts both in the `vacancies` group.
`vacancies({ path: '/careers' })` puts them somewhere else inside the panel.

## What is here

- **The list** — every vacancy at once, in the order the site lists them, with tabs: the open ones
  (the default), the closed ones, all — the closed ones dimmed, marked «Closed» or «Expired» — and
  the bin. The order is dragged by a handle, or with the keyboard on it, on «Open» and «All» while
  nothing narrows the list; the line under the rows says why when it cannot be. The category and
  the state are behind the funnel. «Duplicate» and «Close the hiring» / «Reopen» are in the row
  menu.
- **The editor** — the described screen `vacancies.form` with the tabs Vacancy · Settings · SEO ·
  History, autosaved into a draft and guarded by a revision. «Open until» and «Posted on» are
  calendar days (`YYYY-MM-DD`) and read the same day in every zone. «Duplicate» in the bar saves,
  copies and opens the copy. The categories, the application form and «closed» wait in the draft and
  reach the site on «Publish». A project adds its own fields with a patch into the
  `project-fields` card.
- **Categories** — the panel's shared category screens (`categoryRoutes`): groups with a key for
  the site's filter, no page of their own. `vacancyCategoriesOptions()` is exported for a panel
  that mounts them elsewhere.

The words are English by default and come from the server in the panel's language
(`webx-vacancies::`).

Documentation: https://webx-ui.github.io/webx-ui/guide/vacancies.html

## License

MIT
