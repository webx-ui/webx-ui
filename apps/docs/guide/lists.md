# Lists

Most of an admin panel is lists. A section shows rows, narrows them, orders them, and opens one —
and a reader who has learned one list should already know the next. This is the shape they share,
and the reasons behind each part of it. Every number here was measured on a real panel; where a
rule exists it is because something looked broken before it did.

## The frame

```vue
<wx-list-screen v-model:view="view" :title="title" :views="views">
  <template #actions>
    <wx-button type="primary" icon="plus" @click="add">New article</wx-button>
  </template>

  <wx-table :data="page" :columns="columns" flush layout="fixed" @state-change="load" />
</wx-list-screen>
```

The section's name on a line of its own, the one action the section exists for beside it, the views
of the same list as tabs under that, and a card holding nothing but the rows. The card has no
heading: the tab says which view it is and the line above says which section. The search stays
inside the table, because it belongs to the rows rather than to the screen.

`WxListScreen` is in `@webx-ui/module-admin`. Use it rather than arranging the three parts
yourself — five sections each answering "where does the heading go" gave five answers once, and the
cost is not the extra markup, it is that nobody knows where to look.

## The table's own spacing

`flush` tells the table that the box around it does the spacing. Inside a card that is the card's
padding, which is the panel's step (`--wx-gap`: 8 on a phone, 12 on a tablet, 16 on a desktop), and
the table adds nothing of its own — the head reaches the same edges as the rows, the search field
ends where the row of headings ends, and what is left between the head and the first row is that
same step.

Do not set margins on the table or padding on the card to correct it. If something is off by a few
pixels, measure: the distance from the card's edge to the head, to the rows and to the footer has
to be one number.

## Columns

```ts
const columns = computed<TableColumn<ArticleRow>[]>(() => [
  { key: 'cover', label: '', width: 68 },
  { key: 'title', label: t('column-title'), minWidth: 220 },
  { key: 'rubrics', label: t('column-rubrics'), width: 190, hideBelow: 1000 },
  { key: 'date', label: t('column-date'), width: 120, hideBelow: 700, cellClass: 'when' },
  { key: 'status', label: t('column-status'), width: 170 },
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
])
```

**`width` is a floor, not a ceiling.** A fixed table gives a column exactly the number it was
declared, and a value wider than that does not shrink — it is drawn outside its column. Cells clip
what does not fit, so a column that is too narrow cuts its own text instead of painting across the
one beside it, but a cut date is still a mistake. Size a column by its longest value, in the
longest language you ship: "25 сентября в 13:43" is 152px, and German runs longer.

**`minWidth` only means something with `layout="auto"`.** The widths ride on a `<col>`, and
`min-width` is not one of the four properties that apply there: declared, it computes, shows up in
devtools and does nothing at all. A column with `min-width: 130px` and no width measures zero.

**One column carries no number.** That is the one the others give their room to — the name, the
title, the thing the row is recognised by. Everything else is sized; it takes what is left.

**`hideBelow` in the order the columns can be spared.** Who wrote it, then where it belongs, then
when — and what stays at the narrow end is what identifies the row and what can be done to it. Pick
the numbers from the running total rather than by eye: a column should appear at the width where it
actually fits beside the ones already there, or it appears by pushing the title into a corner.

**The `···` column is `rowMenuWidth`.** The menu is a finger target — 44px under
`(pointer: coarse)` — and the cell keeps `--wx-table-padding-x` on either side, so the column has
to be 76. Import the constant; do not write the number.

## Sorting belongs on the headings

`sortable: true` on a column, and the order arrives in `@state-change` with everything else. Put it
in the address, so a link lands on the list somebody meant:

```ts
function sortOf(state?: TableState): Query['sort'] {
  const from = state?.sort ?? sortFromRoute()
  if (!from) return undefined

  return `${from.order === 'desc' ? '-' : ''}${from.key}`
}
```

