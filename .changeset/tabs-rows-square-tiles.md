---
'@webx-ui/php': patch
---

Widgets: the row of tabs no longer shows a scrollbar — it still scrolls by touch, wheel and arrow keys. The `tabs` block gets «Tabs in rows on a narrow screen»: under 40em the tabs share rows instead of scrolling (`--b-tabs-per-row` sets how many in a row). Theme-default `text-tiles`: «Tiles» square — two by two where both stay squares their words fit in, a tile growing taller when its words need it and its row with it, one under another elsewhere; a «Gradient» shade, dark behind the words and clear on the far side; tile spacing as theme knobs (`--text-tiles-tile-padding`, `--text-tiles-tile-gap`, `--text-tiles-tiles-gap`). Blocks saved before print what they printed before; an existing site takes the new fields with `webx:blocks:import --path=vendor/webx-ui/widgets/resources/blocks --replace=tabs` and `webx:blocks:import --theme --replace=text-tiles`.
