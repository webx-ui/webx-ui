# @webx-ui/module-blog

## 0.4.7

### Patch Changes

- Updated dependencies [2071b2d]
  - @webx-ui/core@0.33.0
  - @webx-ui/module-blocks@0.8.3
  - @webx-ui/module-admin@0.14.4
  - @webx-ui/schema@0.5.1

## 0.4.6

### Patch Changes

- Updated dependencies [6c45f08]
- Updated dependencies [b965650]
- Updated dependencies [7bdeb63]
  - @webx-ui/module-blocks@0.8.1
  - @webx-ui/schema@0.5.0
  - @webx-ui/module-admin@0.14.2

## 0.4.5

### Patch Changes

- Updated dependencies [76b4a71]
  - @webx-ui/module-blocks@0.8.0
  - @webx-ui/core@0.32.1

## 0.4.4

### Patch Changes

- Updated dependencies [fde8622]
- Updated dependencies [a0556f1]
  - @webx-ui/module-blocks@0.7.0
  - @webx-ui/core@0.32.0
  - @webx-ui/schema@0.4.0
  - @webx-ui/module-admin@0.14.1

## 0.4.3

### Patch Changes

- Updated dependencies [8e0d587]
- Updated dependencies [8e0d587]
  - @webx-ui/module-admin@0.14.0
  - @webx-ui/core@0.31.0
  - @webx-ui/module-blocks@0.6.5
  - @webx-ui/schema@0.3.8

## 0.4.2

### Patch Changes

- Updated dependencies [f623fac]
- Updated dependencies [cd95a2e]
- Updated dependencies [b1aeb52]
  - @webx-ui/module-admin@0.13.0
  - @webx-ui/core@0.30.0
  - @webx-ui/module-blocks@0.6.4
  - @webx-ui/schema@0.3.7

## 0.4.1

### Patch Changes

- Updated dependencies [0a506df]
- Updated dependencies [0a506df]
  - @webx-ui/module-blocks@0.6.3
  - @webx-ui/core@0.29.0
  - @webx-ui/module-admin@0.12.2
  - @webx-ui/schema@0.3.6

## 0.4.0

### Minor Changes

- cca572f: The address of an article is one row, not two

  The settings tab used to hold a field labelled "Address" and, directly under it, a row also
  labelled "Address" printing the whole thing. A full row of the form, and a second label, spent
  on one constant segment — `/blog/` — which taught the reader to skim both. The spec had asked
  for the other thing all along: "the address, with the prefix pasted on the left".

  So the prefix moves inside the control. `wx-article-slug` replaces the pair of `wx-input` and
  `wx-article-address`: a localized text field whose `#prefix` is the prefix of the blog, set in
  the same monospace face the address is read in, with the language chip still on the right. The
  whole address is now read and written in one place, and the card is a row shorter.

  What is kept is the part that is not a duplicate: the line that says an article on the site is
  about to answer at a different address and that the old one will keep working. It appears only
  when there is something to lose, and it still appears before the save rather than in a toast
  after it.

  Gone with the row: `WxArticleAddress` and the node type `wx-article-address`, and the words
  `article.address` and `article.no-address` on both halves. A project that patched the `address`
  node of `blog.article-form` has no node to patch any more — the id is not in the screen.

  The server registers `wx-article-slug` as the text type `wx-input` already was, so what a save
  is checked against does not change.

