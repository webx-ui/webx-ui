# @webx-ui/module-seo

## 0.3.1

### Patch Changes

- 48dfd9e: One head for every screen of the panel

  Eight screens each answered "what goes at the top" on their own, and gave eight answers: the
  heading at three sizes, the way out as an arrow on four of them and as a line of breadcrumbs on the
  rest, the buttons folding into a `···` on two editors and wrapping onto a third line everywhere
  else. Writing a new screen meant writing that line again and getting it slightly different again.

  `WxScreenHead` is that line, once: the way out, the name with the state said beside it, the line
  under it that says which record this is, and what can be done here. `WxListScreen` is built on it,
  so a list and the editor a row opens are the same object rather than two similar ones — and it
  takes `back` now, which is what the statuses screen used to draw above its own heading for want of
  anywhere to put it.

  **The actions are declared rather than drawn.** The same action has to be a button on a desktop and
  a line of a menu on a phone, and one vnode cannot be mounted in two places — as markup it had to be
  written twice, which is exactly what the page and article editors did. As `ScreenAction[]` it is
  written once: `primary` is the one thing the screen exists for and the one that keeps a button when
  the head runs out of room, `danger` is never a button at all, `menu` is in the `···` at every
  width, and `loading`, `disabled` and `href` mean what they say. Below 720px — 480 on a list, which
  carries one word and no trail — everything but the primary folds behind the `···` and that primary
  takes the line under the name, full width.

  The name’s line is the head: the way out at the start of it and the actions at the end, both
  centred on it however many badges stand beside the name. The trail is the line above, and it
  scrolls sideways with no scrollbar showing rather than wrapping — on a phone a path four levels
  deep was two lines of the smallest type on the screen, standing between the reader and the name of
  what they had opened.

  Two things that were quietly wrong come out with it. Nineteen buttons across the panel passed
  `icon="plus"` to `WxButton`, which has no such prop: the attribute landed on the `<button>` and
  drew nothing, so the panel’s main actions had no icons at all. And the `···` said `More` in English
  in every language, because the core carries English defaults and knows no dictionary — the panel
  gives it the word now, in all ten.

- Updated dependencies [a9383bb]
- Updated dependencies [b6a09a6]
- Updated dependencies [48dfd9e]
  - @webx-ui/core@0.26.0
  - @webx-ui/module-admin@0.11.0
  - @webx-ui/schema@0.3.3

## 0.3.0

### Minor Changes

- 852883d: The panel's lists take their filters behind the funnel and draw their narrow rows as entities.

  `WxFilterChips` and `AppliedFilter` in `module-admin` give every section the same chip, and the
  panel's own two words — the name of the funnel and "reset all" — live with it in all ten
  languages. Articles, the SEO rules and the administrators put their dropdowns in `#filters` and
  what they are set to in `#applied`; submissions, administrators and articles draw a card below
  their breakpoint as `WxEntityCard` rather than as a stack of labelled lines, with the `···` in
  the card's own top strip beside the checkbox.

  `WxEntityCard` gained `titleLines`, because an article's headline is a sentence: one line of it
  on a phone is half a thought, and the list it replaced already clamped at two.

### Patch Changes

- 852883d: `rowMenuWidth` says how wide a column holding a `···` has to be, and every list reads it. The menu
  is a finger target — 44px under `(pointer: coarse)` — and the cell keeps 16 on either side of it,
  so the 56 the sections declared was never enough: the button painted outside its column, which
  nothing said out loud until cells began to clip what does not fit.

  On the tags screen the selection bar keeps the one button it exists for and puts the other three
  behind the same `···` a row has. The × that cleared the selection is gone: a button whose whole
  job is to undo something harmless, standing beside a red "Delete", read as a way to close the bar.

- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [937f4e2]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
- Updated dependencies [852883d]
  - @webx-ui/core@0.25.0
  - @webx-ui/module-admin@0.10.0
  - @webx-ui/schema@0.3.2

## 0.2.6

### Patch Changes

- Updated dependencies [f87e4ec]
- Updated dependencies [f87e4ec]
  - @webx-ui/core@0.24.0
  - @webx-ui/module-admin@0.9.0
  - @webx-ui/schema@0.3.1

## 0.2.5

### Patch Changes

