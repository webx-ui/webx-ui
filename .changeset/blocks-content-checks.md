---
'@webx-ui/module-blocks': minor
'@webx-ui/core': patch
'@webx-ui/schema': patch
---

The block constructor shows what a save was refused for: the block opens and the message stands under its field, the tree marks the block, a limit of the page is said above the tree. A block added from the picker starts with the sample's settings and none of its words, which are shown as placeholders. «Add inside» on a full container says it is full; a block switched off is dimmed in the preview until it reloads. The block editor offers only real fields to insert (a repeater as a loop, a container as `@blocks`), says which list a container field takes, warns before publishing a version that switches a field's `localized`, asks before dropping other languages, and names a rename in its toast. Thumbnails load as they scroll into view and say when a block draws nothing on its sample; the tree's marks use the panel's tooltip. `WxCheckboxGroup` disables the boxes past `max` instead of ignoring the click; a collapsed menu row carries its name as `aria-label`. `wx-text` in a screen draws `props.text`.
