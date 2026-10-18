---
'@webx-ui/php': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-media': minor
'@webx-ui/module-catalog': patch
---

Private files, the library's pickers and the checks that asked the wrong permission (WEBX_UI_PERMISSIONS.md §22).

Composer packages:

- **Private files refuse a public disk.** `WebxUi\Admin\Support\PrivateDisk` (`module-admin`) calls a disk public when it has `visibility: public` or a local root inside the web root or `storage/app/public`. Attachments of submissions (`webx-inbox.disk`), the catalogue exchange (`webx-catalog.exchange.disk`) and database dumps (`webx-admin.backup.disk`) now throw a `RuntimeException` naming the setting before the first write, instead of serving visitors' files and exports to anyone with the address.
- **A catalogue export is its starter's** (choice 11 (a)). Everybody with the catalogue still sees the runs; the file — `runs/{id}/file` and `file_url` — is for whoever started the run and super administrators. Others get `file_url: null`, the new `file_yours: false`, and **403** from `runs/{id}/file`. An import address is shown to them without its query, fragment and user (`ExchangeRun::withoutSecrets()`). `ExchangeRun::toResponse()` takes the reader; `catalog_exchange_run` answers the same way.
- **Pickers count the `manage` of the same family**: the link picker, collections and relations let `x.manage` in where they asked for `x.view` alone (`AccessPolicy::sees()`, `AccessPolicy::seeing()`).
- **The content of a record through the blocks tools needs the record's section too**: `blocks_set_content` and `blocks_edit_content` ask the section's permission from `SiteBar::entity()` (`pages.manage` for a page) besides `blocks.manage`; `blocks_get_content` and `blocks_preview_url` ask its `view` or `manage`.
- **The rows of a history run** (`history/runs/{id}`, `history_runs` with `run_id`) are only those of types the reader may read. `HistoryReader::rowsOf()` takes `types:`.
- MCP tools brought in line with the panel's routes: `catalog_index_status` is behind `search-index.view|manage` (no longer `catalog.view`); the catalogue's read tools also take `catalog.delete`; the audit's read tools also take `audit.run`; `tasks_stage_release` also takes `tasks.manage` (scope `tasks:write` or `tasks:work`); `tasks_comment` also takes the scope `tasks:review`. The panel's notification preview (`POST notifications/preview`, `channels/{id}/preview`) is open to `notifications.view`, as the tool already was.
- The SEO interlinking and FAQ exports no longer leave an empty temporary file behind.

`@webx-ui/module-admin`: `AdminModule.pickImageWith` — the permissions that let somebody open `pickImage`; without one `admin.pickImage` is `null` and `wx-rich-text` draws no image button. The backup note shows with `settings.manage` too.

`@webx-ui/module-media`: the fields over the library (`wx-media`, `wx-gallery`, `wx-file`, `wx-files`) open the picker only for `media.view` or `media.manage`; without either they keep, caption and clear what is chosen and say the library is closed (`field.locked`). The module sets `pickImageWith` and does not ask the library for addresses without the right. New exports: `canBrowseMedia`, `MEDIA_BROWSE`.

`@webx-ui/module-catalog`: a finished export that is somebody else's says whose it is rather than «no longer kept» (`file_yours`).
