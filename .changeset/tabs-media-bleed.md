---
'@webx-ui/php': patch
---

Widgets: the `tabs` block gets «Picture to the edges of the screen on a narrow screen» (`media_bleed`, off): where the column is narrower than 40em the picture of a tab runs from one side of the screen to the other, square, while the heading, the tabs and the text keep to the column; wider, nothing changes. It works with the slide over the section above and with the fade. Blocks saved before print what they printed before; an existing site takes the field with `php artisan webx:blocks:import --path=vendor/webx-ui/widgets/resources/blocks --replace=tabs --publish`.
