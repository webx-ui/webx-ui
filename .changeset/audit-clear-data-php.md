---
'@webx-ui/php': minor
---

`module-audit`: `DELETE /audit/runs` (`audit.manage`) deletes every run with its findings, pages, links, resources and content addresses; refused with 409 while a run is going. The hiding rules and the settings stay.
