---
'@webx-ui/core': patch
'@webx-ui/module-seo': patch
'@webx-ui/php': patch
---

A note under a field is smaller than its label, and a group of fields has a card

`WxFormItem` sets the help text and the error to `--wx-font-size-xs` — 12 against the label's 14.
They used to share a size and differ only in weight and colour, so a two-line note read as a
paragraph of its own and the eye lost the seam between one field and the next. The error moves
with the help text rather than staying at 14: it takes the help text's place, and a line that
jumps a size on the first failed save is worse than either size.

The SEO tab of a page gets the card it never had. It arrives as a patch from `module-seo`, so
the fix is in the patched node: the `wx-seo` field now travels inside a `wx-card`, and the tab
stops being the one place in the panel where fields lie straight on the page background. The
card holds SEO's own three sub-tabs — one card, tabs inside it, nothing nested.

The snippet preview inside that card also gets its frame back. It asked for
`--wx-color-border`, `--wx-color-surface-sunken`, `--wx-color-text-muted` and `--wx-color-text`,
none of which are tokens; a name that does not exist resolves to nothing without complaint, so
the box had no border, no background and no colour of its own.
