---
'@webx-ui/php': minor
'@webx-ui/module-payments': minor
---

`webx-ui/module-payments`: optional type contract `ValidatesSettings` — `MethodForm::save()` asks the type about the settings as they will be stored and refuses by field, in the panel and in `payments_methods_save` alike. A field of `settings()` may carry `off_with` and `off_help`: `MethodPane` turns it off and empties it while the named switch is on, and says why. Gateway A's card gets the setting `instalments`, ruled out by a hold, as the example.
