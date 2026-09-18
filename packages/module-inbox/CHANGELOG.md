# @webx-ui/module-inbox

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
