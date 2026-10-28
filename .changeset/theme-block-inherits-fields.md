---
'@webx-ui/php': minor
---

`themes`: an override of a block type in a theme no longer fails when the type below grows a field.
A field of the lower type the override does not describe is inherited — whole, with its `default`
and its sample value, after the field it follows below (or at the end) — by `LayerBlocks`, so
`webx:blocks:import --theme`, `webx:theme:show` and the type hash all see it. `webx:theme:check`
says it as a note (`inherits field … with its default`, and whether the template uses it) instead
of the finding `field … is gone`. Retyping a field is still a finding and a refused import; a field
the override describes keeps its own label and default.
