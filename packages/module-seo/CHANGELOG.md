# @webx-ui/module-seo

## 0.1.0

### Minor Changes

- c92f42a: SEO in the panel: rules for addresses, redirects, and `wx-seo`

  `@webx-ui/module-seo` is the front half of the section, and the card that edits what a page says
  about itself. Two screens rather than two tabs — rules and redirects each have their own paging
  and their own search, and a tab that resets both on the way back is worse than a second address.
  Rules are listed in the order the site tries them, so reading the table top to bottom is reading
  what will happen.

  `WxSeo` is registered as the `wx-seo` field type on both halves: its value is everything a page
  says about itself as one object, so an entity's form gets the whole card from a patch the day it
  has somewhere to keep it. The text fields are language maps and grow the same chip every localized
  field in the panel does; the picture is not one, deliberately. The length counters are soft —
  search engines shorten what they shorten, and nothing here refuses a longer line.

  The share image comes in as `seo({ mediaField: WxMediaField })` rather than as an import, so the
  package does not depend on the library being installed.

  **Check an address** answers the question this section gets asked most — which redirect catches
  it, which rule matched, what each source contributed, what the page ends up with — in one call.

  The settings tab has moved out of every project's own patch and into the module, which makes it
  the first screen patch laid by a module rather than by a project. The guide is
  `apps/docs/guide/seo.md`.

### Patch Changes

- c92f42a: A card spaces what it holds, and a table's filters stop running off a phone

  Both showed up the moment the SEO settings tab put six fields in one card. `WxForm` hands its gap
  to its own children, and fields that land inside a card are not those: they stacked flush, and the
  hint under one read as the label of the next. A card's body and its two sidebar columns now stack
  what they hold with the same space the card keeps around it.

  The other one is `WxTable`'s header: a filter and a search field are each wider than a phone can
  spare, and an input will not shrink below its own intrinsic width — it overflowed sideways instead,
  and the whole page scrolled with it. The tools wrap now, and once the rows have become cards they
  are a column of their own, full width and all one height.

- Updated dependencies [c92f42a]
- Updated dependencies [0304791]
  - @webx-ui/core@0.19.0
  - @webx-ui/schema@0.2.0
  - @webx-ui/module-admin@0.4.1