- 74d1369: A round of panel fixes, mostly from looking at the two demo sites on a phone.

  - A field stops at a width it can be read at: `WxFormItem` caps its control at
    `--wx-field-max-width` (640px), and what is not a field in that sense says `wide` — a prop on
    the item, `wide: true` on a registry entry.
  - A hovered table row and the `···` at its end no longer paint themselves the same grey: the row
    goes a tone softer, and the row menu carries no fill at rest.
  - Tooltips never open on a touch screen, where the tap that opens one is the tap that was meant
    for the button under it. `useHoverPointer()` is the question, asked once for the application.
  - The panel's step reaches what the panel teleports out of itself — drawers, dialogs, the toaster
    — so a phone no longer lays one screen out with desktop air.
  - Blocks: a row of the tree offers its actions as the panel's `···` rather than three icons that
    only appeared on hover, and removing a block always asks first.
  - Inbox: the form editor no longer draws its save bar across the middle of the form, the section's
    panes stay inside the card's corners, the recipient's bin is red and asks, and a submission
    keeps its notes, its log and its metadata in one card with three tabs instead of three cards.

- Updated dependencies [74d1369]
  - @webx-ui/core@0.23.0
  - @webx-ui/schema@0.3.0
  - @webx-ui/module-admin@0.8.0

## 0.2.4

### Patch Changes

- Updated dependencies [4644d28]
- Updated dependencies [4644d28]
  - @webx-ui/module-admin@0.7.0

## 0.2.3

### Patch Changes

- Updated dependencies [ad9ead7]
  - @webx-ui/module-admin@0.6.0
  - @webx-ui/core@0.22.0
  - @webx-ui/schema@0.2.3

## 0.2.2

### Patch Changes

- 738a7e9: A note under a field is smaller than its label, and a group of fields has a card

  `WxFormItem` sets the help text and the error to `--wx-font-size-xs` — 12 against the label's 14.
  They used to share a size and differ only in weight and colour, so a two-line note read as a
  paragraph of its own and the eye lost the seam between one field and the next. The error moves
  with the help text rather than staying at 14: it takes the help text's place, and a line that
  jumps a size on the first failed save is worse than either size.

  The SEO tab of a page gets the card it never had. It arrives as a patch from `module-seo`, so
  the fix is in the patched node: the `wx-seo` field now travels inside a `wx-card`, and the tab
  stops being the one place in the panel where fields lie straight on the page background. The
  card holds SEO's own three sub-tabs — one card, tabs inside it, nothing nested.

  The snippet preview inside that card also gets its frame back. It asked for
  `--wx-color-border`, `--wx-color-surface-sunken`, `--wx-color-text-muted` and `--wx-color-text`,
  none of which are tokens; a name that does not exist resolves to nothing without complaint, so
  the box had no border, no background and no colour of its own.

- 2e27380: Lists that mean what they show: a tree stays a tree, a row promises only what it does, and
  anything that cannot be undone asks first.

  **The page tree is a table at every width.** Below 640px it used to become cards, and a card has
  no indentation to read and no chevron to open — so the section quietly asked the server for a flat
  list instead, and a phone had no tree at all. The cards were the mistake, not the tree. The table
  now drops columns as the width goes: when it was last touched, then what state it is in, then
  where it lives, until a row is its title and its `···`. Measured at 375px: 65px a row against
  250px a card, ten pages on screen instead of three and a half, with the chevron still opening
  branches.

  **`WxTable` takes a `clickable` prop.** It still infers the answer from whether anybody listens
  for `row-click`, which is right for an ordinary list and needs nothing said. It is not right for a
  list whose rows stop leading anywhere while it is on screen — the bin of `Pages`, an archive, a
  picker taking several rows at once — because the listener a component was rendered with cannot be
  read again. `:clickable="false"` withdraws the whole promise: no pointer, no highlight, and no
  `row-click` either. The bin, the two SEO lists for a reader who may not edit them, and the
  administrator picker in multiple mode all say so now.

  **One place decides what a failed request says.** `useErrorText()` turns an error into a sentence
  in the panel's language. A 422 is repeated word for word — every refusal that reaches one is
  written by a module to be read — and every other status gets the panel's own words, so clicking a
  page somebody else deleted says "It is not there any more" rather than
  `No query results for model [WebxUi\Pages\Models\Page] 8`. Twenty-odd places that printed the
  server's `message` now go through it, and `webx-admin::errors` ships the lines in ten languages.

  **Confirmations, in numbers.** Restoring from the bin, publishing a page, moving a branch by drag
  or by the "Inside" picker, deleting a block that holds others and deleting an empty media folder
  all ask now, and the question carries the consequence as a figure: how many pages come back, which
  address the page starts answering at, how many addresses a move rewrites, how many blocks go with
  the one being removed. A move of a single page stays a gesture and asks nothing, because a redirect
  is left on every address a move vacates — there is no undo to offer, only a second move.

