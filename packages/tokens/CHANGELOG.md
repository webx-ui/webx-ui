# @webx-ui/tokens

## 0.2.0

### Minor Changes

- acd2c88: Inter, self-hosted, as an opt-in — and an honest default without it.

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

- 9005935: Controls read at 14px, and the size is a token now

  Every control — input, textarea, number, select, tags, autocomplete, cascader,
  colour, the date fields and their calendar, checkbox, radio, switch, rate, button —
  took its text size straight off the body scale, which put the default at 16px. That
  is a size for reading paragraphs. An admin panel is a page of controls, and beside
  navigation at 14px they were reading a size too large.

  The scale drops a notch: `md` from 16px to 14px, `sm` from 14px to 12px, `lg` from
  18px to 16px. Control heights are unchanged — `data-density="compact"` is still the
  way to tighten those.

  The size is no longer read off the body scale at all. `@webx-ui/tokens` gains
  `--wx-font-size-control-sm`, `--wx-font-size-control-md` and
  `--wx-font-size-control-lg`, and every control points at them, so retuning how
  controls read is three lines in a stylesheet rather than an override per component.

## 0.1.0

### Minor Changes

- 8e45eb9: Adopt the WebX admin visual language in the token layer: real palette with `base` / `hover` /
  `active` / `disabled` / `soft` states per accent colour, pixel-keyed spacing scale, 42px comfortable
  controls with a `wx-density-compact` override, 10px control radius and 16px card radius, and a
  tinted focus border instead of a ring. `WxCard` is now borderless with a soft shadow by default —
  `borderless` is replaced by `bordered`.

## 0.0.2

### Patch Changes

- 16106cb: Bootstrap the monorepo: design tokens generated from JSON, `WxButton` / `WxInput` / `WxCard`, schema
  contracts, VitePress documentation and the CI / release / docs pipelines.
