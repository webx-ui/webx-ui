# @webx-ui/module-auth

## 0.8.0

### Minor Changes

- dce896c: Every call an agent makes is written down, and the panel shows who did what

  An agent acts in an administrator's name, and until now nothing said afterwards what it had
  done. Now every tool call lands in `mcp_calls`, the way every sign-in lands in
  `cms_login_records`, and the administrators section shows the trail:

  - **One row per call, whichever way it went.** `webx-ui/mcp` writes it in one place, around the
    whole of the call — so a refusal at the door for a scope, a read-only connection or a missing
    permission is a row with its reason, and so is what the handler threw. A handler that answers
    `ok: false` is written down as refused too. Each row carries who the agent acted as, on which
    connection, the tool, its arguments, whether it was a dry run, and how long it took. No secret
    reaches it: the token and the headers are never looked at, and an argument named like one is
    blanked. There is deliberately no link to what the call was about — tools are about
    different things.
  - **Kept by days.** `webx-mcp.calls.days` (90) is the retention; `webx:mcp:prune-calls` runs
    nightly on the scheduler. `calls.enabled` switches the log off, `calls.arguments_length`
    cuts long arguments.
  - **A view next to the administrators.** `@webx-ui/module-auth` draws **Agent calls** as a
    second view of the section, at `/admins/calls`, for whoever holds `admins.audit` — the
    permission the sign-in trail is behind. It narrows by administrator, by tool and by outcome,
    and the choices on offer are the ones that actually appear in the log. Arguments and the
    refusal's words open under a row. `GET /api/cms/auth/mcp-calls` answers it.
  - The playground panel now has the administrators section, so the view can be looked at on
    `localhost:5174/panel/admins/calls`.

