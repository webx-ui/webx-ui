# @webx-ui/module-inbox

## 0.6.2

### Patch Changes

- 52f4e14: The form's Antispam tab says how the site runs the chosen captcha and which key type it needs (reCAPTCHA v2 Checkbox, v2 Invisible or v3; Turnstile on load or invisible). It says that the keys live in the site's `.env`, and warns when they are missing.
- 52f4e14: Inbox panel: status names (tabs, badges, the status select, the statuses screen) follow the panel's language, with the content language and then any line as fallbacks; the drag handles of the forms, fields and statuses lists are named in the module's own words ("Reorder: …"); the submissions list is requested once on opening instead of twice.
- 52f4e14: The submissions list says "Loading" in the panel's language. The section asks for the statuses once instead of once per component. A corrected answer appears in the log. The field dialog shows the server's refusal of a list field with no choices, and the notification settings show a refused reply-to field. «Embedding» tells a site with Blocks to put the tag into a block template in the panel, and shows `:values` and `placement`.
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
- Updated dependencies [52f4e14]
  - @webx-ui/core@0.38.0
  - @webx-ui/module-admin@0.23.5

## 0.6.1

### Patch Changes

- a814314: `WxInputNumber` in a form item is at most 240px wide everywhere, not only in screens: in a
  hand-written form a number stretched across the card with its − and + a line apart. Bare — in a
  filter row or a table cell — it keeps the width it is given. The screens' own cap and the field
  dialog's are gone, the core's covers both.
- a814314: The redirects table shows the code as the audit does: a badge, 301 calm and 302 orange, with what
  it means on hover. In a form's field dialog a number — the answer's length, a size, a count of
  choices — is 200px wide instead of stretching across the dialog, and the field's ··· says «Edit»
  rather than «Field».
- a814314: A switch says what it turns beside itself, not in a heading above it. In screens a `wx-switch` or
  `wx-checkbox` node hands its label to the control (`labelProp` on a field type), and the form
  item keeps the help and the error; the hand-written forms of SEO, the inbox and the landings'
  filters follow.
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
- Updated dependencies [a814314]
  - @webx-ui/core@0.37.1
  - @webx-ui/module-admin@0.23.4

## 0.6.0

### Minor Changes

- 1d9ca51: A submission now says whether its notification left, not only whether it was handed to the queue.
  On a site with a queue the letter is marked `queued` until a worker has sent it (`notified_at`, a
  `notified` line with the count) or given up on it (`notify_error` with the reason, a
  `notify_failed` line with the address); each recipient is listed with how its letter went. On
  `sync` nothing changes. The panel's submission screen, `inbox_get` and `inbox_list` show the state
  (`none`, `queued`, `delivered`, `failed`); an administrator can send the notification again from
  the submission's menu, through `POST …/submissions/{id}/notify` or with the new MCP tool
  `inbox_notify` (`dry_run` first). With the site audit installed, the check `inbox.notification`
  lists letters that failed or have been queued for long — a sign the mail settings are wrong or no
  queue worker runs. Run the migrations: two nullable columns are added to `inbox_submissions`.
- 1d9ca51: A form that would notify nobody now says so instead of looking like a queue that has not run.
  `module-inbox` reports, beside the stored `options.recipients`, who a submission would actually
  be written to: `recipients` (each with `receives` and a `problem` — `admin_deleted`,
  `admin_inactive`, `invalid_email`) and `notifies`, in the panel API and in the MCP tools
  `inbox_forms_list` / `inbox_form_get`. A submission of such a form logs `no_recipients`. The
  panel warns in the form editor and marks the form in the column; the editor opens on the tab
  named by `?tab=`. With `webx-ui/module-audit` installed, the check `inbox.no_recipients`
  (warning) lists switched-on forms that would notify nobody, with a link to their Notifications
  tab. The stored option shape is unchanged.