- cca572f: An article can be taken off the site from its editor, and its tags stand on one line

  **Off the site, from the publication card.** Taking an article off the site was a line in the
  `···` of a row of the list and nowhere else — so an editor looking at the article, on the tab
  where its day and its author are decided, had to go back to the list to pull it. Now
  `wx-article-unpublish` sits under the date, where the rest of the publication is settled. It is
  offered only while there is something to take off — a draft was never there, and one already
  off has nowhere further to go; the way back is "Publish", which stays in the bar. It asks
  first, because this is the one thing on that tab visitors see happen, and the question names
  what survives: the draft, the history and the rubrics all stay, and publishing puts the article
  back exactly where it was. A scheduled article gets its own sentence — it never went out, and
  the day it was set for will pass without it.

  **Tags.** A chip carried a `WxAction` in its slot, and an icon button of the panel is thirty
  pixels tall inside a badge whose words are fifteen: the chip grew to fit the button, the word
  sat three pixels below the cross it stood beside, and the air to the left of the word was half
  the air to its right. `WxBadge` has had `closable` all along, sized to the words — measured, the
  chip is 22.6 px instead of 37.6 and the drift is zero.

  **Rubrics.** The "main" badge stood against the name of the first rubric with nothing between
  them, because the cell a row's content goes into is a block and the `gap` meant for it was
  never applied — and neither was the clipping on the name, which had been written for a flex
  parent that was not there. The slot now makes a line of its own contents: eight pixels between
  the name and the badge, and a rubric with a long name is cut with an ellipsis rather than
  pushing the badge to the far end of the row.

- cca572f: Rubrics are one list and a dialog over it

  The section used to be a list beside a form, and the form took two thirds of a screen whose
  whole job is the drag: the order of this list is the order of the menu on the site. Now the list
  is the screen — grip, name, address, the number of articles, and a `···` with `Edit`, `Show its
articles` and `Delete` — and a rubric is edited in a dialog with three tabs: `Content` (the
  name, the address, the switch and the introduction), `Image` and `SEO`. One `Save` for all
  three, and a `422` opens the tab the failing field is on.

  The introduction is a rich text document now (`wx-rich-text`) rather than a line of plain text:
  cleaned by its own field type on the way in, printed with its library pictures resolved on the
  way out. Nothing migrates — the column is the same one, and a line of text is a document with no
  markup in it.

  Two things this fixes on the way: the SEO card used to open at zero width inside the old form,
  and the footer of that form broke apart onto three rows on a one-pixel overflow.

### Patch Changes

- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
  - @webx-ui/core@0.28.0
  - @webx-ui/module-admin@0.12.1
  - @webx-ui/module-blocks@0.6.2
  - @webx-ui/schema@0.3.5

## 0.3.0

### Minor Changes

- 0aea1bb: The article's action bar is two buttons and a mark

  It used to be three buttons and a sentence — "live since the eighteenth · edits waiting",
  "discard", "save draft", "publish" — which on a phone is three ragged rows, and on any width is
  a bar doing the head's job. Now the bar holds what is done here, and the head holds what this
  record is:

  - **the day moves under the name** as the head's subtitle. When the article goes out is a fact
    about the article, not about the last keystroke;
  - **"edits waiting" becomes the second badge** beside the state, exactly as the articles list
    already draws it — because "Live" is the same word for `published` and `modified`, and a
    colour on its own is not a statement;
  - **"Discard" leaves the bar for the `···`**, where `ScreenAction.menu` says destructive things
    belong: throwing away what was written is not something to keep one slip away from "publish".
    It is still offered only while there is a difference between what is written and what is live;
  - **"Save draft" becomes "Save"** — the button beside it is the publication, so there is nothing
    left to tell apart.

  Measured on a 375px screen: one row, 60px tall, which is what the page editor's bar has been all
  along.

### Patch Changes

- Updated dependencies [e98734c]
  - @webx-ui/module-blocks@0.6.1

## 0.2.2

### Patch Changes

- 537df98: `WxSaveState`: the save says so with a mark, and then stops saying it

  The bar of the page and article editors carried the word "Saved". It is right nearly all of the
  time, which is what makes it furniture: it is on screen when nothing is happening, and nothing is
  happening is exactly when nobody is asking. What anyone wants to know is whether _this_ save
  landed, and only until it has.

  So the word is a mark now: a wheel while the save is in flight, a green tick when it lands, and
  nothing two seconds later. Nothing for unsaved work either — the head already carries a badge
  beside the name, and the enabled save button is the plainest statement that there is something to
  save. The element keeps its place while it is empty, or the buttons beside it would shift by its
  width twice per save.

  The words stay for whoever is not looking at the bar: the mark is a live region carrying "Saving…"
  and then "Saved", which is what a screen reader hears. Its own `state-saved` and `state-saving`
  lines are gone from both modules, along with the `state-unsaved` that nothing says any more.

