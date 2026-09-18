# @webx-ui/php

## 0.22.0

### Minor Changes

- 4644d28: `webx-ui/module-inbox`: the form on the site.

  `<x-webx-form slug="contact" />` prints a form of the panel — a control per field type, the
  honeypot, the hidden timestamp, the captcha block a form asks for — out of views the site
  publishes and rewrites, with no stylesheet and no design tokens of ours following it there.

  It works with JavaScript switched off: the form posts, and the page comes back with the errors
  under their inputs or the thank-you in place. The script adds only that this happens without a
  reload; it is one file with no dependencies, served from the package.

  A field the panel says is not full width carries `wx-form__field--half`. Without it the switch in
  the editor meant nothing on the site, which is worse than not offering it: the package has no
  layout of its own to apply, so naming the field is all it can do and the site's stylesheet does
  the rest.

  The form says which language it was printed in (`webx_locale`), and the intake answers in that
  one. Its middleware is written out by hand and so runs nothing the site added to its own `web`
  group — the language above all — so a Russian page was thanked in English, refused in English,
  and the submission recorded English as the visitor's language. How a site chooses its language is
  the site's business; the one thing always known is what the page came out in, so the form says
  it, exactly as the panel tells the server in `X-Webx-Locale`.

- 4644d28: `webx-ui/module-inbox`: the section by its other doors, and the command that forgets.

  Six MCP tools under `inbox:read` and `inbox:write` — `inbox_forms_list`, `inbox_form_get`,
  `inbox_form_save`, `inbox_list`, `inbox_get`, `inbox_set_status` — through the same rules the
  panel's own editor is refused by: the rules and the row of a form and of a field now live in
  `FormInput` and `FieldInput`, which the form requests and the agent both go through, so a slug
  that is not an address is refused at either door. A save changes only what it names, and a field
  sent with `remove: true` is put aside rather than destroyed, which leaves the answers already
  given through it readable.

  Receiving a submission is deliberately not a tool, and neither is deleting one. What deletes is
  `webx:inbox:prune`: spam older than one age, everything older than another, both from the config
  and both nought by default, row by row through the model so the files on the disk go with them.

- 4644d28: `webx-ui/module-inbox`: the panel's side of the forms, the fields and the statuses (§12).

  Reading the section and changing what it asks are two permissions: `inbox.view` opens the
  column of forms, because that column is the navigation of the section, and `inbox.manage`
  writes a form, a field or a status.

  Two things the settings needed saying out loud. Their keys are literal and several have dots
  in them, so nothing validates them by name — a rule called `options.thank-you.heading` reads
  the dot as a path and the value disappears without an error — and what is saved goes through a
  white list instead, which also keeps a `select`'s choices from surviving on a field that is no
  longer one. A recipient is the one setting refused rather than dropped: an address nobody will
  ever be written to looks exactly like one that works.

  A field's machine name is unique among the live fields of its form only, so a name comes back
  when the field that held it is deleted; a name with a dot in it is refused, because the intake
  would look for a nested array and report the error under a key nothing on the page has. A copy
  of a form is switched off and carries the questions and none of the answers.

  `GET /inbox/recipients` names the administrators a form can be told to write to — the ones who
  may actually open a submission, since a notification is a link and the alternative is a letter
  followed by a 403.

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

- 4644d28: `webx-ui/module-inbox`: forms and submissions, the package half.

  A form and its fields as rows, statuses seeded in ten languages, a public intake standing
  deliberately outside CSRF, the four antispam layers, attachments on the module's own private
  disk served only by the panel, and a notification per recipient in that recipient's own
  language. The submission is written before anything is sent, so a mail server that is down
  costs a notification and not an enquiry.

  The panel's screens, the site's form component and the agent's tools come in later sessions.

### Patch Changes

- 4644d28: `webx-ui/module-blocks`: the preview answers in the language the site answers in.

  Its route ran through `web` alone, and the language of a page is not decided there — it is
  decided by a middleware the site puts on the route that answers for a page. So the preview came
  out in the application's default: an editor writing a Russian page was shown it in English, with
  every localized thing in it — the words of a block, the labels of a form standing on it — in the
  wrong language, while the published page was right. The default is now `['web', 'webx.locale']`,
  which is what the setting already said it was for.

  A site that published `config/webx-blocks.php` keeps its own copy of that list and has to add
  `'webx.locale'` to `preview.middleware` itself.

## 0.21.0

### Minor Changes

- 0ff296d: The search box in the pages section looks in every language the site has.

  A list shows the title a page carries, not the one the reader asked for: a page named in English
  alone is drawn with that English name in a Russian panel. A search that looked at the current
  language only could not find what was on the screen, and said so with an empty list — which reads
  as a broken box rather than as an answer.

  `webx-ui/localization` gained `whereTranslationLikeAny()` for it, which walks the site's languages.
  The record's own keys cannot be walked instead: asking the database about them means reading the
  column as text, and in the stored JSON a translation is escaped (`Д…`), so a term in anything
  but ASCII would never match. `pages_tree` follows the panel — its `locale` argument says which
  language to answer in, not which one to look in.

## 0.20.1

### Patch Changes

- 2c4ee49: The bin of the pages section answers the search box.

  `GET /api/cms/pages` read the term for the tree and dropped it for the bin, so an editor looking
  for one deleted page among a hundred got the whole bin back and no sign that the box had been
  ignored. The term now narrows both lists — and `pages_tree` with `trashed`, which had the same
  gap, because an agent asking the bin for a name should not be handed everything in it either.

  The section says the right thing when a search comes back with nothing: it used to answer “the
  bin is empty” for any empty bin view, which was true until the box started working.

## 0.20.0

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

- d80a19e: A picture's captions reach a template in one language.

  `alt` and `title` are written per language — the field gives each of them a language
  switcher — and they were handed to a template as the whole map, so `{{ $picture['alt'] }}`
  was Blade being given an array and refusing. All four fields that hold a file are read
  through the same code, so all four had it: `wx-media`, `wx-file`, `wx-gallery`, `wx-files`.

  They are picked apart now, down the chain every localized value is read through: the
  language asked for, the site's default, its fallback. A caption that was never a map — one
  written before the site had a second language, or sent by an agent — is left as it is.

  Only the read side changed. The panel edits the whole map, and it never went this way.