- 5309e37: An address is all a person needs to connect their own agent, and a list is all they need to end it

  The dance, the consent screen, the permissions and the log were done; what was missing was the
  part a person actually looks at. Two screens and a guide.

  - **Connect an agent** — a new section in the system group, behind no permission at all:
    whoever got into the panel may connect an agent, and the agent cannot do anything they
    cannot. It has the address of this panel for agents, large, with a button that copies it;
    three steps for Claude and ChatGPT; a line for a terminal for Claude Code and two lines of
    TOML for Codex; and one-click install links for Cursor and VS Code. The address carries no
    secret — that is the whole point of the OAuth path — so it can be printed, read aloud, or
    left on a page. The server prints it absolute, because it is pasted into a program on
    another machine, and the name the server takes in the client's own list comes from its host,
    so somebody with three sites connected can tell them apart. The section is registered only
    where there is a door to connect to: Passport installed and `webx-mcp.path` not `false`.
  - **Connections** — the agents that have been let in, with what each may do, when it was
    connected and when it was last heard from. Everybody's, as a third view of the
    administrators section, for whoever holds `admins.manage`; their own, at the foot of the
    connect page, for anybody signed in. **Disconnect** revokes the refresh token as well as the
    access token — without the second, a connection that the panel says has ended goes on
    refreshing itself for the month it was given. The row is kept, greyed: the call log points
    at it, and a line saying the connection ended on the 21st is worth more than a gap.
  - `GET /api/cms/auth/connections` (`?all=1` for everybody's) and
    `DELETE /api/cms/auth/connections/{id}` answer both, and `WebxUi\Mcp\Grants\Grants::revoke()`
    is where a connection ends.
  - A guide, `apps/docs/guide/agents.md`: how to connect, what an agent may do and why that is
    exactly what you may do, why not to connect a super administrator, and the two things —
    nightly dumps and the list of return addresses — to have in place before switching it on.
  - The playground panel has both screens, on `localhost:5174/panel/connect` and
    `localhost:5174/panel/admins/connections`.

- 87a538c: The consent screen is the panel's own, and "read only" is a box on it

  When an agent asks to be let in, the person now sees a page of the panel rather than the plain
  one: the site's logo, who is asking and where the answer will be sent, and what the agent will be
  able to do — in the words of the panel's modules ("Pages — view and edit", "Files — view"), not in
  scopes. Under it, the warning that the agent acts in their name and that they are responsible for
  what it does. In the panel's language, all ten.

  - **Read only.** One box instead of a matrix of scopes: tick it and the agent may look and may
    not change anything, whatever the person's own permissions say. A read-only connection is not
    shown the tools that write, and is refused if it calls one it remembers from before.
  - **The consent is written down.** No "I understand" box — the fact of pressing Allow goes into
    `mcp_grants` in `webx-ui/mcp`: who, which client, the address the code went to, whether they
    said read only, which version of the text they were shown, and when. The same row is updated
    when the same person lets the same client in again. `last_used_at` is kept to the minute, so
    a list of connections can say when each was last seen.
  - **A guest is sent to the panel to sign in and brought back.** Passport sends a stranger to a
    route named `login`, which no site with this panel has; now they are sent to the panel's own
    sign-in screen with the consent page as `next`, and `@webx-ui/module-auth` follows a whole
    address on the same site as a page rather than as a route. "Sign in as somebody else" on the
    consent screen ends the session and goes the same way. The sign-in path is
    `webx-auth.login_path`, `login` under the panel's path.
  - The consent screen posts to `{oauth prefix}/consent` rather than to Passport's approve route;
    `scripts/php-smoke.sh` walks the dance both ways, read-only and not, in a real application.

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

### Patch Changes

- Updated dependencies [f623fac]
- Updated dependencies [cd95a2e]
- Updated dependencies [b1aeb52]
  - @webx-ui/module-admin@0.13.0
  - @webx-ui/core@0.30.0

## 0.7.4

### Patch Changes

- Updated dependencies [0a506df]
  - @webx-ui/core@0.29.0
  - @webx-ui/module-admin@0.12.2

## 0.7.3

### Patch Changes

- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
- Updated dependencies [cca572f]
  - @webx-ui/core@0.28.0
  - @webx-ui/module-admin@0.12.1

## 0.7.2

### Patch Changes

- Updated dependencies [537df98]
- Updated dependencies [537df98]
  - @webx-ui/module-admin@0.12.0
  - @webx-ui/core@0.27.0

## 0.7.1

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

## 0.7.0

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

## 0.6.3

### Patch Changes

- Updated dependencies [f87e4ec]
- Updated dependencies [f87e4ec]
  - @webx-ui/core@0.24.0
  - @webx-ui/module-admin@0.9.0

## 0.6.2

### Patch Changes

- Updated dependencies [74d1369]
  - @webx-ui/core@0.23.0
  - @webx-ui/module-admin@0.8.0

## 0.6.1

### Patch Changes

- Updated dependencies [4644d28]
- Updated dependencies [4644d28]
  - @webx-ui/module-admin@0.7.0

## 0.6.0

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

### Patch Changes

- Updated dependencies [ad9ead7]
  - @webx-ui/module-admin@0.6.0
  - @webx-ui/core@0.22.0

## 0.5.4

### Patch Changes

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

## 0.5.3

### Patch Changes

- Updated dependencies [2c2c2ba]
  - @webx-ui/core@0.20.0
  - @webx-ui/module-admin@0.4.2

## 0.5.2

### Patch Changes

- Updated dependencies [c92f42a]
- Updated dependencies [0304791]
  - @webx-ui/core@0.19.0
  - @webx-ui/module-admin@0.4.1

## 0.5.1

### Patch Changes

- 47d52ee: Screens in the panel, both halves, and the first section built on them.

  - `module-admin` (PHP): `Screens::register` / `Screens::extend`, the `FieldTypes` registry with the core types, `ScreenValues` (save by description — a key the tree does not name is dropped, a node without permission is closed for writing, rules per type and per language), `GET /api/cms/screens/{name}` (patched, permission-filtered, translated). The manifest carries `screens`, `groups` and each module's `group`; `Module::group()` is new on the contract (`AbstractModule` answers null). `webx-admin.groups` declares the `system` group.
  - `@webx-ui/module-admin`: `createAdmin({ screens, types })`, `WxScreen`, `admin.loadScreen()` cached per language, `admin.types`, navigation groups drawn as branches — "System" holds settings and administrators.
  - `module-media`, both halves: registers `wx-media` (the field on the client, the stored key with the resolved address on the server).
  - `module-settings`, both halves, new: the `settings.index` screen with one "General" tab, `cms_settings`, `GET`/`PUT /api/cms/settings`, `settings()` on the site, cache and `SettingsSaved`, MCP `settings_list` / `settings_get` / `settings_set`. The SEO tab is a project patch, not part of the module.
  - `core`: a `gear` icon; the language chip on a localized field unrolls every language in the site order — the current one included and marked — instead of reshuffling, and switching puts the caret into the field that was switched.
  - `module-auth`: the administrator implements `HasPermissions`, `cms.auth` makes the guard the request's default, and the section sits in the "System" group.

- Updated dependencies [47d52ee]
  - @webx-ui/core@0.18.0
  - @webx-ui/module-admin@0.4.0

## 0.5.0

### Minor Changes

- b554497: Фотография в углу шапки. Сессия (`/auth/me`, ответ на вход) теперь несёт `avatar` — ключ, под
  которым лежит фотография, тот же, что в ресурсе администратора. `auth()` принимает
  `resolveAvatar` — ту же функцию, что и `admins()`, — и `WxUserMenu` показывает картинку вместо
  инициалов; без неё всё как раньше. Аватар в шапке стал крупнее (`lg`).

  Мелочи вокруг: чекбокс «Stay signed in» придвинут к полю пароля, иконка показа пароля больше не
  уменьшается до шрифта кнопки и совпадает по размеру с замком, разделители в меню пользователя
  получили настоящий `spacing="sm"` вместо несуществующего значения, из-за которого они брали
  отступ по умолчанию.

### Patch Changes

- Updated dependencies [1d691b8]
- Updated dependencies [b554497]
  - @webx-ui/core@0.17.0
  - @webx-ui/module-admin@0.3.1

## 0.4.1

### Patch Changes

- e5e129d: Каркас панели называется `module-admin`: `@webx-ui/module-admin` на npm и `webx-ui/module-admin` на
  Packagist вместо `@webx-ui/admin` и `webx-ui/admin`. Правило теперь одно на обе половины: всё, из
  чего состоит панель, — каркас и разделы — с префиксом `module-`, библиотеки, которые живут и без
  панели (`nested-set`, `localization`, `mcp`), — без него.

  Код не изменился: namespace `WebxUi\Admin`, конфиг `webx-admin`, экспорт `createAdmin` — те же.
  В composer `webx-ui/module-admin` объявляет `replace: webx-ui/admin`, так что сайт, который ещё
  требует старое имя, получит новый пакет; в npm старый пакет помечен deprecated. В сайте меняется
  импорт: `from '@webx-ui/module-admin'` и `'@webx-ui/module-admin/style.css'`.

- Updated dependencies [e5e129d]
  - @webx-ui/module-admin@0.3.0

## 0.4.0

### Minor Changes

- 6f7b501: Модуль администраторов называется `admins`, а не `users`: раздел живёт по адресу `/admins`, права
  стали `admins.view`, `admins.manage`, `admins.audit`, области MCP — `admins:read`, `admins:write`,
  `admins:audit`, инструменты — `admins_list_admins`, `admins_grant_role` и остальные с тем же
  префиксом. `users` оставлено пользователям сайта: когда сайту понадобятся регистрация и вход, они
  станут отдельным модулем, и два раздела с одним именем столкнулись бы в правах и в навигации.

  Миграция переписывает права в уже сохранённых ролях, так что роль, которой вчера выдали
  `users.manage`, сегодня по-прежнему разрешает управлять администраторами.

## 0.3.0

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

### Patch Changes

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

- Updated dependencies [3e64cd7]
  - @webx-ui/core@0.16.0
  - @webx-ui/admin@0.2.4

## 0.2.1

### Patch Changes

- Updated dependencies [0d288c1]
  - @webx-ui/core@0.15.0
  - @webx-ui/admin@0.2.3

## 0.2.0

### Minor Changes

- 9c0a762: The sign-in card and the user menu speak the panel's language. Every string is still a prop and
  a prop given still wins; what changed is the default, which now comes from the dictionary rather
  than from English hardcoded in the component.

  The user menu gained a language picker — it belongs within reach rather than three clicks into a
  section somebody cannot read — and `auth.setLocale()` stores the choice against the
  administrator, so it follows them to the next machine.

  A throttle notice takes `:seconds` rather than `{seconds}`, matching the way the same string is
  written in the `lang` file it now comes from.

### Patch Changes

- Updated dependencies [9c0a762]
  - @webx-ui/admin@0.2.0

## 0.1.0

### Minor Changes

- 266f47d: The password reveal is a bare icon at the end of the field. It was a button with a surface of
  its own, and a second box inside an input reads as a second control — it took up a third of the
  field to say something the icon says on its own.

### Patch Changes

- Updated dependencies [752adf0]
- Updated dependencies [266f47d]
  - @webx-ui/core@0.14.2
  - @webx-ui/admin@0.1.0
