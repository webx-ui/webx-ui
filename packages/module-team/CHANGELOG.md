# @webx-ui/module-team

## 0.1.1

### Patch Changes

- 6686e25: Tails from the last five modules. `WxInputNumber` with `precision` shows a whole number bare
  (`1380`, not `1380.00`) and a fraction to the precision (`1380.50`); the model is untouched. A
  refusal of a repeater row (`duties.1.text.ru`) is drawn under that row only, no longer a second
  time under the repeater as a whole. Ctrl+S in the editors saves also when a click on empty space
  left the focus on `<body>` — `useBodyKeys` in `module-admin`.
- Updated dependencies [6686e25]
  - @webx-ui/core@0.34.1
  - @webx-ui/schema@0.6.4
  - @webx-ui/module-admin@0.18.2

## 0.1.0

### Minor Changes

- 1966d54: `@webx-ui/module-team` — the team section of the panel: people with a photo, a job title, a short
  text, social links and the services they do, in one order dragged by hand. A list and the form of
  one person side by side (`?member=`), a new person written before they exist and made on the first
  save, the bin, `Ctrl+S`, and a question before unsaved words are left. One menu entry, no group,
  no categories.