## 0.19.0

### Minor Changes

- 14d79c1: A block can be switched off

  A block on a page gets a switch: it stays in the content, it is edited in the panel exactly as
  before, and the site does not draw it. Not a page draft, not a delete, and not the block type's
  `is_enabled` — a switch on one block.

  The eye stands first in the row of the tree, before duplicate and remove. A switched-off block
  is dimmed and carries an `eye-off` beside its name, always and not only under the cursor: the
  actions are invisible at rest, and a row that is merely dimmer than its neighbours says nothing
  on its own. It is absent from the preview too — a preview that still showed it would not tell
  you which block is the one that is off.

  In the content it is a fourth key on the node, `hidden: true`, written only when it is on: every
  tree from before the switch existed reads as visible, with no migration. The skip is one line in
  `Renderer::list()`, which is why a switched-off container takes everything inside it with it
  while their own switches keep their values — the recursion never reaches them — and why the
  type's styles and script stay off the page.

  `blocks_edit_content` gets `hide` and `show` by key, and `outline` reports `hidden: true`: an
  agent has no other way of telling that a block it can read is not on the site.

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

- e93ae5b: The panel's frame: the bar goes into the sidebar, and the page scrolls itself.

  On a desktop and a tablet there is no bar across the top of the panel any more. The sidebar is
  the whole of the chrome and has three zones — the brand and the collapse button, the menu with its
  own scrollbar, the account at the bottom with its menu opening upwards — and the 56px the bar took
  out of the window's height go to the screen. A phone has no such column, so there the bar comes
  back with the burger, the brand and the account, and the menu is a drawer; choosing a section
  there now closes the drawer, which it did not before.

  The frame floats: the sidebar and the phone's bar are cards inset from the edges of the window,
  with the body colour running all the way round them. The inset is the panel's spacing step —
  8 on a phone, 12 on a tablet, 16 on a desktop — and the column is 220px wide, 56px as a rail,
  which leaves a screen exactly the width it had under the old frame at 1280 and at 1440.

  What scrolls is the page, natively: the shell no longer caps itself at one viewport, and the
  sidebar stands still beside a document that moves. A screen that has to be exactly as tall as the
  window still says `data-wx-fill`, but the height it gets is now measured from the window rather
  than from a scrolling column.

  `WxAside` grew the `top` and `bottom` slots — with either of them filled, `scroll` moves to the
  middle zone — plus `sticky`, for a column that stands beside a scrolling page, and `floating`, for
  one drawn as a card. `WxHeader` takes `floating` too. Both are additions: every existing shape
  behaves exactly as it did, and `viewport` shells are untouched.

  `webx-ui/module-admin` adds `nav.expand` in all ten languages, for the button on the rail.

- 046c6ba: The panel wears the client's logo

  The corner used to hold `WEBX_ADMIN_TITLE`, a name from a deploy file, which made every
  installation look like the same borrowed tool. Settings gets a **Branding** tab with two
  pictures, and the frame wears them: `branding.logo` in the corner of the open sidebar at 28 px
  tall, `branding.mark` on the 56 px rail, above the button that opens the sidebar again.

  Two pictures rather than one and a cropping rule — a wordmark cut to a square is its first two
  letters, and only the client knows what their mark is. A mark left empty leaves the rail
  exactly as it was.

  The name does not leave. `general.project-name`, the localized field that has sat on the
  `General` tab since the section was written without anybody reading it, now becomes
  `manifest.title`: the text in the corner when there is no logo, the logo's `alt` when there
  is, and the deployed title again when it is cleared.

  On the server this is one binding — `WebxUi\Admin\Contracts\BrandingSource`, answered by
  `module-settings`. `module-admin` neither knows nor requires the section that holds a logo, and
  a panel with no source bound is the panel as it always was. The picture fields are `wx-media`,
  so `module-media` is what turns them into addresses; without it the values stay library paths
  the frame cannot read and the corner keeps its name, the same tolerance `module-seo` has for
  its `og:image`.

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

- 923a5ae: The file library moves into the `System` group

  `MediaModule` had no `group()` at all, so `Files` hung at the top level of the menu next to
  `Pages` — as if a file store were one of the things a site is made of, rather than a tool the
  sections share. It now returns `'system'` and leads that group with `order() = 500`, ahead of
  `Blocks` (600), `SEO` (700), `Settings` (800) and `Administrators` (900): of everything in
  `System`, the library is the one an editor opens while writing.

  The cost is a click: the library is now behind a group that starts collapsed. If it turns out
  to be opened more often than the settings around it, the order to reconsider is this one — but
  on watched use, not on argument.

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

## 0.18.0

### Minor Changes

- 22130c0: An agent changes part of a page without sending the page. `blocks_edit_content` takes operations
  by key — `set`, `add`, `move`, `remove`, the same vocabulary screen patches use — so changing one
  heading no longer means reading the whole tree and writing it back, which cost the page twice and
  quietly dropped whatever an editor had done in between. `set` merges field by field, and `locale`
  writes one language of a localized field rather than replacing the map with a string; a localized
  field refuses a bare value, and a plain field refuses a `locale`. Reading is cheaper too:
  `blocks_get_content` answers with the map of the page under `outline`, with one node under `key`,
  and always with a `revision` — a short hash of the content, which both writing tools accept and
  refuse to write over when the entity has changed since. The tree is edited by `ContentEdit`, pure
  functions the panel can use as well. `media_*` now report a file's `path` beside its `url`: a media
  field stores the key, so an agent that could only see the address had nothing to write into the
  field it belongs in.
