<script setup lang="ts">
import { computed, useSlots, useTemplateRef } from 'vue'
import { WxAction, WxButton, WxHeading, WxIcon, WxText, useElementWidth } from '@webx-ui/core'
import type { HeadingLevel } from '@webx-ui/core'
import type { RouteLocationRaw } from 'vue-router'
import WxBackButton from './BackButton.vue'
import WxRowMenu from './RowMenu.vue'
import { useTranslate } from './i18n'
import type { ScreenAction } from './types'

/**
 * The first line of every screen in the panel: where the reader is, what it is called, and
 * what can be done with it.
 *
 * Every screen used to answer that on its own, and eight screens gave eight answers — a
 * heading at three different sizes, the way out as an arrow on four of them and as a line of
 * breadcrumbs on the rest, the row of buttons folding into a `···` on two editors and simply
 * wrapping onto a third line everywhere else. None of it is a screen's business: a reader who
 * has learned one screen already knows where to look on the next, and that only holds if there
 * is one head rather than one per section.
 *
 * The shape is four things, in this order: the way out, the name (with the state said beside
 * it), the line under it that says which record this is, and the actions at the other end.
 *
 * Actions are declared rather than drawn, because the same action has to appear as a button on
 * a desktop and as a line of a menu on a phone — and one vnode cannot be mounted in two places
 * (CLAUDE.md §4). Written as markup they had to be written twice, which is what the page and
 * article editors did; written as data they are written once and this decides. Two rules it
 * keeps rather than leaving to each screen: the destructive action is never a button in the
 * head — it lives in the `···`, where it is a word rather than a red rectangle beside the
 * name — and the one action the screen exists for (`primary`) is the one that survives a
 * narrow screen, with everything else folded behind the menu (§18.3).
 *
 * What it does not do is scroll with anything, stick to anything or know what saving means.
 * A screen that has something to save carries `WxActionBar` along its bottom, and on the
 * screens where the head never leaves the window that bar is the only place the save lives
 * (§3.3) — the head does not duplicate it.
 */
const props = withDefaults(
  defineProps<{
    /** What the screen is called. The one heading on it (§19). */
    title?: string
    /** The line under the name: which record this is — a slug, a form, a count. */
    subtitle?: string
    /**
     * The way out of the screen. A route draws a link; `true` draws the same arrow and emits
     * `back` instead, which is what a pane inside `WxListDetail` needs — on a phone the drawer
     * carries no close of its own and the pane has to bring one (CLAUDE.md §4).
     */
    back?: RouteLocationRaw | boolean
    /** What the arrow's tooltip says. The panel's own word for it when not given. */
    backLabel?: string
    /** `2` for a screen, `3` for a pane standing inside one. */
    level?: HeadingLevel
    /** What can be done here, in the order it should read. */
    actions?: ScreenAction[]
    /** Width of the head below which the actions fold into the `···`. */
    collapseBelow?: number
    /**
     * Draws a rule under the head. For a screen exactly as tall as the window, where the head
     * never scrolls away and needs an edge of its own; a head that scrolls does not.
     */
    divider?: boolean
  }>(),
  {
    title: undefined,
    subtitle: undefined,
    back: undefined,
    backLabel: undefined,
    level: 2,
    actions: undefined,
    collapseBelow: 720,
    divider: false,
  },
)

const emit = defineEmits<{
  /** The way out was taken on a head whose `back` is `true` rather than a route. */
  back: []
}>()

defineSlots<{
  /** Above the name: breadcrumbs, when the screen stands somewhere in a tree. */
  trail?: () => unknown
  /** Beside the name: the state of the record — a status badge, the way to rename it. */
  'title-after'?: () => unknown
  /** The line under the name, when it is more than a string. */
  subtitle?: () => unknown
  /**
   * Controls that are not one-word actions and stay whatever the width — the pair of arrows
   * that walks a pile of records one at a time, say.
   */
  extra?: () => unknown
}>()

const t = useTranslate('webx-admin')
const slots = useSlots()

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)

/* `0` is "not measured yet", and the roomy case is the one to assume until it is. */
const narrow = computed(() => width.value > 0 && width.value < props.collapseBelow)

const offered = computed<ScreenAction[]>(() => props.actions ?? [])

/** At most one, whatever the screen declared: a screen with two main actions has none (§18.3). */
const primary = computed<ScreenAction | null>(
  () => offered.value.find((action) => action.primary) ?? null,
)

/** Wide: everything that is not a menu line. Narrow: the one action worth the room. */
const buttons = computed<ScreenAction[]>(() => {
  if (narrow.value) return primary.value ? [primary.value] : []

  return offered.value.filter((action) => !action.menu && !action.danger)
})

const menu = computed<ScreenAction[]>(() => {
  if (narrow.value) return offered.value.filter((action) => action !== primary.value)

  return offered.value.filter((action) => action.menu || action.danger)
})

