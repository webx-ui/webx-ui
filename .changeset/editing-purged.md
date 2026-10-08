---
'@webx-ui/module-admin': minor
'@webx-ui/module-pages': patch
'@webx-ui/module-blocks': patch
'@webx-ui/module-services': patch
'@webx-ui/module-recipes': patch
'@webx-ui/module-events': patch
'@webx-ui/module-blog': patch
'@webx-ui/module-vacancies': patch
'@webx-ui/php': minor
---

An editor open on a record somebody deleted for good says so. A purge is noted as a `purged` event
that outlives the row for an hour; the heartbeat then answers 410 with who did it, through which
door and when, and so does a save or a publication through the module's own endpoint (any JSON 404
from a model `findOrFail` or route binding could not find). The editor says «Agent, through an agent
deleted this for good · 18:01», stops autosaving, keeps the form and offers «Copy my text» — every
piece of text in the form under the name the form gives it. `EditedRecords::register()` takes
`model:` for this; `useEditing` gains `gone`, `stopped`, `text()` and `copyText()`. A purge of a
record in the bin is no longer heard as a second trip to the bin.