- 738a7e9: One shape for every list in the panel

  Five sections each answered "where does the heading go" on their own, and there were five
  answers: a heading inside the card on `Administrators`, two headings on `SEO`, a search outside
  the card on `Blocks`, no heading at all on `Files`, a bare red bin in every row here and a menu
  there. They are one shape now — the section's name on its own line, the one action it exists
  for beside it, the views of the list as tabs under that, and a card holding nothing but the
  rows. The search stays inside the table: it narrows the rows, not the screen.

  `WxListScreen` in `module-admin` is that frame, and it is a screen node type — `wx-list` — so
  the next section describes its list rather than writing a sixth copy of the same markup.

  `WxTabs` grew an `items` mode for it: the strip is built from a list and the default slot is the
  **one** panel under it. A view is a different question to the server, not a different panel, so
  nothing is unmounted on a switch and the table keeps its search, its page and its scroll.
  `collapseBelow` folds the strip into a single switch labelled with the open view when its own
  container gets narrow — not into three dots, which in this panel mean actions.

  `WxRowMenu` is the other half: a `···` at the end of every row in every list, even for a single
  action. It orders the destructive one last, behind a rule, in red, and what somebody has no
  right to is left out rather than greyed. Underneath it is `WxActions` with the new
  `collapse="always"`, which never builds the row of icons at all — so the width of a table cell
  stops deciding whether a row has a menu. `WxFileCard` takes the same choice as `actionsMenu`,
  which is how a single file in the library gets one.

  Uploading is the media library's main action now: a filled blue button with a word on it in the
  line of the heading, rather than the third grey icon in a row of six. Blue, because green in
  this system means "it worked". Inside the picker dialog the toolbar keeps its upload icon —
  there is no screen around it there.

- 738a7e9: One step for the whole panel: cards, grids and forms read `--wx-gap`

  The panel's spacing step — 8 on a phone, 12 on a tablet, 16 on a desktop — used to space the
  frame alone. It now spaces everything: the air inside a card and between the things in it, the
  gap between the fields of a form, the gutter of a grid, the space between the strip of tabs and
  what it switches. Where there is no panel around them, the components fall back to 16, which is
  what they had.

  Two things change on their own account. A form's `gap="md"` is 16 rather than 24, so a form laid
  out by a card and a form laid out by itself finally agree. And a tab is now a column that spaces
  what it holds — two cards in a tab used to stand flush and read as one.

- 30a3d30: One way for the panel to say when something happened

  `module-admin` gains `useDates()` and `WxDate`, and every section that showed a date now calls
  them: "today at 08:10", "yesterday at 14:03", "16 September at 14:03", "16 September 2025",
  "never". Twenty-four hours and no seconds in the column; the exact moment stays in a tip and in
  `<time datetime>`.

  There were three formats on three screens before this, all of them `toLocaleString()` with no
  locale — so a panel drawn in Russian dated its rows in American order, with `AM/PM` and seconds
  nobody wanted. The month names and the order of the parts now come from `Intl` in the panel's
  own language; only `today`, `yesterday` and `never` are translated by hand, and the word for
  "never" moved out of the two modules that each had their own copy.

  "Today" is counted in the reader's calendar day rather than in UTC, which is what the server
  sends: the small hours of a morning are today to the person reading them. Sorting is untouched —
  a column still sorts on the value the server sent, never on the words.

- Updated dependencies [738a7e9]
- Updated dependencies [2e27380]
- Updated dependencies [738a7e9]
- Updated dependencies [738a7e9]
- Updated dependencies [30a3d30]
- Updated dependencies [738a7e9]
- Updated dependencies [e93ae5b]
- Updated dependencies [a16ff45]
- Updated dependencies [046c6ba]
  - @webx-ui/core@0.21.0
  - @webx-ui/module-admin@0.5.0
  - @webx-ui/schema@0.2.2

## 0.2.1

### Patch Changes

- Updated dependencies [2c2c2ba]
  - @webx-ui/core@0.20.0
  - @webx-ui/module-admin@0.4.2
  - @webx-ui/schema@0.2.1

## 0.2.0

### Minor Changes

- f98786f: The redirects nobody wrote, beside the ones an editor did

  A third screen in the SEO section, **Automatic**: the addresses a rename left behind. Renaming a
  page or moving a branch leaves its old address answering 301, which is what carries a bookmark, an
  inbound link and a search result through an edit in the panel — and a reader who lands on a dead
  address does not care which half of the system answered, so an editor chasing one should not have
  to either.

  Read only, and not for want of a form: an alias belongs to the entity that moved, which makes it,
  repoints it on the next rename, and takes it away when it is deleted. A screen that could edit one
  would be a screen that can make the registry disagree with the site. To give an old address a
  different answer, write a rule on the **Redirects** tab — rules are tried before routing, so yours
  wins and the alias underneath stops mattering.

  Which is why the redirect form now says when the address it is about to take over is a live page of
  the site. It says it and saves anyway: shadowing a page is a legitimate thing to want, and refusing
  it here would make the common case impossible to express. **Check an address** answers the same
  question for any address you type into it.

  Needs `webx-ui/routing` on the server, which `webx-ui/module-seo` now requires.

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
