---
'@webx-ui/php': minor
'@webx-ui/module-admin': minor
---

Codes are made, not refused (#125, stage 1). `Slug::code($text, $locale, $max, $separator)` of `localization` brings a typed code to the shape of its field — transliterated by the site's language, lower case, one separator between words, cut to length: «тормозные колодки» is `tormoznye-kolodki`. A new screen field type `wx-code` (`CodeType` on the server, `WxCodeField` and `codify()` in `@webx-ui/module-admin`) does it as the code is typed and as it is saved; `props.separator` (`-` or `_`) and `props.maxlength` are its shape. The code of a stock status uses it in the panel, and the catalogue's dictionaries (stock statuses, labels) bring a code to its shape on every save — the panel, MCP and the exchange — instead of refusing it; a code that leaves nothing is made of the name, a taken one is still refused. Address slugs are lower-cased by `HasLatinSlug`: `Foo` is saved as `foo` rather than refused.
