# @webx-ui/module-inbox

Forms and submissions as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: the forms of a site on the left, what came in through one of them on the right.

The other half is `webx-ui/module-inbox` on the server, which holds the forms, takes the
submissions and sends the notifications. Neither is useful alone.

## Install

```bash
pnpm add @webx-ui/module-inbox
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { inbox } from '@webx-ui/module-inbox'
import '@webx-ui/module-inbox/style.css'

createAdmin({
  modules: [inbox()],
})
```

The section claims the panel's front page: a panel with an inbox is one somebody opens in the
morning to see what came in overnight. A panel whose front page is something else says
`inbox({ landing: false })`.

## What is here

- **The section** — the forms in a column that can be dragged into order, each with what is
  waiting in it, and the submissions of the chosen one beside them.
- **The form editor** — what the form is called and where it answers, its questions, who is
  written to, the antispam, and the one line that puts it on a page.
- **The questions** — a field is a row with an identity, not a value of the form: it is saved
  in its own dialog, dragged into its own order, and deleted softly so that answers already
  given keep reading under the label they were asked with.
- **The statuses** — the states a submission can be in, as rows with their own words and
  colours.

## Permissions

`inbox.view` opens the section and the submissions; `inbox.manage` changes what the forms ask
and what the statuses are. The panel draws only what the reader may do, and the server refuses
the rest either way.

## Words

The English here is a floor, so nothing shows a key while the dictionary is on its way. The
translations live in the Composer package — one set of files for both halves.

## Licence

MIT
