---
'@webx-ui/core': minor
---

Five icons for the shape of a screen, and a state box that fits what it holds

`WxActionBar` asked its state 220px wide whatever was in it, which is right for a sentence and
wrong for everything else: a state that is one small mark took a line of its own on a phone and
left an empty strip above the buttons, because 220 plus two buttons does not fit 375. The basis
is the content now, and `min-width: min-content` is what keeps a sentence from being crushed —
it wraps onto its own line instead of overflowing the buttons, which is what the `0` it replaces
used to let it do. Measured on a 375px screen: the bar 60px tall with a mark in it, 106 with the
old long line, and the line above the buttons rather than across them.

`monitor`, `tablet`, `smartphone`, `maximize` and `minimize`. The set had nothing for any of them —
`phone` is a telephone handset, and there was no way at all to draw "full screen" — so the width
switcher of the blocks preview had to spell out three device names in a bar with no room for them.

The tablet and the phone are told apart by their proportions and not by any detail, because at one
em a detail is a smudge: 13 wide against 8, which is a difference that survives the size. The two
corner icons are the usual four brackets, pointing out of the picture and back into it.