- 1d9ca51: A site can act on a stored submission without forking `module-inbox`. `SubmissionStored` is
  dispatched once the submission, its answers and its files are written (after commit), for the
  site's form and one typed in by hand, never for what the antispam stopped. For the common case,
  `handlers` in `config/webx-inbox.php` names `SubmissionHandler` classes by form slug or `*`: each
  runs as a queued job of its own, and its outcome shows in the submission's log in the panel and
  in `inbox_get` as "Handed to …" or "… failed: reason". A failing handler never reaches the
  visitor or stops the others.

## 0.5.7

### Patch Changes

- Updated dependencies [3dfe40a]
  - @webx-ui/core@0.37.0
  - @webx-ui/module-admin@0.23.2

## 0.5.6

### Patch Changes

- Updated dependencies [62497fc]
  - @webx-ui/core@0.36.0
  - @webx-ui/module-admin@0.23.1

## 0.5.5

### Patch Changes

- 41f2059: `WxTable` takes `selectRowLabel` and `selectAllLabel` for its checkboxes, and every selectable list of the panel passes them translated (`filters.select-row`, `filters.select-all` in `module-admin`). The history shows a list of names as the names, comma-separated, instead of JSON. The catalogue's list of products warns when the database engine is past `sql_engine_limit`; the tree of categories shows an address without the trailing slash; the category picker of a bulk action says «Choose a category».
- Updated dependencies [41f2059]
  - @webx-ui/core@0.35.0
  - @webx-ui/module-admin@0.23.0

## 0.5.4

### Patch Changes

- Updated dependencies [3a08c6c]
- Updated dependencies [67e44aa]
  - @webx-ui/module-admin@0.22.0
  - @webx-ui/core@0.34.3

## 0.5.3

### Patch Changes

- Updated dependencies [38c5b08]
- Updated dependencies [5ebd999]
  - @webx-ui/module-admin@0.21.0
  - @webx-ui/core@0.34.2

## 0.5.2

### Patch Changes

- Updated dependencies [2ba4290]
  - @webx-ui/module-admin@0.20.0

## 0.5.1

### Patch Changes

- Updated dependencies [d1ff63d]
  - @webx-ui/module-admin@0.19.0

## 0.5.0

### Minor Changes

- 0b70a2f: A submission says where its form stood: «Placement: footer» in its card, and a filter by
  placement in the list once a form has come in from more than one place.

## 0.4.12

### Patch Changes

- acc5de9: Turning a page of articles or submissions sends one request, not two. The watcher over the filters
  read them through one getter that returned a new array each time, so every change of the address —
  the page turn included — fired it, and a second request without `per_page` raced the table's own.

## 0.4.11

### Patch Changes

- Updated dependencies [50ea8a5]
- Updated dependencies [85b8fca]
  - @webx-ui/core@0.34.0
  - @webx-ui/module-admin@0.18.1

## 0.4.10

### Patch Changes

- Updated dependencies [da138e0]
- Updated dependencies [da138e0]
  - @webx-ui/module-admin@0.18.0

## 0.4.9

### Patch Changes

- Updated dependencies [c7b0084]
- Updated dependencies [c7b0084]
- Updated dependencies [c7b0084]
  - @webx-ui/module-admin@0.17.0
  - @webx-ui/core@0.33.2

## 0.4.8

### Patch Changes

- Updated dependencies [298c894]
  - @webx-ui/module-admin@0.16.0

## 0.4.7

### Patch Changes

- Updated dependencies [42d427e]
  - @webx-ui/module-admin@0.15.0

## 0.4.6

### Patch Changes

- Updated dependencies [2071b2d]
  - @webx-ui/core@0.33.0
  - @webx-ui/module-admin@0.14.4

## 0.4.5

### Patch Changes

- Updated dependencies [a0556f1]
  - @webx-ui/core@0.32.0
  - @webx-ui/module-admin@0.14.1

## 0.4.4

### Patch Changes

- Updated dependencies [8e0d587]
- Updated dependencies [8e0d587]
  - @webx-ui/module-admin@0.14.0
  - @webx-ui/core@0.31.0

## 0.4.3

### Patch Changes

- b24f7d1: The snippet the Embedding tab hands an editor is the renamed tag: `<x-webx-inbox::form slug="…" />`

## 0.4.2

### Patch Changes

