---
'@webx-ui/core': minor
---

`WxCodeEditor` highlights Mustache: `language="mustache"` over text and `"mustache-html"` over HTML — values, sections, comments, and what a template may not use (partials, delimiters, a tag never closed) in red. A new `completions` prop turns on suggestions while typing and on Ctrl+Space: a list of words, or a function of the document and the caret; `mustacheCompletions(variables)` is the one for Mustache — variables with examples, a list written as a whole section, the fields of the list being looped over, the close of the open section. `mustacheProblems()` and `scanMustache()` read a template the same way. `localized` edits one document per language of the site, as `WxInput` and `WxTextarea` do. The component is generic over its model — a plain editor is still a `string` to whoever binds it — so a template ref is typed `ref<CodeEditorExposed | null>()` rather than `InstanceType<typeof WxCodeEditor>`. A `send` icon.
