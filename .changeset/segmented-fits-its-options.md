---
'@webx-ui/core': patch
---

`WxSegmented` stays as wide as its options inside a column that stretches its children — a form
field's control is one, and the track ran the width of the input under it — and its labels no
longer cut off descenders such as the tail of a "g". `block` still fills the width.
