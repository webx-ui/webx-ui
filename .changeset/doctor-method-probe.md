---
'@webx-ui/php': patch
---

`webx:doctor` asks the site over `APP_URL` whether the web server lets PUT and DELETE through to Laravel, and warns with `methodOverride` as the cure when it refuses them itself. The answer comes from a new `PUT|DELETE /api/cms/method-probe`, which marks its answer and changes nothing; an unreachable site skips the check. The new-site guide describes the symptom, the option and the check.
