<script setup lang="ts">
import { WxCard, WxTabs, type CardPadding, type TabItem, type TabValue } from '@webx-ui/core'
import type { RouteLocationRaw } from 'vue-router'
import WxScreenHead from './ScreenHead.vue'
import type { ScreenAction } from './types'

/**
 * The frame every list of the panel is drawn in.
 *
 * One shape for all of them, because five sections that each answered "where does the heading
 * go" on their own gave five answers — a heading inside the card on `Administrators`, two
 * headings on `SEO`, a search outside the card on `Blocks`, no heading at all on `Files`. A
 * reader who learns one list should already know the next.
 *
 * The shape is: the section's name on the left of its own line, the one action the section
 * exists for on the right of it, the views of the list as tabs under that, and a card holding
 * nothing but the rows. The card has no heading of its own — the tab says which view it is,
 * and the line above says which section (§19). The search stays inside the table, where it is
 * a property of the rows rather than of the screen (§10).
 *
 * That first line is `WxScreenHead`, the same one an editor carries: a list and the record it
 * opens are two screens of one panel, and a head that differs between them is a head the
 * reader has to read twice.
 *
 * What it deliberately does not do is fetch, filter or page. It is a frame; the section still
 * owns its data, and the tabs only say which view is open.
 */
withDefaults(
  defineProps<{
    /** The section's name. The one heading on the screen. */
    title?: string
    /** The line under it, when the name alone does not say which list this is. */
    subtitle?: string
    /** Where the list goes back to, for one that stands under another screen. */
    back?: RouteLocationRaw
    /** What the arrow's tooltip says. The panel's own word for it when not given. */
    backLabel?: string
    /**
     * What the section offers: `New article`, `Check an address`. The one it exists for is
     * `primary`, and it is the one that survives a narrow screen.
     */
    actions?: ScreenAction[]
    /**
     * The views of the same list — "All / Drafts / Published", "Rules / Redirects". Not a
     * navigation between screens: everything under them is the same table.
     */
    views?: TabItem[]
    /** Wrap the rows in a card. Off for a section that brings its own. */
    card?: boolean
    /** Padding of that card; `WxCard`'s own default when not given. */
    padding?: CardPadding
    /** The screen is as tall as the column it is drawn in — a file manager, a board. */
    fill?: boolean
    /** Container width below which the views fold into one switch. */
    collapseBelow?: number
  }>(),
  {
    title: undefined,
    subtitle: undefined,
    back: undefined,
    backLabel: undefined,
    actions: undefined,
    views: undefined,
    card: true,
    padding: undefined,
    fill: false,
    collapseBelow: 560,
  },
)

defineSlots<{
  /** The rows: a table, a grid of cards, a manager. */
  default?: () => unknown
  /**
   * Controls beside the heading that are not one-word actions and so cannot be declared —
   * a switch, a picker. Everything a button can say belongs in `actions`, which is what folds
   * into the `···` on a phone; what is written here stays drawn at every width.
   */
  actions?: () => unknown
}>()

const view = defineModel<TabValue | undefined>('view', { default: undefined })
</script>

<template>
  <div class="wx-list-screen" :class="{ 'is-fill': fill }" :data-wx-fill="fill ? '' : undefined">
    <!-- A list folds its buttons later than an editor does: what stands beside the name here is
         one word and no trail, and a `New page` that takes the whole line on a tablet reads as
         a screen with nothing else on it. -->
    <wx-screen-head
      v-if="title || actions || $slots.actions"
      class="wx-list-screen__head"
      :title="title"
      :subtitle="subtitle"
      :back="back"
      :back-label="backLabel"
      :actions="actions"
      :collapse-below="480"
    >
      <template v-if="$slots.actions" #extra><slot name="actions" /></template>
    </wx-screen-head>

    <!--
      `items` rather than a tab each with its own panel: switching a view must not take the
      table away and put a new one back, or the search somebody typed goes with it.
    -->
    <wx-tabs
      v-if="views && views.length > 0"
      v-model="view"
      class="wx-list-screen__views"
      :items="views"
      :collapse-below="collapseBelow"
      :aria-label="title"
    >
      <wx-card v-if="card" class="wx-list-screen__card" :padding="padding">
        <slot />
      </wx-card>
      <slot v-else />
    </wx-tabs>

    <template v-else>
      <wx-card v-if="card" class="wx-list-screen__card" :padding="padding">
        <slot />
      </wx-card>
      <slot v-else />
    </template>
  </div>
</template>

<style scoped>
.wx-list-screen {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
  /* The width that decides the layout is the screen's own, not the window's. */
  container-type: inline-size;
}

/*
 * A screen that fills gives the height to the rows, not to the heading over them.
 *
 * The chain has to be spelled out all the way down, because a percentage inside a box that
 * does not know its own height resolves to nothing at all (CLAUDE.md §4) — and with tabs the
 * boxes in between belong to `WxTabs`, so `:deep()` is what reaches them. A scoped rule stops
 * at this component's own elements and would silently match nothing.
 */
.wx-list-screen.is-fill {
  min-height: 0;
}

.wx-list-screen.is-fill > :last-child {
  flex: 1 1 auto;
  min-height: 0;
}

.wx-list-screen.is-fill :deep(.wx-tabs),
.wx-list-screen.is-fill :deep(.wx-tabs__layout),
.wx-list-screen.is-fill :deep(.wx-tabs__panels) {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-height: 0;
}

.wx-list-screen.is-fill :deep(.wx-tabs__panel),
.wx-list-screen.is-fill :deep(.wx-tabs__panel > *) {
  flex: 1 1 auto;
  min-height: 0;
}

/* The bar is the one part that keeps its own height. */
.wx-list-screen.is-fill :deep(.wx-tabs__bar) {
  flex: 0 0 auto;
}
</style>
