# @webx-ui/tokens

## 0.4.0

### Minor Changes

- b1aeb52: Light, dark or the machine's — chosen in the account menu, stored against the person

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

## 0.3.0

### Minor Changes

- ad9ead7: A pass over the panel: the chrome, the editors and the constructor.

  **The chrome.** The button that collapses the sidebar stands at the far end of
  the brand row instead of against the logo. The foot of an open sidebar says who
  is signed in rather than only showing them. The menu drawer keeps its width on a
  phone instead of covering the page — `WxDrawer` has `full-screen` for that — and
  icon buttons there are one size down.

  **Settings the panel wears.** A picture chosen in the library no longer vanishes
  from its field when the form is saved, and the logo in the corner changes with
  it: `AdminContext` gained `refreshManifest()`, which fetches a new manifest
  without the panel passing through `loading`.

  **`WxActionBar`.** The strip of body colour the bar painted in the gap below it
  is gone: it erased the part of the bar's own shadow that fell there, and a
  descendant is always on top of its ancestor's shadow. What shows through the gap
  instead is a sliver of the page still moving, which is what a bar floating over
  a scrolling page looks like.

  **Editors.** `WxBackButton` and `WxRenameButton` in `@webx-ui/module-admin`: a
  way out of a screen that opens one record, and renaming as an act rather than as
  typing into what looks like a heading. Both editors use them.

  **Tabs that hold a form.** Only the tab holding the constructor is a box of a fixed height
  with its own scrollbar; the others grow with their content and the page scrolls. A scroll box
  clips, and the cards inside one had their shadows cut off square at all four edges.

  **The constructor.** The preview is a picture of the page rather than the page:
  nothing in it navigates or submits, a click opens the block it landed in, the
  block under the pointer is outlined, and choosing one in the tree scrolls the
  frame to it.

  **Help.** `WxHelpButton` shows a page of Markdown from a module's own `lang`
  files — and `module-blocks` hands the identical page to an agent at
  `blocks://schema`.

  The page explains fields in more than one language, and writing it turned up that they
  did not work: a block with a `localized` field handed its template the whole language
  map, Blade refused to print an array, and the renderer caught that and printed nothing —
  the block vanished from the page. It is given one language now, down the same chain every
  localized value is read through.

  **The library.** A file can be downloaded from its card: `WxFileCard` takes
  `download-url`.

  **Smaller things.** A dialog puts air between whatever its body was given, so three stacked
  fields are not one block of controls. `WxSkeleton` is `border-box`, so a loader given padding
  no longer stands wider than the card it is in. And there is a guide to
  [languages](https://webx-ui.github.io/webx-ui/guide/languages).

  **Yourself.** `PUT auth/me` and the profile dialog behind the corner menu: your
  name, your photograph, your password — the last of those only with the current
  one.

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
