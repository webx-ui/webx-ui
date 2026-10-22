---
'@webx-ui/module-admin': patch
'@webx-ui/module-catalog': patch
'@webx-ui/module-catalog-landings': patch
'@webx-ui/module-catalog-properties': patch
'@webx-ui/module-catalog-manticore': patch
'@webx-ui/php': patch
---

The catalog and its satellites no longer fall back to the English defaults of core components in a translated panel: drag handles, branch toggles, table spinners, picker search boxes and their empty answers are said in the panel's language. The words live in `webx-admin::filters` (`loading`, `reorder`, `search`, `nothing-found`, `expand`, `collapse`) in all ten languages, for any module to hand to a core component.
