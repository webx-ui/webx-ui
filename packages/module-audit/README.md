# @webx-ui/module-audit

The «Audit» section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: the
overview of the last run — health, counts by severity and group, what is new and what got fixed —
and its findings, one row per check, each opening into its addresses.

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
