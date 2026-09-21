---
'@webx-ui/tokens': minor
'@webx-ui/core': minor
'@webx-ui/module-admin': minor
'@webx-ui/module-auth': minor
'@webx-ui/php': minor
---

Light, dark or the machine's — chosen in the account menu, stored against the person

The tokens have carried both themes since the beginning, and nothing in the panel ever wrote
`data-theme`: the only way to see the dark one was to set the whole machine to it. Now there is a
control, and the choice belongs to the person rather than to the browser — somebody who works
dark at night on a laptop finds the panel dark in the morning at a desk.

Three states rather than two. A toggle can say light and dark; it cannot say _I have not
decided_, which is the state almost everybody is in, because their machine has already decided
for them. `system` is a real answer and the one the switch starts on, and it goes on following
the machine afterwards — the panel darkens at sunset along with everything else on the desk.

- `WxThemeSwitch` — the control, in the core: three cells, a thumb that slides between them and a
  picture that arrives rather than appears. It is a radio group, the arrow keys move within it,
  and both animations stop under `prefers-reduced-motion`. Like everything in the core it ships
  English and knows nothing about a dictionary, so its three words are props.
- `applyTheme()` now takes `system`, which removes the attribute rather than writing a third
  value — the stylesheet already follows `prefers-color-scheme` for anything not pinned to light.
  `systemTheme()` and `watchSystemTheme()` are there for whatever has to _know_ rather than be
  painted. New `--wx-easing-emphasized`, a curve with a little overshoot in it.
- The theme contract now works both ways round. The tokens have always had a `data-theme="dark"`
  block and never a light one, so a light island inside a dark page — a preview, a printed
  sheet — inherited the dark values and quietly stayed dark, while the guide claimed a page could
  mix the two. There is a `[data-theme='light']` block now, and it can.
- `createAdmin()` builds the theme before it mounts, so the sign-in screen is already the colour
  this browser was left in, and `useTheme()` hands it to anybody who asks. The administrator's own
  record replaces the browser's guess the moment the session says who they are.
- `PUT /api/cms/auth/theme` and a `theme` column on `cms_users`, beside the language and for the
  same reasons. `null` means follow the machine — a choice, and one that has to travel between
  machines like any other.
- The Blade shell paints before its bundle runs: three lines that read the browser's copy, so a
  dark panel never starts white.