- 2c2c2ba: The page editor: `pages.form` as a described screen, and the screen that saves it.

  `webx-ui/module-pages` registers `pages.form` — four tabs, Content · Settings · SEO · History —
  so the SEO card can arrive as a patch rather than as a fork of the editor. `GET /api/cms/pages/{id}`
  now answers with the values of that screen, the trail above the page, a signed preview link and a
  `revision`; `PUT` takes the values back through `ScreenValues`, so what the tree does not name is
  dropped and a 422 lands under the field it is about. The `revision` is a short hash of the
  content, the way `module-blocks` computes one: a save that names a revision that is no longer the
  current one is refused with a 409 carrying the page as it now is and who wrote it, and two people
  who saved the same thing are not a conflict. `GET .../versions` lists the publications with their
  author and source, and `POST .../versions/{n}/restore` makes an old one the draft — publishing it
  stays the separate step it always was.

  `@webx-ui/module-pages` draws it. The head stays put: the trail through the tree, the page's state,
  the preview link and the two buttons, folded into a menu on a narrow panel. Saving is by autosave —
  a pause after the last keystroke and the moment a field is left — with an explicit button beside a
  chip that says saved · saving · not saved yet, and a guard that flushes the pause on the way out
  and only asks when the save did not go through. The parts of the screen that are not fields are
  node types of their own: `wx-page-place` prints the whole address the page answers at and moves it
  in the tree, `wx-page-danger` takes it off the site or into the bin, `wx-page-history` is the
  history.

  `@webx-ui/module-blocks`: `wx-blocks` takes a `fill` prop — be as tall as what it is drawn in and
  let the tree, the form and the preview scroll each in itself. Without it the constructor sizes
  itself to its content, and a page made of twenty blocks scrolls the editor's head off the top of
  the screen along with it.

  `@webx-ui/core`: a screen that fills its column says so with `data-wx-fill`, and `WxMain` stops
  growing with it. A percentage height inside a scrolling column resolves against nothing while the
  column is as tall as its own content, and a screen cannot reach its own ancestors any other way.

- 2c2c2ba: A page says something about itself. `webx-ui/module-seo` grows the half it had deliberately left
  out: `seo_meta`, one translated row per entity; the `HasSeo` trait, which is the whole of what a
  content module has to write (`$page->seoValue()`, `$page->saveSeo()`, `$page->seoData($locale)`);
  and `EntitySource` at priority 50, between the rules an editor wrote for an address and the
  defaults the site falls back on — a rule was written because a page was wrong, so it wins; the
  defaults are what is said when nothing was said, so they lose. `wx-seo` is now a field type on the
  server as well, so the card can be dropped onto any entity's screen by a patch, and
  `webx-ui/module-pages` gets it that way: the SEO tab of the page editor is filled in by the SEO
  module rather than described by the pages one. What the card holds is saved the moment it is
  saved, on a published page and on an unpublished one alike — it never goes into the draft, because
  a description that only reaches search engines at the next publication is the kind of thing an
  editor finds out about from a search engine. An emptied card deletes its row instead of keeping
  one full of blanks, which is what lets the site's defaults back through.
- 2c2c2ba: Agents get the pages of a site. `webx-ui/module-pages` offers nine tools — `pages_tree`,
  `pages_get`, `pages_create`, `pages_update`, `pages_move`, `pages_publish`, `pages_unpublish`,
  `pages_delete`, `pages_restore` — through the same doors the panel uses: `PageForm` decides what
  a page's values are and checks them against the described screen, so a field another module put
  on `pages.form` is writable here by having done nothing, and the `revision` that answers a
  panel's 409 refuses an agent's stale write with the same sentence. Where a page may go is now
  `Placement`, one class the move endpoint and the move tool both ask, rather than two copies of
  the three rules that keep the home page where it is.

  A page is named by its id or by its address — `"/catalog/shoes"`, and `"/"` for the home page —
  because that is what a site is talked about in; text fields answer with every language at once,
  since an agent that got one title has no way of knowing whether the others exist. Content does
  not travel through any of this: a `blocks` key sent to `pages_update` is refused with the name of
  the tool that does it (`blocks_edit_content`, §13.1), rather than accepted and dropped. The new
  resource `pages://sitemap` is the map to read first — the tree nested the way it is nested, each
  page with its address per language and its status — and the prompt `build_page` puts the loop in
  front of an agent that was asked for a page: read the map and the block catalogue, create, fill
  with blocks, look at the preview, write the SEO card, and leave it a draft for a person.

  `webx-ui/mcp` raises the page size of `tools/list` from fifteen to a hundred. Six modules now
  offer more than forty tools between them, and a client that does not follow the cursor was seeing
  a third of them and concluding the rest did not exist.

- 2c2c2ba: `webx-ui/module-pages`: the tree of pages, the home page as its root, and the addresses.

  A new Composer package. Pages are a nested set with translatable `title` and `slug`, content
  made of blocks, a draft and a history — almost all of it from packages that already existed.
  What the package adds is the rules that are about pages: the home page is the root, is always
  there, and cannot be moved, deleted or given an address of its own; there is only ever one of
  it; and deleting a page trashes its whole branch, one node at a time, so that every address in
  it is released and a restore brings back exactly what went down together.

  `webx-ui/nested-set` learns soft deletes, by opt-in: a model that says `softDeletesInTree()`
  keeps its trashed nodes standing in the tree instead of being refused the delete, and takes
  charge of what happens to their descendants. Without it, the delete is still refused — a node
  that vanishes while its bounds are reclaimed leaves its children outside their parent.

  `webx-ui/routing` learns that an entity may have no address in a language at all:
  `HasUrl::hasUrlIn()` answers yes for everything unless a model says otherwise, and a language it
  says no to gets no row in the registry and loses the one it had. Syncing also stopped writing
  the slug it read back into the entity, which quietly filled in languages the editor had left
  empty; `webx:routes:check` no longer reports those languages as missing addresses.

- 2c2c2ba: The pages section: the tree of a site's pages, and the panel API behind it.

  `@webx-ui/module-pages` is a new npm package — the front end of the section. The list is a table
  tree read a level at a time: the home page is pinned at the top and its children are the top
  level, because everything on the site is inside it and a branch drawn for that would give every
  row a step of indentation that says nothing. Children arrive when a branch is opened, searching
  puts the tree away and answers with a flat list of matches and their addresses, and the bin is a
  filter rather than a section of its own. A page is moved by dragging it or through “Move…” and a
  tree of pages — the one that works on a touch screen and in a catalogue where the page and its
  new parent are four screens apart — and either way the section says out loud how many addresses
  the move rewrote, because an editor should not hear about a thousand redirects from a search
  engine. Row actions: open, add a page inside, duplicate, move, copy the address, open on the
  site, delete; in the bin, restore.

  `webx-ui/module-pages` gains the section and the endpoints under `/api/cms/pages`: the level of
  the tree with `can` and `children_count` on every row, create, save the draft, move, duplicate,
  publish, unpublish, delete into the bin with the branch, and restore. A page's title comes from
  its draft and its address from the registry, so a page renamed and not yet published shows its
  new name beside the address the site is still serving. Its refusals — the home page cannot be
  moved or deleted, a page cannot be dropped into its own branch, nothing stands beside the home
  page — answer as a 422 under the field they are about, the same way a taken address does.

