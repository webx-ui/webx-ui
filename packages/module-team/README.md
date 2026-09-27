# @webx-ui/module-team

The front end of the team section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: the people of the organisation — a photo, a name, a job title, a few lines of text, their
social links and, when the site has services, the services they provide.

The other half is the Composer package `webx-ui/module-team`, which owns the people, their order,
the Team block, `team()` for templates and the API. A section appears in the panel when both halves
are installed.

## Install

```bash
npm install @webx-ui/module-team
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { team } from '@webx-ui/module-team'
import '@webx-ui/module-team/style.css'

createAdmin({
  basePath: '/cms',
  modules: [team()],
}).mount()
```

`team()` is one section and one entry of the menu, at the top level — the team has no categories,
so there is nothing to group it with. `team({ path: '/staff' })` puts it somewhere else inside the
panel.

On the server, the block type the module offers is installed once:

```bash
php artisan webx:blocks:offered --install --module=team
```

## The team

A list and the form of one person side by side, without pages: the list is where people are put
in order — the one order every team block on the site shows — and a drag cannot cross a page
boundary. The open person is in the address (`?member=12`), so a link to them is a link to the list
around them too. **New person** is a row that opens an empty form; the person is created by its
first save. On a phone the form slides over the list and draws its own «Back».

A row shows the photo (or the initials), the name, the job title and a mark when the person is not
published. While a search narrows the list, the grips disappear. The bin lists who was deleted,
newest first, and brings them back to their place.

The form is the `team.form` screen, described in JSON on the server: the photo from the library,
the name, the job title and the text in every language of the site, the social links (a network
from the site's list and an address, in the order the site prints them), the services — only on a
site that has them — **Published** and a card for the project's own fields. Save with the button or
`Ctrl+S`; leaving with unsaved changes asks first. A person has no draft: a save is on the site.

## Words

The panel's words come from the server (`webx-team::*`, ten languages). `teamMessages` is the
English floor the section falls back on while they load, or when a key is missing.

## License

MIT
