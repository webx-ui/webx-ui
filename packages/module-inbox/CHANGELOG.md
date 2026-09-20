# @webx-ui/module-inbox

## 0.3.2

### Patch Changes

- Updated dependencies [537df98]
- Updated dependencies [537df98]
  - @webx-ui/module-admin@0.12.0
  - @webx-ui/core@0.27.0

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

- bfd7f62: Even air around the submissions list, one line in the tags bar.

  The step the pane keeps around its table was a side padding on the card view alone, so the cards
  stood further in than the search field above them and the rows below them, and the step showed as
  a disagreement. It is one padding on the table now, on all four sides and in both views, and none
  at all in the drawer, where the screen's edge is the boundary and the pane's own step is all the
  air the list needs. The scroll bar of the card list rides in that step rather than in the cards'
  own right edge, so the list has the same air on both sides.

  The tags selection bar stays on one line on a phone, and its `···` is the size of the button
  beside it. The bar keeps its state box at 220px so that a sentence about a draft has room to be
  read; "2 selected" does not need it, and asking for it put "Merge" and the `···` on a second line
  at every phone width. A row menu is 30px because it stands at the end of a row; in a bar it stands
  beside a button, and the pair read as a control and a leftover.

- 852883d: A cell keeps what it holds inside its own column. `table-layout: fixed` gives a column the width
  it was declared and nothing else, so a value wider than that used to be painted straight across
  the column beside it — measured on the panel, a date cell 130px wide with 152px of text, its tail
  sitting under the status badge. Cells clip now.

  `TableColumn.minWidth` says what it can and cannot do: a `<col>` takes four properties and
  `min-width` is not one of them, so the floor only means something with `layout="auto"`.

  The lists that showed it — articles, pages, tags and submissions — carry the widths their longest
  values actually need, and the columns that can be spared step aside a little later so that the
  name keeps the room.

- 852883d: `rowMenuWidth` says how wide a column holding a `···` has to be, and every list reads it. The menu
  is a finger target — 44px under `(pointer: coarse)` — and the cell keeps 16 on either side of it,
  so the 56 the sections declared was never enough: the button painted outside its column, which
  nothing said out loud until cells began to clip what does not fit.

  On the tags screen the selection bar keeps the one button it exists for and puts the other three
  behind the same `···` a row has. The × that cleared the selection is gone: a button whose whole
  job is to undo something harmless, standing beside a red "Delete", read as a way to close the bar.

- 852883d: `WxDate` has a column form. `compact` shows the time alone for today — it is the only row in the
  column wearing a clock, so it reads as today without spending a word on saying so — a short month
  for the rest of this year, and digits once the year has to be said. What it leaves out is in the
  tip, which is where "when exactly" was always answered.

  The lists use it, and their date columns went from 185px to 120: the full line is the reason the
  column had to be that wide in Russian and wider in German. The article covers take the smallest
  radius in the scale with it, the one `WxEntityCard` gives its own thumbnail — 12 on a box 32px
  tall reads as a pill.

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

## 0.2.1

### Patch Changes

- Updated dependencies [f87e4ec]
- Updated dependencies [f87e4ec]
  - @webx-ui/core@0.24.0
  - @webx-ui/module-admin@0.9.0

## 0.2.0

### Minor Changes

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

### Patch Changes

- Updated dependencies [74d1369]
  - @webx-ui/core@0.23.0
  - @webx-ui/module-admin@0.8.0

## 0.1.0

### Minor Changes

- 4644d28: `@webx-ui/module-inbox`: the section, the form editor and the statuses.

  One screen rather than two: the forms of the site in a column that is dragged into order, each
  with what is waiting in it, and the submissions of the chosen one beside them. The columns of
  that list are the fields of the chosen form, so a list of everything would have had no columns
  worth the name — and the two questions this section is opened to ask, "what is new" and "what
  does this form ask", are now one click apart instead of one screen apart. Which form is open
  lives in the address, so a link to it is a link somebody can send.

  The editor is five tabs and one save, with the fields as the exception: a question is a row
  with an identity that answers point at, so it is written in its own dialog, dragged into its
  own order and deleted softly. Its settings are the ones its type has and no others. The
  embedding tab prints the one line that puts the form on a page and the names the page fills
  its hidden fields by.

  `@webx-ui/module-admin` gains `landing` on a module and a route for `/`. Nothing answered
  there until now — the routes are the modules' and none of them claimed the root — so signing
  in landed on a blank page. The landing module is honoured once the manifest says the panel
  actually has it, and the first entry of the menu is the fallback.

- 4644d28: The submissions: the list, the card, and notes on any record of the panel.

  **`@webx-ui/module-admin` and `webx-ui/module-admin` — notes.** A record somebody can write a
  note on takes `HasNotes`, declares `Notable` and names the permission its notes are behind; the
  table, the endpoint and the `WxNotes` feed are the panel's own, so the next section that wants
  one — an order, a client — adds a trait rather than a copy. Two things the shared endpoint
  cannot be allowed to get wrong are closed in it: the type in the address is an alias of the
  morph map and never a class name, and the permission is the record's answer, never the
  controller's guess.

  **`webx-ui/module-inbox` — the panel's side of a submission.** One form's list, with its own
  `in_table` fields as columns and the counts of every tab travelling beside the rows; spam out of
  "all" and reachable by its own tab; a pile moved, marked or thrown away row by row, so the log is
  written and the attachments go with it. Beside it, one submission opened at an address of its
  own: the answers with the words they were asked in, the files, what the intake saw around it, the
  status and the assignee, the notes, the log, a reply by `mailto:`, and the arrows to the next one
  in the same filtered pile. Submissions can also be typed in by hand, through the same intake as
  the public door — a call that came by telephone lands in the same list, and carries none of the
  administrator's own browser and address as if a visitor had them.

### Patch Changes

- Updated dependencies [4644d28]
- Updated dependencies [4644d28]
  - @webx-ui/module-admin@0.7.0
