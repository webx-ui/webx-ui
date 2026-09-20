# Building a screen

Every section of the panel is the same handful of parts in the same order: a head, a body, and —
when there is something to save — a bar along the bottom. Written that way a new screen is
half an hour's work and reads like the ten screens beside it. Written freehand it is a day, and
the panel gains one more answer to a question it had already answered.

Start with the head. It is the part a reader learns once and then expects everywhere, and it is
the part that used to be written eight different ways.

## 1. The head

```vue
<script setup lang="ts">
import { computed } from 'vue'
import { useAdmin, useTranslate, WxScreenHead, type ScreenAction } from '@webx-ui/module-admin'

const admin = useAdmin()
const t = useTranslate('webx-blog')

const canManage = computed(() => admin.can('blog.articles.manage'))

const actions = computed<ScreenAction[]>(() => [
  { key: 'preview', label: t('article.preview'), icon: 'eye', href: previewUrl.value },
  ...(canManage.value
    ? [{ key: 'new', label: t('article.new'), icon: 'plus', primary: true, run: () => void add() }]
    : []),
])
</script>

<template>
  <wx-screen-head
    :back="base"
    :back-label="t('module.articles')"
    :title="title"
    :subtitle="article.slug"
    :actions="actions"
  >
    <template #trail>
      <wx-breadcrumb size="sm">…</wx-breadcrumb>
    </template>
    <template #title-after>
      <wx-badge :type="badge()" dot>{{ status }}</wx-badge>
    </template>
  </wx-screen-head>
</template>
```

The name's line **is** the head: the way out at the start of it, the actions at the end, both
centred on the name however many badges stand beside it. The trail takes the line above and
scrolls sideways with no scrollbar showing — a path four levels deep is longer than a phone is
wide, and a trail that wraps is two lines of the smallest type on the screen standing between the
reader and what they opened. The line that says _which_ record this is goes underneath.

| Prop            | What it is                                                         |
| --------------- | ------------------------------------------------------------------ |
| `title`         | The one heading on the screen; the card under it gets none.        |
| `subtitle`      | Which record this is: a slug, the form it came through, a count.   |
| `back`          | A route, or `true` to draw the same arrow and emit `back` instead. |
| `backLabel`     | What its tooltip says. The panel's own word for it when left out.  |
| `level`         | `2` for a screen, `3` for a pane standing inside one.              |
| `actions`       | What can be done here — see below.                                 |
| `divider`       | A rule underneath, for a head that never scrolls away.             |
| `collapseBelow` | Where the actions fold. 720 by default; a list uses 480.           |

Four slots, for the things a prop cannot carry: `#trail` (breadcrumbs), `#title-after` (the state
of the record — a badge, `WxRenameButton`), `#subtitle` when it is more than a string, and
`#extra` for controls that are not one-word actions and must stay drawn at every width, such as
the pair of arrows that walks a pile of records one at a time.

### Actions are declared, not drawn

```ts
const actions = computed<ScreenAction[]>(() => [
  { key: 'preview', label: t('preview'), icon: 'eye', href: previewUrl.value },
  { key: 'new', label: t('new'), icon: 'plus', primary: true, run: () => void add() },
  { key: 'export', label: t('export'), icon: 'download', menu: true, href: exportHref.value },
  { key: 'delete', label: t('delete'), icon: 'trash', danger: true, run: () => void remove() },
])
```

The same action has to be a button on a desktop and a line of a menu on a phone, and one vnode
cannot be mounted in two places — written as markup it has to be written twice, which is what two
editors here used to do. Written as data it is written once and the head decides:

- **`primary`** is the one thing the screen exists for: filled, blue, with the word on it. One per
  screen, and the one that keeps a button of its own when the head runs out of room.
- **`danger`** never becomes a button. It lives in the `···`, last, behind a rule, in red.
- **`menu`** says the same about an action that is not destructive: in the menu at every width.
- **`href`** makes it a link, **`run`** a button, **`loading`** a button with a spinner, and
  **`disabled`** one that is offered but not possible right now.

An action somebody has no right to is **left out of the array**, not passed with `disabled`: a
menu is a list of what is possible, and a row of dead entries teaches nothing.

Below `collapseBelow` everything but the primary folds behind the `···`, and that primary takes
the line under the name, full width. A head whose actions all folded keeps the `···` up on the
name's line instead — a menu alone on a line of its own reads as something left over.

### Icons

`WxButton` has **no** `icon` prop — it has an `#icon` slot, and an `icon="plus"` written on it
lands on the `<button>` as an attribute and draws nothing at all. The head takes the icon by name
(`ScreenAction.icon`) and draws it correctly; this is one of the reasons to declare the actions
rather than write the buttons yourself.

### What stays out of the head

Saving. A screen with something to save carries `WxActionBar` along its bottom, and the rule is
that the bar duplicates what scrolled away: on a screen exactly as tall as the window, where the
head never leaves it, the bar is the only place the save lives. The head there keeps only what
leads away from the record — a preview, the page on the site.

