<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import WxDrawer from '../Drawer/Drawer.vue'
import { useElementWidth } from '../../composables/useElementWidth'
import type { ListDetailProps } from './types'

defineOptions({ name: 'WxListDetail' })

const props = withDefaults(defineProps<ListDetailProps>(), {
  filtersWidth: 240,
  listWidth: 380,
  detailMin: 420,
  filtersTitle: 'Filters',
  detailLabel: 'Details',
})

defineSlots<{
  /** What narrows the list: views, folders, filters. The column is optional. */
  filters?: (props: { inline: boolean; close: () => void }) => unknown
  /** The records. */
  list?: (props: { filtersInline: boolean; openFilters: () => void }) => unknown
  /** The record that is open. */
  detail?: (props: { inline: boolean; back: () => void }) => unknown
  /** Shown in the pane's place while nothing is selected. */
  empty?: () => unknown
}>()

/**
 * Whether a record is open. One idea, two jobs: beside the list it picks the detail
 * over the empty state, and on a narrow screen it is what raises the panel.
 */
const open = defineModel<boolean>('open', { default: false })
const filtersOpen = defineModel<boolean>('filtersOpen', { default: false })

const root = ref<HTMLElement | null>(null)

/*
 * The screen measures itself, not the window: this is a pane inside a page inside a
 * shell, and a viewport media query would count the sidebar it does not have.
 */
const width = useElementWidth(root)

function toPx(value: number | string) {
  return typeof value === 'number' ? value : Number.parseFloat(value) || 0
}

/*
 * Both thresholds are the widths the caller gave, added up — not numbers of their
 * own. A wider list or a roomier detail moves them by itself, which is the only way
 * they stay right for a screen we have never seen.
 */
const filtersInline = computed(
  () =>
    width.value === 0 ||
    width.value >= toPx(props.filtersWidth) + toPx(props.listWidth) + props.detailMin,
)

const detailInline = computed(
  () => width.value === 0 || width.value >= toPx(props.listWidth) + props.detailMin,
)

const style = computed(() => ({
  '--wx-list-detail-filters': toLength(props.filtersWidth),
  '--wx-list-detail-list': toLength(props.listWidth),
}))

function toLength(value: number | string) {
  return typeof value === 'number' ? `${value}px` : value
}

function openFilters() {
  filtersOpen.value = true
}

function closeFilters() {
  filtersOpen.value = false
}

function back() {
  open.value = false
}

/*
 * A record stays open when the screen grows — it just stops being a panel and
 * becomes the column it always wanted to be. The filters have no such continuity:
 * their column is back, so the panel has nothing left to show.
 */
watch(filtersInline, (inline) => {
  if (inline) filtersOpen.value = false
})
</script>

<template>
  <div ref="root" class="wx-list-detail" :style="style">
    <div v-if="$slots.filters && filtersInline" class="wx-list-detail__filters">
      <slot name="filters" :inline="true" :close="closeFilters" />
    </div>

    <div class="wx-list-detail__list" :class="{ 'wx-list-detail__list--alone': !detailInline }">
      <slot name="list" :filters-inline="filtersInline" :open-filters="openFilters" />
    </div>

    <section v-if="detailInline" class="wx-list-detail__detail">
      <slot v-if="open" name="detail" :inline="true" :back="back" />
      <div v-else class="wx-list-detail__empty">
        <slot name="empty" />
      </div>
    </section>

    <!--
      Narrow: the detail is not a column but a screen of its own, and the filters are
      a panel. Both are the same slots — the caller writes them once.
    -->
    <wx-drawer
      v-if="!detailInline"
      v-model:open="open"
      side="right"
      size="100%"
      :closable="false"
      :aria-label="detailLabel"
      class="wx-list-detail__drawer"
    >
      <slot name="detail" :inline="false" :back="back" />
    </wx-drawer>

    <wx-drawer
      v-if="$slots.filters && !filtersInline"
      v-model:open="filtersOpen"
      side="left"
      :size="filtersWidth"
      :title="filtersTitle"
      closable
    >
      <slot name="filters" :inline="false" :close="closeFilters" />
    </wx-drawer>
  </div>
</template>

<style scoped>
.wx-list-detail {
  display: flex;
  /* It fills what it is given: a route inside a padding-less `WxMain`, usually. */
  height: 100%;
  min-height: 0;
  min-width: 0;
  background: var(--wx-bg-body);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

.wx-list-detail__filters,
.wx-list-detail__list,
.wx-list-detail__detail {
  display: flex;
  flex-direction: column;
  /* Columns clip; what scrolls inside them is the caller's to say. */
  min-height: 0;
  box-sizing: border-box;
}

.wx-list-detail__filters {
  width: var(--wx-list-detail-filters, 240px);
  flex: 0 0 var(--wx-list-detail-filters, 240px);
  background: var(--wx-bg-surface);
  border-right: 1px solid var(--wx-border-default);
}

.wx-list-detail__list {
  width: var(--wx-list-detail-list, 380px);
  flex: 0 0 var(--wx-list-detail-list, 380px);
  /* A row whose contents refuse to wrap must not widen the column. */
  min-width: 0;
  background: var(--wx-bg-surface);
  border-right: 1px solid var(--wx-border-default);
}

/* Alone, the list is the screen. */
.wx-list-detail__list--alone {
  width: auto;
  flex: 1 1 auto;
  border-right: none;
}

.wx-list-detail__detail {
  flex: 1 1 auto;
  min-width: 0;
}

.wx-list-detail__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--wx-space-10);
  height: 100%;
  color: var(--wx-text-muted);
  text-align: center;
}
</style>

<style>
/* The panel is teleported, so its padding cannot be set from a scoped rule. */
.wx-list-detail__drawer .wx-drawer__body {
  padding: 0;
}
</style>
