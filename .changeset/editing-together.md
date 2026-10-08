---
'@webx-ui/module-admin': minor
'@webx-ui/module-pages': minor
'@webx-ui/module-blocks': minor
'@webx-ui/module-services': minor
'@webx-ui/module-recipes': minor
'@webx-ui/module-events': minor
'@webx-ui/module-blog': minor
'@webx-ui/module-vacancies': minor
'@webx-ui/php': minor
---

Two hands on one record no longer cost either of them their work. A save refused because somebody
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
