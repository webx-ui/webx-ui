# @webx-ui/admin

## 0.1.0

### Minor Changes

- 266f47d: The shell paints the page it owns. Every state now carries `wx-root` — the class holding the
  font family, the text colour and the page background — so the panel no longer renders in the
  browser's default serif on whatever background the page happened to have. Where the panel is
  the whole page rather than a widget on one, the body's own margin is reset, and the centred
  plain layout is `border-box`, so its padding no longer added a scrollbar to a column exactly
  one viewport tall.

### Patch Changes

- Updated dependencies [752adf0]
  - @webx-ui/core@0.14.2
