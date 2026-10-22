---
'@webx-ui/php': minor
'@webx-ui/module-catalog': minor
'@webx-ui/module-catalog-labels': minor
---

A catalogue label can have a colour of its own besides the six tones: `color` = `#rrggbb`, and `text_color` for the text on it — black or white by WCAG contrast when left empty. The editor offers «Own colour» next to the tones with a colour picker and a live preview; the panel's list of products draws the label in its colours; the card prints `wx-catalog-badge--custom` with `--webx-label-bg` / `--webx-label-fg` on the element, read by the new base stylesheet `resources/theme/catalog-labels.css`. `catalog_labels_create` takes `values` now (any category kind's `_create` does), and the labels' tools describe both fields. Stock statuses stay on tones. New migration: `text_color` on `catalog_labels`.
