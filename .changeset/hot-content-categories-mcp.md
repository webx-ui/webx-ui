---
'@webx-ui/php': patch
---

Category tools of every module (`*_categories_*`, `rubrics_*`, `recipe_nutrients_*` and the rest): a new `<x>_get`; a dry run of create, update and reorder is the write rolled back, so a taken address is refused by it too and it answers the paths and the order the write would make; reorder refuses an id that is not one; a title another category already has (any language, any case) is refused on create and update, and a name two categories answer to is refused with their ids; unknown arguments and unknown fields in `values` are refused rather than dropped. `webx-ui/mcp` gains `Arguments` (strict tool arguments) and `Rehearsal` (a dry run as a rolled-back write).