## 0.17.0

### Minor Changes

- 4d1d996: `module-media` stores and resolves the three new field types, `module-blocks` tells an agent
  about them.

  `GalleryFieldType`, `FileFieldType` and `FilesFieldType` join `wx-media` in
  `WebxUi\Media\Screens`, sharing one set of rules, one `store` and one `resolve`. A resolved value
  now carries the whole of what the library knows — `url`, `thumb`, `name`, `extension`, `mime`,
  `size`, `width`, `height` — because a Blade template has nothing else to ask with, and a whole
  list is looked up in one query rather than one per value. `props.accept` is checked on the way in
  against the row fetched for the resolve; a key whose file has been deleted is kept and resolves
  to `url: null`.

## 0.16.2

### Patch Changes

- dd41bd8: A block's template is handed what the field type makes of a value, not the row as stored — the
  same way a screen's values are read for the site. A `wx-media` field keeps `{ path, alt, title }`
  and the template now also gets `url`, worked out when the block is printed, so the picture no
  longer has to be assembled from the disk's configuration inside the Blade. A value whose type
  nobody registered — `wx-blocks` above all — and a value whose key the schema does not name pass
  through as they are; a field inside `wx-repeater` or inside layout is resolved too, and the
  preview, the panel's own drawing and the check before publishing all see the same values as the
  page. The media field remembers the files it looked up for the length of one response, so a
  gallery costs one query per picture rather than one per mention.

## 0.16.1

### Patch Changes

- f7bdc63: `module-blocks`: the section moves into the System group of the navigation, first in it, above
  SEO — a block type is made once and then lives on the pages, so the section is opened the way
  the settings are.

## 0.16.0

### Minor Changes

- 7cecf88: The block constructor: the agent's doors, and the files

  `webx-ui/mcp` now serves what the modules declare. `WebxServer` is a `laravel/mcp` 1.0 server
  that reads the tool registry when it starts, so a panel exposes exactly the tools of the modules
  it has — over Streamable HTTP at `{api_path}/mcp`, closed by `webx.mcp-auth` until a Sanctum
  token opens it, and over stdio as `mcp:start webx`. `php artisan webx:mcp:token` issues a token
  to an administrator with the scopes as its abilities; a scope is checked once, before any handler
  runs. A handler now also receives the administrator the call acts as, and refuses with a thrown
  `ToolFailure` that the agent reads verbatim.

  `webx-ui/module-blocks` speaks it: `blocks_list`, `blocks_get`, `blocks_create`, `blocks_update`,
  `blocks_publish`, `blocks_render`, `blocks_get_content`, `blocks_set_content` and
  `blocks_preview_url`, through the same doors the panel uses and with `mcp` as the source in the
  history; the resources `blocks://guidelines`, `blocks://catalog`, `blocks://fields` and
  `blocks://site`; the prompt `design_block`. And the files: `webx:blocks:export` writes each type
  to `resources/blocks/{slug}.json`, `webx:blocks:import` reads them back — a version only where the
  content differs, `--publish` to publish what passes the checks, `--dry-run` to be told.

- 7cecf88: The block constructor: the panel

  `@webx-ui/module-blocks` is new: the section where a block type is made — a list of cards with
  live thumbnails, and an editor with the template, styles, script, fields and settings on one side
  and the block drawn on its sample, the sample's form built from the schema being edited and where
  the type stands on the other. Checks run live under the editor; publishing is a separate step,
  refused with the line when the template fails on the sample or on a page. `wx-blocks` is the field
  that builds an entity out of blocks: a tree with drag to reorder, a picker of types with pictures
  that offers only what may go here, the selected block's fields as a form, and the entity's own
  preview beside them — the whole page while looking at it, a phone at one to one while editing a
  block, swapped in place after a field changes. `provideBlocksPreview()` is how the hosting screen
  hands the preview address in.

  In `webx-ui/module-blocks`, the panel half: the module (`blocks.view`, `blocks.manage`, the groups
  and `webx.provide()` names in the manifest), the API under `/blocks` — types, catalogue, save
  (a version per save, none for an unchanged one), publish with the check on every page's values,
  render on sent values or on an unsaved template, usage, history and restore — the lints the
  server sends back with a saved version, `webx-blocks.editing` as a read-only switch, and the
  dictionary in ten languages.