A leading minus is how the whole panel says "the other way round", and the server reads it the same
way everywhere. Leave the default out of the query string: a link that repeats the default outlives
a change of mind about it.

What this replaces is a pair of buttons over the list saying "by name" and "by count". They are the
same thing said in a second place, they cost a line of chrome, and they cannot say which way round
the order is.

## Filters behind a funnel

Three dropdowns standing open above the rows are three controls to read before any data, and on a
phone that is half the screen. `#filters` puts them behind one button:

```vue
<wx-table
  :filters-count="applied.length"
  :filters-label="panel('filters.title')"
  :data="page"
  :columns="columns"
>
  <template #filters>
    <wx-form-item :label="t('filter-rubric')">
      <wx-select v-model="rubric" :options="rubrics" :placeholder="t('any-rubric')" clearable />
    </wx-form-item>

    <wx-button v-if="applied.length > 0" variant="text" size="sm" block @click="clearFilters">
      {{ panel('filters.reset') }}
    </wx-button>
  </template>

  <template #applied>
    <wx-filter-chips :filters="applied" />
  </template>
</wx-table>
```

A shut panel says nothing about itself, and a list narrowed by something nobody can see looks
broken. Two things answer that: `filtersCount` puts the number on the funnel, and `#applied` is the
strip in the head that says what the filters are set to. The chips are the section's, because only
the section knows that `rubric=2` reads "Rubric: News":

```ts
const applied = computed<AppliedFilter[]>(() => {
  const chips: AppliedFilter[] = []
  const rubric = rubrics.value.find((item) => item.id === query.value.rubric)

  if (rubric) {
    chips.push({
      key: 'rubric',
      label: `${t('filter-rubric')}: ${rubric.title}`,
      clear: () => narrow('rubric', undefined),
    })
  }

  return chips
})
```

`WxFilterChips` draws them the same way in every section. "Reset all" goes inside the panel, with
the fields it resets: beside the chips it reads as one more chip, and it is the only control there
that does not take exactly one filter off.

The two words a list needs the moment it has filters — the name of the funnel and "reset all" — are
the panel's own, under `webx-admin`. The names of the filters belong to whatever is being filtered.

## Narrow: a row becomes an entity

Below `cardsBelow` the table draws a card per row. Left alone that card is the table's own stack of
labelled lines, which at 375px is four hundred pixels for one record. Give it `#cell-card`, and
draw `WxEntityCard`:

```vue
<template #cell-card="{ row }">
  <wx-entity-card
    variant="plain"
    :title="row.title"
    :image="row.cover?.thumb"
    image-size="56px"
    :title-lines="2"
    :subtitle="row.path"
  >
    <template #meta>
      <wx-badge :type="badge(row.status)" dot size="sm">{{ status(row) }}</wx-badge>
      <wx-date :value="row.published_at" />
    </template>
  </wx-entity-card>
</template>

<template #card-actions="{ row }">
  <wx-row-menu :actions="actionsFor(row)" :label="row.title" />
</template>
```

`variant="plain"` because the box around it is the table's own; two boxes are two borders. Pass
`title` as well as any `#title` slot — the prop is what the fallback initial is made of, and without
it a record with no picture loses its media box and starts further left than the rest of the list.

`titleLines` is one by default, because a row of entities reads as a row only while every one is the
same height. Two is for a list where the name is a sentence: an article headline cut to one line on
a phone is half a thought.

The `···` goes in `#card-actions`, the card's own top strip, beside the checkbox — that line exists
anyway as soon as rows can be picked, and a menu inside the entity takes width from a title that is
already short.

**Measure the same box the table measures.** `cardsBelow` is compared against the table's width; a
screen that measures itself instead disagrees by the width of the card's padding, and then the
columns say "row" while the table draws cards:

```ts
const root = useTemplateRef<HTMLElement>('root') // on the element wrapping the table
const width = useElementWidth(root)
const asCards = computed(() => width.value > 0 && width.value < CARDS)
```