## 2. The body

**A list** is `WxListScreen`, which is the head plus the views as tabs plus one card holding
nothing but the rows. It takes the same `title`, `subtitle`, `back` and `actions`:

```vue
<wx-list-screen v-model:view="view" :title="title" :views="views" :actions="actions">
  <wx-table :data="page" :columns="columns" flush layout="fixed" @state-change="load" />
</wx-list-screen>
```

Everything else about lists — columns, sorting, filters, the narrow shape, the row's `···` — is in
[Lists](/guide/lists).

**One record** is either a described screen or your own cards. Prefer the described one:

```vue
<wx-screen v-model="values" name="blog.article-form" :errors="errors" :disabled="!canManage" />
```

The tree comes from the server, a project patches it, and a module can add a tab to your editor
without a fork of your file — see [Screens](/guide/screens). Fields belong in groups, and every
group on its own card; a card is a `WxCard`, and the spacing between them is the panel's own
step, never a margin of yours.

## 3. Saving

```vue
<wx-action-bar v-if="canManage">
  <template #state>{{ state }}</template>
  <wx-button type="primary" :loading="saving" @click="save">{{ t('save') }}</wx-button>
</wx-action-bar>
```

It is the last row of the screen and sticks to the bottom of the window while there is anything
left to scroll, so it never covers the end of the page. For that to work the screen has to be a
flex column — `WxMain` gives a screen that carries a bar a floor of one window's height, and the
bar reaches the bottom of it by an auto margin.

Writing over somebody else's work is a question, not a toast: send the `revision` you read with
every write, and when the server says it changed, ask which version wins.

## 4. Height

A screen is as tall as its content unless it says otherwise. One that must be exactly a window
tall — a file manager, a board, an editor with a preview beside it — says so with `data-wx-fill`
(`WxListScreen` has a `fill` prop for it), and `WxMain` answers with the height.

Spell the chain out all the way down. A percentage inside a box that does not know its own height
resolves to nothing, so every box between the screen and the thing that should scroll needs to be
a flex column with `min-height: 0` — including the ones that belong to `WxTabs`, which `:deep()`
is what reaches.

## 5. What the reader may do

```ts
const canManage = computed(() => admin.can('blog.articles.manage'))
```

`can()` answers from the manifest. Some screens write it as a `computed` and some as a plain
boolean — read the line before you write `.value`, because `.value` on a boolean is `undefined`,
and an action guarded by it disappears without a word.

## 6. Words

Every string in the interface comes from the dictionary:

```ts
const t = useTranslate('webx-blog')
```

The keys live in the Composer package (`lang/<locale>/*.php`) and travel to the panel with the
manifest; the English defaults in `messages.ts` are the floor, for a front end with no server
behind it. The panel's own words — `Back`, `Rename`, `Save`, `More` — are in `webx-admin` and
should not be copied into a module's dictionary.

Components of the core carry **English defaults and no dictionary at all**. A file card, an image
editor or a `···` opened by your screen says what you tell it to say, and a module translated into
ten languages with an English dialog in the middle of it is what happens when nobody does.

## 7. Dates

`WxDate`, never `toLocaleString()`: without an explicit locale the format comes from the browser
rather than from the language the panel is being read in, and "today" has to be counted in the
reader's calendar day, not the server's.

```vue
<wx-date :value="row.updated_at" />
```

## 8. Mounting it

A screen is a route of a module, and the module is what the panel is given:

```ts
export function blog(options: BlogOptions = {}): AdminModule[] {
  const path = options.path ?? '/blog'

  return [
    {
      id: 'articles',
      path: `${path}/articles`,
      routes: [
        { path: `${path}/articles`, component: ArticlesPage, props: { base: path } },
        { path: `${path}/articles/:id(\\d+)`, component: ArticleEditorPage, props: { base: path } },
      ],
    },
  ]
}
```

The section's own path travels as a prop, so a panel that mounted the module somewhere else still
links between its own screens correctly. The navigation entry is built from the manifest: a module
the server does not report is not drawn, which is what makes it safe to return several at once.

::: warning A component of the panel is not registered globally
`WxScreenHead`, `WxListScreen` and the rest are imported from `@webx-ui/module-admin`. Forget the
import and `<wx-screen-head>` is still valid HTML: the browser draws an unknown element with
`display: inline` and shows its children, so the screen loses its heading and its buttons without
a single warning — and jsdom behaves the same way, so the test passes too.
:::

## Before calling it done

jsdom computes no layout, so none of this is visible to a test. Open the screen in a browser —
the panel of the playground is at `/panel/` — and check:

- the head at 375 and at your desktop width: the way out, the name and the `···` on one line, the
  primary action full width under it on the phone, and the trail on one line whatever its length;
- `document.scrollWidth` against `clientWidth`: a horizontal scrollbar is something too wide, not
  a window too narrow;
- the dark theme, where every tint becomes a shape of its own and misalignment stops hiding;
- the language: switch the panel to another one and look for English left in the middle of it.
