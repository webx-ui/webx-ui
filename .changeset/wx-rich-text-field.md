---
'@webx-ui/module-admin': minor
'@webx-ui/module-media': minor
'@webx-ui/core': minor
'@webx-ui/php': minor
---

`wx-rich-text`: the editor as a field of a screen

A node type on both halves. On the server it is checked against `props.maxlength`, stored
through an allowlist — a `<script>`, an `onclick` or a `javascript:` address does not survive —
and an emptied editor is stored as `null` rather than as `<p></p>`. `localized` needs nothing of
its own: the language map is picked apart one layer up, so a translated article is the same type
run once per language.

Pictures come from the file manager. `AdminModule` gains `pickImage`, which `module-media`
supplies and the panel hands to every editor on every screen; a panel without a file manager
draws no image button, because the editor does not offer what it cannot do.

What a document keeps for a picture is the library's **key**, as `data-wx-path`, and the address
is worked out again on every read through `WebxUi\Admin\Contracts\AssetUrls`. The same rule
`wx-media` has always followed, one layer in: the address differs between deployments of one
site, a private bucket's address expires, and an image edited in place changes the version stamp
without changing the key.

`WxRichText` itself gains `localized` — one editor with a language chip, as `WxInput` and
`WxTextarea` have — and `labels`, so the panel can put its own words on the toolbar.

`HasDraft::publish()` takes an optional `?CarbonInterface $at`: the date an entity is published
under is not always now, and it cannot travel through the draft.