- 7cecf88: The block constructor: the package and the renderer

  `webx-ui/module-blocks` is the section of the panel where a block type is made entirely — its
  fields, its Blade template, its styles and its script — and the mechanism that prints an entity's
  content from such blocks. This is its first half, the rendering: three tables (`blocks`,
  `block_versions`, `block_bundles`), the `Block` and `BlockVersion` models with `saveVersion()` and
  `publish()`, the cached registry `BlockTypes`, Blade compiled from the database into one file per
  version, the `@blocks` directive for nesting, the `HasBlocks` trait with the `$table->blocks()`
  macro, and `Blocks::render()`.

  Every block renders inside its own try/catch, so one broken template leaves a gap and a report
  rather than taking the page with it; in preview mode the gap is a notice with the template's line,
  and every block is wrapped in a pair of comments the panel finds it by. Publishing a version renders
  it on its sample values first and refuses, with the line, when that throws. The schema's fields are
  the template's variables — a field added after the content was written is `null` on the old pages,
  not an error.

  The second half of the same package, the styles and scripts: the set of types a page rendered, at
  their versions, makes a hash that names a row of `block_bundles` with the glued CSS and JS, served
  by `/blocks/{hash}.css` and `.js` with a year-long immutable cache. `@webxBlocks` in the layout
  prints the tags — evaluated where it stands, after the content under `@extends` and components —
  with `('styles')`, `('scripts')` and `('runtime')` variants and an inline mode for small sets. A
  block's script is an initialiser per instance behind a small runtime (`webx.block`, `webx.mount`,
  `webx.provide`, `webx.use`) that also ships on its own at `/blocks/runtime.js`. Commands:
  `webx:blocks:bundles --prune|--warm` and `webx:blocks:clear`.

  The preview: `/_preview/{type}/{id}?token=…` shows the draft of an entity as the page it will
  be, through the same handler and view that answer the real address, with a `Resolution` of the
  entity's own, the drafts of the block types, the marker comments, and `no-store` plus `noindex`
  on the response. The token is one signed parameter that opens one entity for an hour;
  `Preview::url($entity)` makes it, `PreviewGrant::of($request)` is how a handler tells a preview
  from a visit. The preview prefix is closed to the address registry.

  What the preview stands on, in `webx-ui/module-admin`: drafts and versions for any entity.
  `HasDraft` keeps what is being prepared in a `draft` column next to what the site shows, with
  `saveDraft()`, `withDraft()`, `publish()`, `unpublish()` and `isPublished()` by `published_at`;
  `HasVersions` writes a numbered snapshot into `entity_versions` on every publication, keeps a
  ring of autosaves beside the history, trims to `webx-admin.versions.limit` with pinned versions
  excepted, and restores an old version into the draft. `$table->draft()` adds the columns,
  `webx:versions:prune` applies a lowered limit. And in `webx-ui/nested-set`: a detached node —
  `saveDetached()` saves a row with no place in the tree, for a "new page" that exists before
  anybody has decided where it goes; it joins the tree with the first placement.

  The panel section and the MCP tools follow.

### Patch Changes

- f78e485: A compiled block template is named by its content as well as its version. The slug and the
  version number alone are not unique across databases: a test suite on an in-memory database
  and the developer's own site compile into one directory, each with a `hero` at version 1 and
  a different template — and whichever compiled first served both, so a page printed the other
  site's block, or nothing. The file name now carries a hash of the template; a new version is
  still a new file, and nothing is ever invalidated.

## 0.15.0

### Minor Changes

- f98786f: The registry of a site's public addresses

  `webx-ui/routing` is one flat namespace — `/about`, `/blog`, `/alternator-belt-7100104` — one row
  per address, and one resolver that hands a request to whoever owns the path it matched. Uniqueness
  holds across every kind of content at once, which is the part no single module can do on its own: a
  module cannot see another module's addresses, and those are exactly the ones it collides with. A
  library rather than a section: the public side of a site uses it with no panel in sight.

  Add `HasUrl` to a model and register its type, and saving, renaming, moving in the tree, deleting
  and restoring keep the registry in step from then on. The address itself is built by the type's
  formatter — `Slug`, `TreePath`, `SlugId`, `SlugSku`, `Prefixed`, or one of your own — and a
  formatter is a pure function of the entity, so a save, a preview in a form and
  `webx:routes:rebuild` cannot disagree about what the address is. A project overrides somebody
  else's formatter from config and moves the addresses that already exist with one command; nothing
  dies in the move, because every address that changes leaves an alias behind. Collisions are settled
  by the type: refused under the slug field, or suffixed with the suffix written back into the entity
  so the form shows what the site will serve.

  Reading is a fallback route, which is the whole trick — a fallback is tried only when nothing else
  matched, so a project's own `/search` wins with no ordering to arrange. One spelling per address
  (a trailing slash, a capital letter or a doubled slash is a 301, query kept); exact beats prefix, so
  `/about/mission` is its own page rather than a tail handed to `/about`; an alias answers 301 and
  takes the tail with it, so a renamed category keeps its pages of filters. Publication stays the
  entity's business: the registry has a row for everything that exists, and the handler decides
  whether to show it. Addresses the application answers itself are refused when an entity is saved,
  not when a request arrives — losing silently to a live route leaves an editor with a page that
  exists everywhere except on the site.

  `webx:routes:rebuild` recomputes the addresses of a type, `webx:routes:check` reports what no
  constraint can — rows with no entity, entities with no row, aliases leading nowhere, addresses a
  project has since claimed with a route of its own — and exits 1 so a deploy can stop on it.

  `UrlNormaliser` has moved here from `webx-ui/module-seo`: one spelling of an address for both
  halves of the system, with `key()` added beside `normalise()` for the registry's own. `module-seo`
  now depends on `webx-ui/routing`, reads the aliases through the narrow `RouteAliases` contract,
  says in `POST /seo/test-url` what the registry holds at an address, and prints the `<head>` of a
  page the resolver found without the template having to name it.

  The guide is `apps/docs/guide/routing.md`.

## 0.14.1

### Patch Changes

- aac21d2: `webx-ui/module-media` stops shipping its tests, and stops flaking

  Seven of the eight composer packages carry a `.gitattributes` that keeps `tests/` out of the
  published archive and normalises line endings; `module-media` was the one that did not, so its
  test suite has been riding along to Packagist. It has one now, the same one.

  The tests it keeps to itself are one bug lighter. A fake upload is a blank canvas of the size
  asked for and nothing else — the name is never drawn into it — so two of them with the same
  dimensions are the same bytes, and the store deduplicates by content inside a directory. One
  helper drew a random width to keep its two files apart, which worked about seven hundred and
  ninety-nine times out of eight hundred; the other trusted a unique name, which never mattered at
  all and held only because no test yet puts two such files in one folder. Both now give each file
  a width of its own, the way the third helper already did.

## 0.14.0

### Minor Changes

