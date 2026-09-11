---
'@webx-ui/tokens': minor
---

Inter, self-hosted, as an opt-in — and an honest default without it.

The type token named `Inter` first and the library shipped no way to get it: an admin panel
rendered in Inter on a machine that happened to have it installed and in the platform's font on
every other, which is the worst of both. The stack is system-first now, and Inter arrives with one
import:

```ts
import '@webx-ui/tokens/fonts.css'
```

That file brings the faces from `@fontsource-variable/inter` (SIL Open Font License) and points
`--wx-font-family-sans` at them. Variable, split by alphabet — a Cyrillic page downloads the
Cyrillic file and nothing else — served from your own origin, with `font-display: swap`. Skip the
import and you get Segoe UI, San Francisco or Roboto, which costs nothing and never looks foreign;
take it and every admin built on the library reads the same on every operating system.
