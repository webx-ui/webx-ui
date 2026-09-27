---
'@webx-ui/schema': patch
'@webx-ui/php': patch
---

A `wx-row` on a screen is as wide as one field, not as the card. Every field stops at
`--wx-field-max-width`, so a row across the card put "Start" at its left edge and "End" a
thousand pixels away, each still leaving most of its column empty. The row now splits one
field's width in two (columns answer to the row, so a pair of halves is `sm: 12`).

The event form uses it twice: start and end side by side, and the price with its number.
