---
'@webx-ui/php': minor
---

The change history in `module-admin` (`WEBX_UI_HISTORY.md`): the `admin_history` table, one row
per save with who, when, through which door and what changed. `RecordsHistory` on a model writes
its saves field by field (translated fields per language, service fields and secrets left out,
long values as a length); `History::record()` and `History::recordFor()` write by hand, and
`History::run()` puts an import or a bulk action under one parent row. The source and the author
come from `HistoryContext`, set by the new `webx.history` middleware at the end of the panel's API
group, by the MCP server (`mcp` with the administrator and the grant) and `console` otherwise. A
module registers its types with field labels and a view permission (`HistoryTypes::register`);
`GET /api/cms/history/{type}/{id}` and `GET /api/cms/history/runs/{id}` read them, and so do the
MCP tools `history_get` and `history_runs` with the `history://types` resource. Rows older than
`webx-admin.history.retention_days` (365) go nightly with `webx:history:prune`.
