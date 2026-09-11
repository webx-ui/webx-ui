---
'@webx-ui/core': minor
---

The small pieces an admin screen is assembled from: icons, badges, typography, and four components
that had been standing in as markup.

`WxIcon` draws one icon from a built-in set of 24×24 stroke drawings that take the colour and the
size of the text around them; `registerIcons` adds your own, so a name is all the JSON schema
renderer will ever need. `WxBadge` is the label that says what something is, and `WxIndicator` the
count or dot pinned to a button, an icon or a link — Element Plus splits the same job between
`el-tag` and `el-badge`.

`WxButtonGroup` joins buttons into one segmented control and hands its look down to them, which is
why `WxButton` now resolves `type`, `variant` and `size` from the group when its own are unset — a
button that sets one still wins.

Typography: `WxHeading` separates the level in the outline from the size on screen, `WxText` covers
the body, the hints and the truncation, `WxLink` opens an external target safely and renders through
`RouterLink` when asked, and `WxProse` gives the editor's HTML the typography of the design system —
headings, lists, quotes, code, images, and a pasted table that scrolls inside its own box.

`WxAutocomplete` suggests without constraining: the model is the text, `search` is debounced and
held back by `min-length`, and picking a suggestion does not ask the backend for what it has just
been given. `WxCascader` picks out of a tree one column per level, either handed over whole or
fetched level by level through `load`, and walks with the arrow keys.

`WxEntityCard` is one record as a row — picture, name, the fields under it, and an actions slot
whose clicks stay out of the row's own. `WxTimeline` and `WxTimelineItem` show what happened and
when. `WxStatistic` groups a number through `Intl` rather than a hard-coded separator, and
`WxCountdown` counts down to a moment with a format where only the tokens present consume time, so
`mm:ss` on two hours prints `120:00` instead of quietly dropping the hours.
