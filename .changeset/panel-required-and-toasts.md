---
'@webx-ui/core': minor
'@webx-ui/schema': minor
'@webx-ui/module-admin': patch
'@webx-ui/module-orders': patch
'@webx-ui/module-media': patch
'@webx-ui/php': patch
---

Toasts leave on their own: a report (default, success, info) after 5 s, a warning or an error after 8 s; the pointer over the toasts or focus in them pauses the count, the × stays. `duration` sets a time of its own, the new `persistent: true` (or `duration: 0`) keeps a toast until it is dismissed — the media library's «copy the address by hand» does. `toast.danger` no longer stays by default.

The asterisk of a required field comes from the field's rules across the panel. A screen node gets `required` (schema, renderer → `WxFormItem`); the server sets it when it hands a screen out, for a bare `required` among the rules of the field's type and the rules a module tells with `Screens::rules()` (`Screens\Required::among()`); conditional rules (`required_without`, `required_if`) give none. `orders.show` and `users.form` tell theirs. A 422 toast says the first refusal without Laravel's untranslated «(and N more errors)».

module-orders: the contacts of an order are refused in the module's words with human names of the fields in the panel's language — panel, MCP and checkout alike (`ContactRules`); «Enter a phone or an e-mail.» under the phone and the e-mail and a hint under the pair on «New order» and in the card, the asterisk only at the buyer's first name; the cancel reason refused in words too.