/** A route rather than the bare `true` that asks for the event. */
const backTo = computed<RouteLocationRaw | null>(() =>
  props.back && props.back !== true ? (props.back as RouteLocationRaw) : null,
)

const hasActions = computed(
  () => buttons.value.length > 0 || menu.value.length > 0 || Boolean(slots.extra),
)
</script>

<template>
  <div
    ref="root"
    class="wx-screen-head"
    :data-level="level"
    :class="{
      'is-narrow': narrow,
      'has-button': buttons.length > 0,
      'has-trail': Boolean($slots.trail),
      'has-divider': divider,
    }"
  >
    <!-- Its own line, and it scrolls sideways rather than wrapping: a trail is one line by
         definition, and a second line of it pushes the name of the record down the screen. -->
    <div v-if="$slots.trail" class="wx-screen-head__trail"><slot name="trail" /></div>

    <wx-back-button
      v-if="backTo"
      class="wx-screen-head__back"
      :to="backTo"
      :label="backLabel"
      tooltip-side="bottom"
    />
    <wx-action
      v-else-if="back"
      class="wx-screen-head__back"
      icon="arrow-left"
      size="sm"
      tooltip-side="bottom"
      :title="backLabel ?? t('editor.back')"
      @click="emit('back')"
    />

    <div class="wx-screen-head__name">
      <wx-heading v-if="title" :level="level" truncate class="wx-screen-head__title">
        {{ title }}
      </wx-heading>
      <slot name="title-after" />
    </div>

    <wx-text
      v-if="subtitle || $slots.subtitle"
      class="wx-screen-head__subtitle"
      size="sm"
      tone="muted"
      truncate
    >
      <slot name="subtitle">{{ subtitle }}</slot>
    </wx-text>

    <div v-if="hasActions" class="wx-screen-head__actions">
      <slot name="extra" />

      <wx-button
        v-for="action in buttons"
        :key="action.key"
        class="wx-screen-head__button"
        :class="{ 'is-primary': action.primary }"
        :type="action.primary ? 'primary' : 'default'"
        :variant="action.primary ? 'solid' : (action.variant ?? 'outline')"
        :loading="action.loading"
        :disabled="action.disabled"
        :href="action.href"
        :target="action.href ? (action.target ?? '_blank') : undefined"
        :rel="action.href ? 'noopener' : undefined"
        @click="action.run?.()"
      >
        <template v-if="action.icon" #icon><wx-icon :name="action.icon" /></template>
        {{ action.label }}
      </wx-button>

      <!-- The same menu a row of a list carries, so that one `···` means one thing in the
           panel — destructive last, behind a rule, and every line with the word on it. -->
      <wx-row-menu
        v-if="menu.length > 0"
        :actions="menu"
        size="lg"
        :label="title ?? t('editor.more')"
      />
    </div>
  </div>
</template>

<style scoped>
/*
 * A grid of named areas rather than a row that wraps.
 *
 * Everything on the head answers to the name: the way out is at the start of its line and the
 * actions at the end of the same one, both centred on it — as flex items they lined up with the
 * top of the block instead, which on a screen with a trail put them level with four words of
 * small grey type and left the name hanging under them. The trail takes a line of its own above,
 * and the line that says which record this is takes one below, indented to the name.
 *
 * The space between the columns is margins on the two outer elements rather than a `column-gap`:
 * a head with no way out and no actions still has those tracks, and a gap would indent the name
 * by the width of two things that are not there.
 */
.wx-screen-head {
  /*
   * The line the way out and the actions are centred on: the heading's own, which is its font
   * size times the tight leading both of them are set in. It has to be written out because what
   * they stand beside is the name *block*, and that block is two lines whenever a badge does not
   * fit next to the name — centring on it would put the arrow between the two, which is what a
   * head that had no rule about this did.
   */
  --wx-head-line: calc(var(--wx-font-size-2xl) * var(--wx-font-line-height-tight));

  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  grid-template-areas:
    'back name actions'
    '. subtitle .';
  align-items: start;
  row-gap: var(--wx-space-4);
  /* A head inside a column that gives its height to the rows keeps only what it needs. */
  flex: none;
  min-width: 0;
}

/* A pane inside a screen carries `level="3"`, and its heading is a size smaller. */
.wx-screen-head[data-level='3'] {
  --wx-head-line: calc(var(--wx-font-size-xl) * var(--wx-font-line-height-tight));
}

.wx-screen-head.has-trail {
  grid-template-areas:
    'trail trail trail'
    'back name actions'
    '. subtitle .';
}

.wx-screen-head.has-divider {
  padding-block-end: var(--wx-space-12);
  border-block-end: 1px solid var(--wx-border-default);
}

