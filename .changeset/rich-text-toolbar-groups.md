---
'@webx-ui/core': patch
'@webx-ui/tokens': patch
'@webx-ui/php': patch
---

`WxRichText`'s toolbar wraps a group at a time: no divider is left at the start or the end of a
line, and every button stays on screen. The expand button follows the last group instead of
standing alone at the far end, and the language chip sits on the middle of the first line of
buttons. In a form the editor stops at the new `--wx-size-field-wide` token (768px) rather than at
the 640px of an input — the whole toolbar, language chip included, is one line there; a narrower
card still wraps it. Ordinary fields keep 640px, now also a token, `--wx-size-field`.