- 537df98: The content tab is no longer a box of a fixed height

  Both editors kept a tab exactly one window tall, with its own scrollbar, for the sake of a
  constructor whose three columns scrolled inside themselves. The constructor does not work that way
  any more — its preview is as tall as the page it shows and the browser scrolls it — so the rules
  that arranged all that are gone with the `fill` prop they hung on. A tab that grows with its
  contents is also the only kind that does not clip them.

- Updated dependencies [537df98]
- Updated dependencies [537df98]
- Updated dependencies [537df98]
  - @webx-ui/module-blocks@0.6.0
  - @webx-ui/module-admin@0.12.0
  - @webx-ui/core@0.27.0
  - @webx-ui/schema@0.3.4

## 0.2.1

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
  - @webx-ui/module-blocks@0.5.3
  - @webx-ui/schema@0.3.3

## 0.2.0

### Minor Changes

- 852883d: Tags: the order is on the headings, and renaming is a form.

  The two buttons over the list are gone — the name and the count sort from their own headings, in
  either direction, and the address carries the order so a link lands on the list somebody meant.
  The server takes a leading minus for it and keeps the bare names it had: alphabetical, and most
  used first.

  Renaming opens a dialog with one field. In the cell it was a name that turned into an `<input>`,
  which reads as a name — nothing said it could be typed in — and it saved itself on `blur`, an
  event that does not bubble, so the listener on the field's wrapper heard nothing and clicking away
  lost what had been typed.

  `WxActionBar` wraps its buttons. They were `flex: 0 0 auto` and stayed on one line whatever the
  width: measured on a 375px screen, five of them were 815px inside a bar 359 wide, and they took
  the whole page sideways with them.

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
  - @webx-ui/module-blocks@0.5.2
  - @webx-ui/schema@0.3.2

## 0.1.0

### Minor Changes

- f87e4ec: The article editor: tabs, blocks, autosave, the day it goes out

  `blog.article-form` is a described screen, like the page editor and for the same reason: the SEO
  card arrives as a patch from `module-seo` rather than being named in the blog's own description,
  and a project adds a tab the same way. Four tabs — the block constructor, the settings, SEO and
  the history — with a head above them that never moves and an action bar below.

  The settings are §10 of the spec: the address printed whole under the field that edits its last
  segment, the lead with a counter, the rubrics as a list that is dragged into order because the
  first one is the main one, a tag box that makes the tag it cannot find, the author, the cover,
  the pin, and the articles pinned under this one by hand. Five of them are node types the blog
  registers on both halves, so a rubric that is not a rubric is refused where every screen is
  checked rather than wherever somebody remembered.

  **The day is the part that is not a page editor.** The date in the settings tab is what
  "publish" publishes under, and the bar says which day that is before it is pressed: ahead, the
  article waits and answers 404 until its morning; behind, it moves down the feed. For an article
  that has never been on the site the day waits in the draft, because `published_at` is what "on
  the site" means and there is no column for a date that has not happened yet. For one that is
  already dated, moving the date writes the column at once — every listing orders by it.

  `WxActionBar` wraps. Its state box may shrink to nothing, and the words in it went on being
  painted where the box no longer was — straight across the buttons. Measured on a 375px screen:
  the box 0px wide and 105 tall, "Saved · goes out on 25 September at 17:06" over the top of "Save
  draft". Past the width of a short sentence the buttons now take a line of their own, still
  against the end of the bar.

  Saving is autosave, checked against the revision the form read and refused with a 409 when
  somebody wrote in between; the answer carries the article as it now is, so the panel asks which
  version the site gets instead of keeping one of the two silently. `PUT` now takes the screen's
  `values`, the history has its own two routes, `POST .../discard` throws away what is waiting,
  and `GET|POST /blog/tags` is the half of the tags API the article form needs — the screen that
  rakes them over comes with session D.

