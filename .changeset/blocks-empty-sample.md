---
'@webx-ui/module-blocks': patch
---

A component's sample can leave a `wx-data` input empty. The empty editor is `null` — the call that
passes nothing, which a component has to survive and which publishing now checks when the sample
says so — rather than "Not valid JSON"; a `null` shows as an empty editor, and a typed `null` is no
longer turned back into `{}`. The JSON lint stays quiet on the empty editor.
