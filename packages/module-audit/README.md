# @webx-ui/module-audit

The «Audit» section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: the
overview of the last run — health, counts by severity and group, what is new and what got fixed —
its findings, one row per check, each opening into its addresses — and the pages the last full
run crawled, with any field of the snapshot as a column, a filter on any field, a CSV export and
a card per page with its answer, its problems and its links in and out.

The server half is the Composer package `webx-ui/module-audit`; the section appears in the
navigation when both are installed.

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { audit } from '@webx-ui/module-audit'
import '@webx-ui/module-audit/style.css'

createAdmin({ modules: [audit()] })
```

Options: `path` (default `/audit`) and `settingsPath` (default `/settings` — the audit's settings
live on the «Audit» tab there).

## License

MIT