- c92f42a: SEO: rules for addresses, redirects, and the `<head>` a page prints

  `webx-ui/module-seo` is the panel section for SEO that belongs to no entity, and the renderer that
  turns it into markup. Sources are asked in order and merged **field by field** — a rule that fills
  in nothing but a title keeps the description and the picture that came from below it — so the
  entity source that arrives with the first content module is an addition, not a change.

  One matcher serves rules and redirects alike: exact, then mask (`*` inside a segment, `**` across
  them), then a regular expression, by priority inside each group, against the path with its query
  string. A pattern that will not compile is refused when it is saved and never matches if it got in
  anyway. The active ones are one compiled list in the cache, dropped whenever any of them changes.

  Redirects run as global middleware rather than in the `web` group, because the addresses worth
  redirecting are the ones the site has no route for and those never reach a group at all — with the
  panel's own paths stepped over, so a mask cannot lock an editor out of the screen they wrote it on.
  `/robots.txt` answers from a setting; the SEO tab of the settings screen now comes from the module
  instead of from each project's own patch. `POST /seo/test-url` says what an address ends up saying
  and where every part of it came from.

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

- 0304791: Repeater: a field whose value is a list of records

  `WxRepeater` is `WxSortableList` once every row is a form — a set of fields, repeated, in an order
  that is part of the answer. Rows fold to a name taken from their own fields, and each keeps a key
  of its own, so writing a field, removing the row above or dragging one elsewhere never rebuilds the
  form under the caret. `WxSortableList` gained `itemLabel` for the same reason: a row has to be
  called something out loud.

  In a described screen it is `wx-repeater`, the one type whose model is nested: the node's children
  are the fields of one item, and a `name` inside it is a key of that item. A type of its own can do
  the same with `nested: true`, which hands the component the node and the render context.

  On the server `RepeaterType` checks, stores and resolves items with the types their children
  declare — per language where a child is localized — and a failed row says which row it was.
  `FieldType::resolve` now takes the requested locale as a third argument, and `Tree::fields` stops
  at a named node: a repeater's children belong to its value, not to the screen.

## 0.13.0

### Minor Changes

- 47d52ee: Screens in the panel, both halves, and the first section built on them.

  - `module-admin` (PHP): `Screens::register` / `Screens::extend`, the `FieldTypes` registry with the core types, `ScreenValues` (save by description — a key the tree does not name is dropped, a node without permission is closed for writing, rules per type and per language), `GET /api/cms/screens/{name}` (patched, permission-filtered, translated). The manifest carries `screens`, `groups` and each module's `group`; `Module::group()` is new on the contract (`AbstractModule` answers null). `webx-admin.groups` declares the `system` group.
  - `@webx-ui/module-admin`: `createAdmin({ screens, types })`, `WxScreen`, `admin.loadScreen()` cached per language, `admin.types`, navigation groups drawn as branches — "System" holds settings and administrators.
  - `module-media`, both halves: registers `wx-media` (the field on the client, the stored key with the resolved address on the server).
  - `module-settings`, both halves, new: the `settings.index` screen with one "General" tab, `cms_settings`, `GET`/`PUT /api/cms/settings`, `settings()` on the site, cache and `SettingsSaved`, MCP `settings_list` / `settings_get` / `settings_set`. The SEO tab is a project patch, not part of the module.
  - `core`: a `gear` icon; the language chip on a localized field unrolls every language in the site order — the current one included and marked — instead of reshuffling, and switching puts the caret into the field that was switched.
  - `module-auth`: the administrator implements `HasPermissions`, `cms.auth` makes the guard the request's default, and the section sits in the "System" group.

## 0.12.1

### Patch Changes

- b554497: Фотография в углу шапки. Сессия (`/auth/me`, ответ на вход) теперь несёт `avatar` — ключ, под
  которым лежит фотография, тот же, что в ресурсе администратора. `auth()` принимает
  `resolveAvatar` — ту же функцию, что и `admins()`, — и `WxUserMenu` показывает картинку вместо
  инициалов; без неё всё как раньше. Аватар в шапке стал крупнее (`lg`).

  Мелочи вокруг: чекбокс «Stay signed in» придвинут к полю пароля, иконка показа пароля больше не
  уменьшается до шрифта кнопки и совпадает по размеру с замком, разделители в меню пользователя
  получили настоящий `spacing="sm"` вместо несуществующего значения, из-за которого они брали
  отступ по умолчанию.

## 0.12.0

### Minor Changes

- e5e129d: Каркас панели называется `module-admin`: `@webx-ui/module-admin` на npm и `webx-ui/module-admin` на
  Packagist вместо `@webx-ui/admin` и `webx-ui/admin`. Правило теперь одно на обе половины: всё, из
  чего состоит панель, — каркас и разделы — с префиксом `module-`, библиотеки, которые живут и без
  панели (`nested-set`, `localization`, `mcp`), — без него.

  Код не изменился: namespace `WebxUi\Admin`, конфиг `webx-admin`, экспорт `createAdmin` — те же.
  В composer `webx-ui/module-admin` объявляет `replace: webx-ui/admin`, так что сайт, который ещё
  требует старое имя, получит новый пакет; в npm старый пакет помечен deprecated. В сайте меняется
  импорт: `from '@webx-ui/module-admin'` и `'@webx-ui/module-admin/style.css'`.

## 0.11.0

### Minor Changes

- 6f7b501: Модуль администраторов называется `admins`, а не `users`: раздел живёт по адресу `/admins`, права
  стали `admins.view`, `admins.manage`, `admins.audit`, области MCP — `admins:read`, `admins:write`,
  `admins:audit`, инструменты — `admins_list_admins`, `admins_grant_role` и остальные с тем же
  префиксом. `users` оставлено пользователям сайта: когда сайту понадобятся регистрация и вход, они
  станут отдельным модулем, и два раздела с одним именем столкнулись бы в правах и в навигации.

  Миграция переписывает права в уже сохранённых ролях, так что роль, которой вчера выдали
  `users.manage`, сегодня по-прежнему разрешает управлять администраторами.

## 0.10.0

### Minor Changes