/*
 * The trail scrolls sideways and says nothing about it.
 *
 * A path to a page four levels down is longer than a phone is wide, and a trail that wraps is
 * two lines of the smallest type on the screen standing between the reader and the name of what
 * they opened. A scrollbar under it would be a third thing to look at, so it is hidden: the
 * trail is a finger away on a touch screen, and on a desktop there is room for it.
 */
.wx-screen-head__trail {
  grid-area: trail;
  min-width: 0;
  overflow-x: auto;
  /* Nothing above should scroll because the trail reached its end. */
  overscroll-behavior-x: contain;
  /*
   * And it starts at the beginning of the path. The crumbs arrive after the record does, and a
   * scroller whose content grows under it is one the browser anchors — which left the trail
   * scrolled to its far end, with the section's own name cut in half at the left edge.
   */
  overflow-anchor: none;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.wx-screen-head__trail::-webkit-scrollbar {
  display: none;
}

/* The trail is one line whatever its length — wrapping is what the scroll replaces. */
.wx-screen-head__trail > :deep(*) {
  flex-wrap: nowrap;
  white-space: nowrap;
}

/*
 * And a crumb keeps its whole width. `WxBreadcrumb` lays its items out to shrink and cut
 * themselves off with an ellipsis, which is the right answer inside a box that cannot scroll and
 * the wrong one inside this: what shrinks away is exactly what the scroll exists to reach.
 */
.wx-screen-head__trail :deep(.wx-breadcrumb__list) {
  flex-wrap: nowrap;
}

.wx-screen-head__trail :deep(.wx-breadcrumb-item) {
  flex: none;
}

.wx-screen-head__trail :deep(.wx-breadcrumb-item__link) {
  overflow: visible;
}

/*
 * The way out stays small (30) while the actions at the other end are 42: it belongs to the
 * heading beside it rather than to that row, and at 42 it argues with the heading itself (§23.8).
 */
/*
 * `:deep()` because the class is ours and the element it rides belongs to `WxAction`, which
 * carries a tooltip beside itself and so has a fragment for a root: Vue puts this component's
 * scope attribute only on a single-rooted child, and a scoped rule without it matches nothing
 * at all (CLAUDE.md §4). Silently — the button kept its class, landed in the right cell because
 * an item with no area of its own is placed in the first free one, and stood flush against the
 * heading because the margin was never applied.
 */
.wx-screen-head > :deep(.wx-screen-head__back) {
  grid-area: back;
  margin-inline-end: var(--wx-space-8);
  /* Half the difference between the line and the control puts the two centres together. */
  margin-block-start: calc((var(--wx-head-line) - 30px) / 2);
}

.wx-screen-head__name {
  grid-area: name;
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex-wrap: wrap;
  min-width: 0;
}

.wx-screen-head__title {
  min-width: 0;
}

.wx-screen-head__subtitle {
  grid-area: subtitle;
  min-width: 0;
}

.wx-screen-head__actions {
  grid-area: actions;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--wx-space-8);
  flex-wrap: wrap;
  min-width: 0;
  margin-inline-start: var(--wx-space-12);
  /* Centred on the name's line, the way the arrow at the other end of it is. */
  margin-block-start: calc((var(--wx-head-line) - var(--wx-size-control-md)) / 2);
}

/*
 * One height for everything on this line (§23.8): a button with a word on it is 42, and an
 * icon button matches it at `lg`. On a phone the shell drops icon buttons to 36, and the words
 * follow — the height of a button is read off `--wx-size-control-md` and never declared on the
 * button itself, so handing the row a different value is all it takes (CLAUDE.md §4).
 */
.wx-screen-head__actions :deep(.wx-action) {
  --wx-action-size: var(--wx-size-control-md);
}

.wx-admin--drawer .wx-screen-head__actions {
  --wx-size-control-md: 36px;
}

/*
 * Narrow, and there is a button: the actions take the line under the name, whole, and the one
 * button with a word on it takes what the `···` leaves. Three things elbowing each other across
 * 343px is not a row, and a primary button too narrow to hold its word is not a button.
 *
 * Narrow with nothing but the `···` — a pane whose tools all folded — keeps it up on the name's
 * line: a menu alone on a line of its own reads as something left over.
 */
.wx-screen-head.is-narrow.has-button {
  grid-template-areas:
    'back name name'
    '. subtitle subtitle'
    'actions actions actions';
}

.wx-screen-head.is-narrow.has-button.has-trail {
  grid-template-areas:
    'trail trail trail'
    'back name name'
    '. subtitle subtitle'
    'actions actions actions';
}

.wx-screen-head.is-narrow.has-button .wx-screen-head__actions {
  margin-inline-start: 0;
  margin-block-start: var(--wx-space-8);
}

/* `:deep()` because the class is ours and the element it rides belongs to `WxButton`. */
.wx-screen-head.is-narrow .wx-screen-head__actions > :deep(.wx-screen-head__button) {
  flex: 1 1 auto;
}
</style>
