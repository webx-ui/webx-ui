---
'@webx-ui/php': patch
---

Widgets: the `tabs` block can slide over the section above, as the theme-default blocks do — «Slide over the section above» with an edge picture and one for a phone; its words and tabs fade when the next section slides over it. The `tabs` block and the theme-default `cards` block get a line above the heading («Above the heading»), styled as in `text-tiles`. Off and empty, both print what they printed before; an existing site takes the new fields with `webx:blocks:import --path=vendor/webx-ui/widgets/resources/blocks --replace=tabs` and `webx:blocks:import --theme --replace=cards`.
