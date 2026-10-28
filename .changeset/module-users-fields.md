---
'@webx-ui/php': minor
---

`webx-ui/module-users`: the site's own fields on its visitors. A field is declared once — in `webx-users.fields` or by a module with `UserFields::register()` — with a field type of the panel, its places (registration, profile, panel), rules, `unique` and `visitorEditable`; it is kept in `users.extra`, asked in the registration after the built-in fields, and handed to the profile, the panel's card and MCP (`FieldValues`, `FieldCatalog`) with the same rules. Whether the visitor must fill it in is the setting `users.fields.<name>` (`hidden` / `optional` / `required`), which covers the first name, last name and phone as well. A field that cannot work — a taken name, an unknown type, a choice without options, a type the site's forms lack outside the panel — is an exception and a line of `webx:doctor`; `webx:users:index-field <name>` writes a site migration with an indexed generated column that the `unique` check uses.
