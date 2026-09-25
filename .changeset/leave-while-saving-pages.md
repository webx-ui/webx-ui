---
'@webx-ui/module-pages': patch
---

Leaving the editor while an autosave is on its way no longer asks whether to leave without saving: the save waits for the one in flight, and goes again only for what was typed meanwhile.
