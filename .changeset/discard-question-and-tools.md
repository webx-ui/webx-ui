---
'@webx-ui/module-admin': patch
'@webx-ui/module-blog': patch
'@webx-ui/module-events': patch
'@webx-ui/module-services': patch
'@webx-ui/module-recipes': patch
'@webx-ui/module-vacancies': patch
'@webx-ui/module-pages': patch
'@webx-ui/php': minor
---

«Discard changes» asks one plain question in every drafted editor — «Discard changes? The published version comes back. Everything changed since publishing is lost…» — with «Keep» beside it instead of «Cancel». Agents get `articles_discard`, `events_discard`, `services_discard`, `recipes_discard` and `vacancies_discard` beside `pages_discard`; a dry run names only the fields that differ from the published ones (`HasDraft::changedFields()`).