- 3e64cd7: Администраторы: список, форма и выбор из кода.

  `webx-ui/module-auth` получил CRUD поверх того, что уже было, — поиск, фильтры по роли и
  состоянию, пагинация, роли только на чтение. Два действия сервер отклоняет сам, а не прячет
  кнопку: выключить или удалить самого себя и снять последнего суперадминистратора. Оба способа
  оставить панель, в которую некому войти. У администратора появилось фото — ключ библиотеки, как
  и везде.

  `@webx-ui/module-auth`: раздел `admins()`, диалог создания и редактирования, `selectAdmin()` и
  `selectAdmins()` — тот же список в диалоге, возвращает выбранных. Для выбора достаточно права
  `users.view`: назначить кому-то задачу и редактировать его учётную запись — разные вещи.

  Модуль не зависит от медиа: поле для фотографии и способ превратить ключ в адрес передаёт панель
  — единственное место, которое знает, что установлено и то и другое.

  Попутно: `WxAvatar` берёт `name` и сам считает инициалы, а проп `label` у него отсутствует — меню
  пользователя показывало всем заглушку вместо букв. И сама аватарка в меню больше не завёрнута в
  экшен: это не ещё один инструмент в ряду иконок, а кто вошёл.

- 3e64cd7: Таблицы на телефоне и восемь правок по форме администратора.

  `@webx-ui/core`: ниже `cardsBelow` (480 по умолчанию, ширина самой таблицы, а не окна) строка
  рисуется карточкой из пар «подпись — значение». Ячейки те же самые — те же слоты `cell-<key>`,
  те же форматтеры, — так что экран, написанный под таблицу, переживает телефон без единой правки.
  Колонка с `hideOnCards` в карточку не попадает, а действия строки уезжают в слот `card-actions`:
  в карточке нет колонки, где им быть. Пагинация показывается только когда страниц больше одной —
  одну страницу листать некуда, а строку экрана она занимает. Проп `flush` снимает с таблицы
  её собственные поля вокруг шапки и карточек — для таблицы внутри карточки, где поля уже держит
  карточка, и второй их комплект ставил поле поиска на шаг правее кнопки над ним.

  `webx-ui/localization` кладёт под приложение переводы сообщений валидатора на десять языков.
  Laravel везёт их только по-английски, и панель, переведённая на десять языков, отвечала на
  незаполненную форму по-английски. `addPath` добавляет, а не заменяет: строка, опубликованная в
  `lang/` приложения, по-прежнему главнее.

  `@webx-ui/module-media`: у поля картинки появились пропорции — `aspect` с пресетами `16/9`,
  `4/3`, `1/1` и любым `width / height`.

  `@webx-ui/module-auth`: форма в две колонки — фото сбоку, поля справа, а на узком экране одной
  колонкой; кнопка и заголовок диалога короткие («Добавить», «Редактировать»); фильтры над списком
  администраторов убраны (их полдюжины, фильтр над шестью строками — это контрол, который надо
  прочитать вместо того, чтобы посмотреть).

## 0.9.0

### Minor Changes

- 0d288c1: Форма научилась говорить на нескольких языках, а картинка — жить на форме.

  `@webx-ui/core`: `localized` на `WxInput` и `WxTextarea`. Модель становится записью по языкам,
  инпуты называются `title[uk]`, `title[ru]` — так, как это читает обычный POST, — а язык, который
  редактируется, общий на весь экран: страница наполовину на одном языке и наполовину на другом это
  и есть непереведённая запись. Языки берутся не из пропсов каждого поля, а из панели
  (`provideLocales`), и это языки контента сайта, а не язык самой панели. `WxLocales` — то же самое
  вокруг секции, вкладками. Плюс `localizedValue()` для чтения такого значения в таблице.

  Пикер теперь умеет и загружать. Выбор только из того, что уже лежит в библиотеке, отправляет
  человека уходить со страницы, загружать файл и искать форму заново. И `accept` стал не
  подсказкой, а правилом: когда вызывающий сказал «картинка», фильтр типов из панели убирается и
  вернуть «все» нельзя.

  `@webx-ui/module-media`: `WxMediaField` переписан под два состояния — пустая рамка, открывающая
  библиотеку, и картинка с двумя действиями: подписи (`alt` и `title`, мультиязычные) в поповере и
  очистка поля с подтверждением, которая не удаляет файл. Запись хранит только ключ, поэтому поле
  само находит файл по нему: `webx-ui/module-media` отвечает на `GET files/by-path`.

  `@webx-ui/core` заодно: внутри `WxSelectionArea` больше не терялся двойной клик. Указатель
  захватывался на нажатии, и совместимостные mouse-события уходили вместе с ним — файл в менеджере
  настоящей мышью не открывался, хотя синтетический `dblclick` в тестах проходил.

## 0.8.0

### Minor Changes

- 9e1b513: Three things found by putting the library on S3 behind a CDN.

  The image editor could not open a picture at all. It draws onto a canvas and writes that canvas
  out, which a browser refuses for bytes fetched from another origin without CORS headers — and a
  private bucket cannot be given those headers for the panel in any case. So `webx-ui/module-media`
  serves the picture itself at `files/{id}/source`, behind the same permission as the listing, and a
  `MediaFile` now says where that is. `url` stays what everything that only looks at a file uses.

  The editor also spoke English in a Russian panel: it is a component of the design system, so its
  words are props, and the manager was not passing any. It has its own ten-language group now, as
  does the question the card asks before deleting one file.

  `@webx-ui/admin`: changing the language renames the sections too. Titles are translated on the
  server and travel in the manifest, which was fetched in the previous language — so the panel used
  to switch everything except its own navigation until the page was reloaded.

## 0.7.0

### Minor Changes

- 760d372: `webx-ui/module-media` gains its folder endpoints: the tree in one answer, create, rename, move,
  and a delete that refuses a folder with anything in it until it is asked again with `force`. The
  refusal carries the counts, because the panel has a real question to put to the person: pictures
  already placed in articles will stop opening.
- a7fd824: `webx-ui/module-media` gains image editing and its MCP tools. An edit is written over the same
  key, so every address already in an article keeps working, and the picture as it arrived is kept
  once so any edit can be undone. The tools cover the library the way an agent would use it —
  except deleting a folder, which is deliberately absent.
- de2b3a4: `webx-ui/module-media` gains its file endpoints and its previews: a paginated, searchable,
  filterable listing, multi-file upload, rename, batch move, batch delete, and a thumbnail
  endpoint that cuts a variant once and then redirects to it so the disk — or the CDN in front of
  it — serves the grid instead of PHP.