- f87e4ec: `Blog`: the section, the panel API and the list of articles

  The blog arrives in the navigation as three entries under one heading — Articles, Rubrics,
  Tags — because the panel draws one entry per module and a blog wants three. Rubrics and tags are
  declared on the server and stay out of the menu until their screens are written: an entry with
  no screen has nowhere to send anybody, so it is silently skipped.

  The API is a paginator rather than a level of a tree, which is the whole difference from
  `Pages`: `GET /api/cms/blog/articles` with a search term, a rubric, a tag, an author and a
  state, plus create, save, publish, unpublish, delete and restore. Every filter is a subquery and
  none of them is a join — an article is in several rubrics and carries several tags, and joining
  the pivot turns a page of twenty into seventeen articles with three of them drawn twice.

  Five states, and the pair worth keeping apart is the last two: an article that was never
  published and one that was taken off the site this morning both have no publication date, and
  only the history tells them apart. Publishing takes an optional date, so "on the site next
  Tuesday" is that date and not a scheduler.

  What is saved goes to two places, and the split is deliberate. The title, the address, the lead
  and the cover go into the draft — the site keeps showing what was published. The rubrics, the
  tags, the related articles and the pin do not, and cannot: a pivot row is not a column, and
  there is no such thing as half a row. A translated field travels as its whole language map, so
  saving from a Russian panel that is showing an English fallback no longer copies the English
  title into the Russian slot.

  `@webx-ui/module-blog` is the front end: the list with its filters, its views as tabs, the bin,
  and a row menu. Below 640 pixels the row becomes a card with the cover on the left and the title,
  one rubric, the state, the date and the author beside it — ten articles on a phone screen rather
  than two.

- f87e4ec: Blog: the screens for rubrics and tags

  **Rubrics** are a menu, so they are edited as one: `WxListDetail` with the list on the left,
  dragged into the order the site has them in, and the form for the one that is open on the right.
  No paginator and no search — a site has eight rubrics, and a menu you have to search is a menu
  that is already wrong. The form looks up `wx-media` and `wx-seo` in the panel's own type
  registry rather than importing either, so a panel without the file manager or without SEO gets a
  shorter form instead of one that will not mount. The SEO card starts folded behind a sentence
  saying where the title of the page comes from without it.

  Deleting a rubric that still holds articles is refused with the number in the message, and the
  button stays on screen and out of reach with the reason beside it: a button that disappears does
  not answer "why can I not delete this".

  **Tags** are entered from the article form by the hundred, so the screen is built for raking them
  over. Renaming happens in the row — Enter saves, Escape puts back — and the address does not move
  with the word, because a tag spelled three ways before lunch would otherwise leave three aliases
  behind a decision nobody made. Selecting rows raises a bar that opens, closes or deletes the pile
  at once, and merges it: the articles move over, the pivot deduplicates, and a checkbox decides
  whether the addresses that existed go on answering as redirects. The merge is irreversible and
  the dialog says so.

  The column **Indexing** has three states, not two — `indexed`, `indexed — SEO rule`, `noindex` —
  and the filter beside it counts by the same rule the rendered page follows, through
  `UrlRuleSource::hasRuleFor()`. Anything less leaves the editor who wrote the rule looking at a row
  that says `noindex` about a page that is in the index.

  Server side: `GET/POST/PUT/DELETE /api/cms/blog/rubrics` with `rubrics/reorder`, and
  `GET/POST/PUT/DELETE /api/cms/blog/tags` with `tags/merge` and `tags/mass`. The tags endpoint is
  one answer to "which tags are there": the dropdown on the article form asks for its first page.
  `HasUrl` gains `urlOf()`, so a screen that has already loaded the `routes` relation for a page of
  rows does not go back to the registry once per row to learn what it was handed.

### Patch Changes

- Updated dependencies [f87e4ec]
- Updated dependencies [f87e4ec]
  - @webx-ui/core@0.24.0
  - @webx-ui/module-admin@0.9.0
  - @webx-ui/module-blocks@0.5.1
  - @webx-ui/schema@0.3.1
