---
'@webx-ui/php': patch
---

The entry `webx:panel` writes now imports the stylesheets. The packages ship compiled CSS that
nothing imports on its own, so the panel built from the previous stub ran perfectly and looked
like an unstyled form.
