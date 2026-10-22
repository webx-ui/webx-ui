---
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
'@webx-ui/php': patch
---

Rich text in panel forms stops at the form's column like the fields above it, instead of
stretching across the whole screen. `WxRichText` gains a toolbar button that opens the same
editor over the whole window — caret, selection and language kept; the button or Esc gives the
form back (`expandable`, `expand` event, `expand`/`collapse` labels). The panel translates the
button into all ten languages.