Some lists never become cards. A tag is a word and a number; a card for it is 250px of nothing, so
that screen sets `cards-below="0"` and drops columns instead until the word and the `···` are left.

## Dates in a column

`WxDate compact`: the time alone for today — it is the only row in the column wearing a clock, so it
reads as today without spending a word on saying so — a short month for the rest of this year, and
digits once the year has to be said. The full value is in the tip, which is where "when exactly" was
always answered.

That is what a date column costs: the full line is why it had to be 185px wide in Russian. At 120
the room goes back to the name. Cards and detail screens keep the long form — there is space, and
there a date is read rather than scanned.

## Thumbnails

A preview in a row is 44×32 or thereabouts, and the radius has to be the smallest step in the scale
(`--wx-radius-xs`, 8) — the one `WxEntityCard` gives its own media. The next step up is 12, which is
over a third of the box's height and reads as a pill.

The box is not the picture. Give the wrapper a fixed size, `overflow: hidden` and
`object-fit: cover`, and the picture `min-width: 0` — an `<img>` has `min-width: auto`, which is its
natural width, so a 1200px cover takes the column and the ones beside it.

## Editing a name

In a dialog or a popover, never in the cell. A name that quietly turns into an `<input>` reads as a
name: nothing says it can be typed in, and a stray click on a row is a rename nobody asked for.

There is a mechanical reason too. An in-cell box usually saves itself on `blur` — and `blur` does
not bubble, so a listener on the field's wrapper never hears it and clicking away loses what was
typed. `WxRenameButton` is the ready-made popover; a dialog is the other answer, and the one to pick
when the rename is offered from the `···` menu.

Bind Enter on the field as well as on the form. Implicit submission is a browser behaviour, and
inside a dialog that takes the keyboard over it does not always happen: measured, a real Enter
reached the input, was prevented by nobody, and submitted nothing.

## The selection bar

One button — the thing that can only be done to several rows at once — and the rest behind the same
`···` a row has, where the reader already looks for them:

```vue
<wx-action-bar v-if="chosen.length > 0" sticky>
  <template #state>
    <wx-text weight="medium">{{ t('selected', { count: chosen.length }) }}</wx-text>
  </template>

  <wx-button type="primary" :disabled="chosen.length < 2" @click="merge">Merge</wx-button>
  <wx-row-menu :actions="massActions" />
</wx-action-bar>
```

The count goes in `#state`, which is what the slot is for; the buttons keep the other end of the bar
and wrap into rows when there is no width for them.

No × for clearing the selection. Rows are unticked by unticking them, or all at once from the box in
the heading — a button whose whole job is to undo something harmless, standing beside a red
"Delete", reads as a way to close the bar.

## One event, one fetch

Everything the backend needs travels together:

```ts
function onState(state: TableState): void {
  void router.replace({
    query: {
      ...route.query,
      q: state.search === '' ? undefined : state.search,
      sort: sortOf(state),
      page: state.page === 1 ? undefined : String(state.page),
    },
  })

  void load(state)
}
```

The address is the single place the list's state is written, so opening a record and coming back
lands on the same filter, the same order and the same page. `@state-change` fires once on mount as
well, which makes it the initial load too — there is no second place a request can start from.

## What to check before calling it done

jsdom computes no layout, so none of this is visible to a test. Open the section in a browser and
measure:

- the distance from the card's edge to the head, the rows and the footer — one number;
- `scrollWidth` against `clientWidth` on the table and on the document, at 375 and at your desktop
  width — a horizontal scrollbar is a column that is too wide, not a table that is too small;
- every cell's `scrollWidth` against its own width — anything clipped is a column sized by eye;
- the narrow shape: the card, the `···` in its strip, and the selection bar with everything ticked;
- the dark theme, where every tint becomes a shape of its own and misalignments stop hiding.
