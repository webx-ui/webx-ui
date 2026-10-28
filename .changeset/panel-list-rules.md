---
'@webx-ui/core': patch
'@webx-ui/module-inbox': patch
'@webx-ui/module-catalog-properties': patch
'@webx-ui/module-reviews': patch
'@webx-ui/module-press': patch
'@webx-ui/module-tariffs': patch
'@webx-ui/module-services': patch
'@webx-ui/module-vacancies': patch
'@webx-ui/module-recipes': patch
---

Panel lists: the rules around a hovered or current row go. Properties, the inbox forms, reviews, press, tariffs,
services, vacancies and recipes tint their rows through `WxSortableList`'s `hoverable` instead of rules of their own,
so the lines above and below the tinted row are hidden, and the divider between reviews (press, tariffs) is straight
rather than curved by the row's radius. `WxSortableList` with `plain` drops the head's own top step — the frame that
holds a plain list insets it already — and the inbox's column of forms starts at the pane's step with its name on the
pane head's line. The inbox search box stands the same 8px under the status tabs and over the table.
