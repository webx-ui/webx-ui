---
'@webx-ui/php': minor
'@webx-ui/module-inbox': minor
'@webx-ui/module-blocks': patch
---

A form of the Inbox is chosen, not typed: the field type `wx-inbox-form` (registered by `module-inbox`, drawn by `WxInboxFormField` of `@webx-ui/module-inbox`) searches the forms by name and slug, shows both, and is cleared by its remove button. It keeps the slug, as the text field did, so blocks written before open with their form chosen and nothing is migrated. A slug no form has is refused when it is written — by the panel's save, `blocks_set_content` and `blocks_edit_content` (with the slugs there are), and a settings screen — and kept when the record already held it, drawn as "No such form". The `contact` block of `theme-default` uses it. The relation target `inbox-form` answers with `slug` and searches by it, the exact one first. `ScreenValues::validate()` and `ScreenRecord::split()` take `held:` — what the record keeps — and run the checks only a new value has to pass (`ChecksNewValues`) on what differs from it; the settings pass it. A block added from the picker no longer copies the sample's form.
