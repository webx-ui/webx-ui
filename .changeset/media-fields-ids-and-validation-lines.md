---
'@webx-ui/php': patch
'@webx-ui/module-media': patch
---

A field that holds a library file (`wx-media`, `wx-gallery`, `wx-file`, `wx-files`) takes the file by its id, as `{ id }`, by its key `{ path }`, or as the whole object `media_get_file` and `*_get` return — extra keys are dropped, an unknown id is refused by name. The same on every path of writing: the panel, MCP, blocks, repeaters. MCP describes it in the new resource `media://fields`, and the server's instructions point at it.

Laravel's validation messages come in the panel's language on a site with a theme: the lines of `webx-ui/localization` wrap the translation loader instead of being added to a bare `FileLoader`, which `webx-ui/themes` hides. A site in Ukrainian no longer answers with `validation.array`, and a line in the site's own `lang/` still wins.
