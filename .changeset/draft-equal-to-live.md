---
'@webx-ui/php': patch
---

A draft that says what the site already says is not kept: a letter typed and taken back between two autosaves no longer leaves a page «modified», in the panel or through an agent's update. Publishing with nothing changed and no new date writes no version (`HasDraft::matchesLive()`).
