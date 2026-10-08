---
'@webx-ui/php': patch
'@webx-ui/module-blocks': patch
---

A block template that changed a text field with a string function — `{{ rtrim($heading, '.') }}`
— printed `Call &lt;a href=…` once the field held `[phone]`: the function turned the resolved
HTML into a string and `{{ }}` escaped it again. `wx_text($field)` takes a plain string or a
`ShortcodeText` and returns a `ShortcodeText` whose `trim`, `trimStart`, `trimEnd`,
`stripPrefix`, `stripSuffix` and `map()` change what the editor typed and stay HTML:
`{{ wx_text($heading)->trimEnd('.') }}`. The template checks warn (`string-on-text`) about a
string function or a cast applied to a text field inside `{{ }}`, naming the field and the fix,
on saving and live in the editor.
