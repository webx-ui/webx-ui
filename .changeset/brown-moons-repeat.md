---
'@webx-ui/module-blocks': patch
---

The block constructor's own preview switches width by picture too

`BlockStage` — the preview beside the template in the Blocks section — still spelled out
"Desktop · Tablet · Phone" while the page preview had already moved to the three device icons.
Two previews in one panel, one of each kind, is the sort of difference an editor reads as
meaning something. The words stay as the accessible name, and the group is named "Width".