- Updated dependencies [f623fac]
- Updated dependencies [cd95a2e]
- Updated dependencies [b1aeb52]
  - @webx-ui/module-admin@0.13.0
  - @webx-ui/core@0.30.0

## 0.4.1

### Patch Changes

- Updated dependencies [0a506df]
  - @webx-ui/core@0.29.0
  - @webx-ui/module-admin@0.12.2

## 0.4.0

### Minor Changes

- cca572f: The inbox opens on the submissions, and the forms are the chooser

  Somebody opens "Inbox" to see what has come in. What the section showed them was a column of
  three form names, and on a phone that column was the whole screen: the submissions were a
  record opened beside it, so they lived in the drawer and the reader had to pick a form before
  seeing anything at all.

  The two swap places. The forms are the `filters` column of `WxListDetail` — the thing that
  narrows the list — and the submissions are the list. Nothing moves on a wide screen: the
  forms are still 270px down the left. On a narrow one it is the forms that fold, into a panel
  raised by a **Forms** button in the head of the submissions, and the list is the screen. One
  form is always open — the first, unless the address names another — which also covers an
  address naming a form that has since been deleted.

  `WxListDetail` grew the case that makes this possible: with no `detail` slot, the list is the
  main pane rather than a fixed column with an empty pane beside it, and the only threshold left
  is the chooser's, `filtersWidth + detailMin`. It is the shape for a list whose records open on
  a route of their own — which is what a submission does, and what a file in a library does.

  Gone from the head of the submissions: **Settings**. It is an action on the form, and the
  form's own `···` in the list of forms already offers it beside Duplicate and Delete — a second
  door on the same strip, one word away from the list it was not about. `panel.choose-form` goes
  with it on both halves: there is no longer a moment with no form chosen.

  The button in the head is now **New submission**. The section is opened to read what came in
  dozens of times for every once a form is added, and what stood there in blue was the form: a
  new form is the `+` over the list of forms, beside the things it makes one more of, and in the
  drawer — where an icon alone under the drawer's heading reads as a stray mark — it is a button
  with the word on it. The dialog stays with the list of submissions and is exposed to the head,
  because what is written has to land in that list, in the filter that is on, and be counted in
  its tabs. On a narrow screen **Forms** joins it up there, so the two ways out of the list stand
  together instead of one being in the head of the section and the other in the head of the pane.
  `panel.new-submission` reads "New submission" rather than "Add by hand" on all ten dictionaries;
  the dialog it opens still says which case it is for.

  `WxListDetail` says `filters-inline` whenever the chooser's column appears or folds, and once at
  the start. The `list` slot has always been handed that as a slot prop, but a head that stands
  outside the pane — above the card, where a screen's actions live — cannot read one.

  One inset, kept by the pane. The name of the form, the tabs, the search box and the rows now
  all begin on the same line down the left: the table added a step of its own inside the pane's,
  which is exactly what `flush` says it should not, and on a phone that put the head at 17 and
  the list at 33 — two panels stacked rather than one screen. The change is a rule removed from
  this screen, so no other list in the panel moves.

  What scrolls is now the page. The section used to be as tall as the window with the rows
  scrolling inside a box of their own: a bar down the middle of the screen, and a wheel that
  meant one thing over the rows and another an inch to the left. Every other list in the panel
  scrolls as a page, and this one does too — the card is as tall as what is in it.

  A switched-off form is said by its name, struck through and grey, instead of by a badge
  beside it. The badge did not shrink, so in a 270px column already holding a name, a count and
  a `···` it ran under the menu — measured at 396px against a row ending at 346 — and it said in
  a word what the type says at a glance. The strike is on the name only: the count beside it is
  still true.

  Fixed on the way: between 640 and about 672 pixels the pane and the table measured the same
  threshold a step apart — the pane's own padding stood between them — and the table drew cards
  out of the full set of columns, five lines of "Label: value" for one enquiry. The pane decides
  now and the table is told, so a tablet holds fourteen rows where it held three cards.

### Patch Changes

- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
  - @webx-ui/core@0.28.0
  - @webx-ui/module-admin@0.12.1

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