- 47cc998: The file manager after an hour with it: icon actions instead of labelled buttons, filters behind
  popovers, folders in a drawer on a phone, case-insensitive search in any alphabet, previews that
  change when a picture is edited, and a copy-the-link that says whether it worked.

  `@webx-ui/admin` gains the toaster the panel never had — until now every `toast()` from every
  module reported into silence.

- 5ccdccc: New package `webx-ui/module-media`: the panel's file manager. This is its first step — the
  section registers itself, carries its configuration and its ten languages, and declares the
  permissions the rest of the module will be built against. Folders, files and the endpoints
  around them follow.
- 377e894: `webx-ui/module-media` gains its schema and its storage: folders as a nested set, files that
  belong to one, and the service that puts bytes on a disk and takes them off it. Keys are built
  from a uuid and say nothing about the folder, so the same picture in two folders is two keys and
  moving a file between folders never touches the bytes.
- 47cc998: A second pass over the file manager, from using it: upload refusals written in extensions rather
  than a paragraph of mime types, a status bar under the grid instead of a toolbar that grows a
  line, folders created through a dialog rather than a `prompt` the browser may refuse, the page in
  a card, and the image editor cropping what the person actually framed.

  `@webx-ui/core`: `WxFileCard` falls back to the old clipboard when the modern one refuses, so the
  green tick appears wherever the copy actually worked.

### Patch Changes

- 47cc998: New package `@webx-ui/module-media`: the file manager as a section of the panel, a picker that
  opens from code, and a form field that keeps `{ path, alt, title }` on the entity rather than on
  the file. Batch deletion moved to `POST files/delete` on the server, because the panel's own HTTP
  client sends no body on `DELETE`.
- 47cc998: Three things the file manager got wrong in Russian: the dialogs' buttons stood shoulder to
  shoulder, the confirm button read `manager.save` because nobody had shipped the key, and an
  upload refusal came back in English — the uploader is a bare `XMLHttpRequest` and was the one
  request in the panel that never said which language it was drawn in.

## 0.6.0

### Minor Changes

- a9240d6: Seven more languages in the panel: German, Polish, French, Spanish, Italian, Portuguese and
  Turkish, alongside the English, Russian and Ukrainian that were already there. A site still
  decides which of them to offer in `config('webx-localization.panel')`.

  A test in each package holds the ten key sets together — a missing line falls back to English
  rather than to a key, which is right and also the reason a gap can sit unnoticed.

## 0.5.0

### Minor Changes

- 78d8ef3: The panel answers in the language of whoever is reading it. `webx-ui/admin` serves the language
  list and the interface dictionary — both public, because the sign-in screen is drawn before
  there is a session to ask — and carries `locale`, `locales` and `panelLocales` in the manifest.
  `webx-ui/module-auth` stores each administrator's choice on the administrator, so it follows
  them to the next machine and so validation messages arrive in the same language as the labels
  above them.

  Both packages ship English, Russian and Ukrainian. A site adds a language they never shipped by
  publishing their `lang` files and translating what is missing; the merge is per line, so an
  untranslated key falls back on its own rather than taking its screen with it.

- 78d8ef3: `webx-ui/localization` — the languages a site is published in, translated Eloquent attributes,
  and the dictionary the admin panel is drawn from.

  It keeps two things apart that are easy to run together. Interface phrases are written by
  whoever wrote the module, change at deploy, and live in the package's `lang` files; content is
  written by whoever runs the site, changes all day, and lives in the database. One store for
  each, and neither knows about the other.

  A model names its translatable columns and goes on being a model — the value is a JSON language
  map, readable by anything that understands `spatie/laravel-translatable`. The panel's own words
  come from the same `lang` files the server reads, so a module is translated once rather than
  once per half.

### Patch Changes

- 9c0a762: The panel's sidebar toggle has a translated label: `webx-admin::nav.collapse`, in English,
  Russian and Ukrainian. Without it that one control fell back to the English the npm package
  carries, which is a small thing that looks exactly like a broken translation.

## 0.4.0

### Minor Changes

- fd98bdd: `webx:panel` wires the panel's front end into the application that hosts it: it writes the
  entry file, adds it to the Vite inputs, points `webx-admin.vite` at it, and names the npm
  packages to install. Where it cannot recognise a Vite configuration it says which line to add
  rather than rewriting a build it does not understand.
- 7fa1539: `webx-ui/admin` can load the panel's own assets. `webx-admin.assets` names the built files, or
  `webx-admin.vite` names entry points for an application that builds the panel with Laravel's
  own Vite. With neither, the shell stays deliberately blank — the frame installed and the panel
  not is a real state, and it should look like one.

### Patch Changes

- 48c6e34: The entry `webx:panel` writes now imports the stylesheets. The packages ship compiled CSS that
  nothing imports on its own, so the panel built from the previous stub ran perfectly and looked
  like an unstyled form.

## 0.3.1

### Patch Changes

- b193bdf: `webx:admin` accepts the password in `WEBX_ADMIN_PASSWORD` when there is nobody to ask, so a
  provisioning script or a container entrypoint can create the first administrator. Still no
  `--password` option: an argument lands in the shell history and in the process list.

## 0.3.0

### Minor Changes

- 18d9a7e: `webx-ui/module-auth`: administrators, roles and sign-in. Installing it is what closes the
  panel — until now `webx-ui/admin` served its API to anyone. Administrators live in their own
  table behind their own guard, roles grant the permissions modules declare in the manifest, and
  every sign-in attempt is written down. Its MCP tools can read who has what and move people
  between roles, and deliberately cannot touch a password or mint a token.

## 0.2.0

### Minor Changes

- cc9d156: Two packages the admin panel is built from. `webx-ui/admin` carries the module contract, the
  registry, the manifest the front end reads before it draws anything, and the catch-all that
  keeps a deep link from 404ing. `webx-ui/mcp` carries the contract by which a module offers
  itself to an AI agent — a mutating tool is given `dry_run` and a write scope whether its author
  remembered them or not.

## 0.1.0

### Minor Changes

- 219fd59: First release of the Composer packages. `webx-ui/nested-set` brings nested set trees to
  Eloquent: subtree reads in one query, placement and moves that keep the bounds consistent,
  `toTree` for handing a whole tree to the front end, and `fixTree` / `checkTreeIntegrity` for
  when something has gone wrong anyway.
