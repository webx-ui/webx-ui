<script setup lang="ts">
import { computed, ref, useSlots, watch } from 'vue'
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

const emit = defineEmits<{
  /**
   * Whether the chooser still has a column of its own, said whenever it changes and once at
   * the start. The `list` slot is handed the same thing; this is for a head that stands
   * outside the pane and has to offer the way in when the column is gone.
   */
  'filters-inline': [inline: boolean]
  /**
   * Whether an open record still stands beside the list, said the same way.
   *
   * What it is for is the first record: beside the list, a screen that opens on nothing wastes
   * its whole width on the words "choose one", so the caller opens the first by itself — and
   * on a phone that same line would raise a panel over a list nobody has touched yet. Only the
   * pane knows which of the two it is.
   */
  'detail-inline': [inline: boolean]
}>()

defineSlots<{
  /** What narrows the list: views, folders, filters. The column is optional. */
  filters?: (props: { inline: boolean; close: () => void }) => unknown
  /** The records. Left alone by a screen with no `detail`, it is the screen. */
  list?: (props: { filtersInline: boolean; openFilters: () => void }) => unknown
  /** The record that is open. Optional: a list whose records open elsewhere has none. */
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

const slots = useSlots()

/**
 * Whether one record is opened beside the list at all.
 *
 * Without it this is a list with a chooser in front of it — which form's submissions, which
 * folder's files — and the list is the screen rather than a column of one. The difference is
 * the whole of what the narrow screen does: a pane that exists goes into the panel and the
 * chooser keeps its column for longer; a pane that does not means the chooser is what folds
 * away, and what the reader is left looking at is the records.
 */
const hasDetail = computed(() => Boolean(slots.detail))

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
 *
 * `detailMin` is the main pane's floor, and which pane that is depends on whether there
 * is a detail: with one, the column of records stands between the two and is counted;
 * without, the records are the main pane and the chooser folds when they would go under
 * that same floor.
 */
const filtersInline = computed(
  () =>
    width.value === 0 ||
    width.value >=
      toPx(props.filtersWidth) + (hasDetail.value ? toPx(props.listWidth) : 0) + props.detailMin,
)

const detailInline = computed(
  () => width.value === 0 || width.value >= toPx(props.listWidth) + props.detailMin,
)

/** The list is the screen: nothing stands beside it, so it takes what is left. */
const listAlone = computed(() => !hasDetail.value || !detailInline.value)

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
watch(
  filtersInline,
  (inline) => {
    if (inline) filtersOpen.value = false

    emit('filters-inline', inline)
  },
  { immediate: true },
)

/*
 * Only once the pane has been measured. Before that the width is zero and every threshold
 * answers "inline" — which is right for the layout, since a pane that has not been measured
 * renders as columns — and wrong for a caller that would open the first record on the strength
 * of it, on a phone, over a list nobody has touched.
 */
watch(
  [width, detailInline],
  () => {
    if (width.value > 0) emit('detail-inline', detailInline.value)
  },
  { immediate: true },
)
</script>

<template>
  <div ref="root" class="wx-list-detail" :style="style">
    <div v-if="$slots.filters && filtersInline" class="wx-list-detail__filters">
      <slot name="filters" :inline="true" :close="closeFilters" />
    </div>

    <div class="wx-list-detail__list" :class="{ 'wx-list-detail__list--alone': listAlone }">
      <slot name="list" :filters-inline="filtersInline" :open-filters="openFilters" />
    </div>

    <section v-if="hasDetail && detailInline" class="wx-list-detail__detail">
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
      v-if="hasDetail && !detailInline"
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
/*
 * The panel is teleported, so its padding cannot be set from a scoped rule.
 *
 * Neither the body nor the sheet inside it insets anything. A drawer pads what it holds
 * because what it holds is usually a form or a note; this one holds a screen, and a screen
 * brings its own margins — two insets stacked put the same content further from the edge here
 * than the same content is on every other screen of the application.
 */
.wx-list-detail__drawer .wx-drawer__body,
.wx-list-detail__drawer .wx-drawer__content {
  padding: 0;
}
</style>
