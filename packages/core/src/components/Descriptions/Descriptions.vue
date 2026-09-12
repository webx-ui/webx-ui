<script setup lang="ts">
import { computed, provide } from 'vue'
import { descriptionsKey } from './context'
import type { DescriptionsProps } from './types'

defineOptions({ name: 'WxDescriptions' })

const props = withDefaults(defineProps<DescriptionsProps>(), {
  title: undefined,
  columns: 2,
  bordered: false,
  size: 'md',
  layout: 'horizontal',
  labelWidth: undefined,
})

defineSlots<{
  title?: () => unknown
  /** Beside the heading: an edit button, a status. */
  extra?: () => unknown
  default?: () => unknown
}>()

provide(descriptionsKey, {
  get bordered() {
    return props.bordered
  },
  get layout() {
    return props.layout
  },
  get labelWidth() {
    return props.labelWidth
  },
  get columns() {
    return Math.max(1, props.columns)
  },
})

const classes = computed(() => [
  'wx-descriptions',
  `wx-descriptions--${props.size}`,
  `wx-descriptions--${props.layout}`,
  { 'wx-descriptions--bordered': props.bordered },
])

const style = computed(() => ({
  '--wx-descriptions-columns': String(Math.max(1, props.columns)),
  '--wx-descriptions-label': props.labelWidth ?? 'max-content',
}))
</script>

<template>
  <div :class="classes" :style="style">
    <header v-if="title || $slots.title || $slots.extra" class="wx-descriptions__header">
      <div class="wx-descriptions__title">
        <slot name="title">{{ title }}</slot>
      </div>
      <div v-if="$slots.extra" class="wx-descriptions__extra">
        <slot name="extra" />
      </div>
    </header>

    <!--
      A grid rather than a table. The facts are a list of pairs, not tabular data —
      nothing lines up down a column except by accident — and a grid can fold to one
      column in a narrow panel, which a table cannot be talked into.
    -->
    <dl class="wx-descriptions__list">
      <slot />
    </dl>
  </div>
</template>

<style scoped>
.wx-descriptions {
  box-sizing: border-box;
  /* The fold is decided by the panel this sits in, not by the window. */
  container-type: inline-size;
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

.wx-descriptions__header {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  margin-bottom: var(--wx-space-10);
}

.wx-descriptions__title {
  min-width: 0;
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-md);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-descriptions__extra {
  margin-inline-start: auto;
}

.wx-descriptions__list {
  display: grid;
  margin: 0;
  padding: 0;
  font-size: var(--wx-font-size-sm);
}

/*
 * Horizontal: every column is a label and a value, so the grid has twice as many
 * tracks as it has columns. The labels size to their content and share one width,
 * which is what keeps the values aligned down the page.
 */
.wx-descriptions--horizontal .wx-descriptions__list {
  grid-template-columns: repeat(
    var(--wx-descriptions-columns, 2),
    var(--wx-descriptions-label, max-content) 1fr
  );
  gap: var(--wx-descriptions-row-gap) var(--wx-descriptions-column-gap);
}

.wx-descriptions--vertical .wx-descriptions__list {
  grid-template-columns: repeat(var(--wx-descriptions-columns, 2), 1fr);
  gap: var(--wx-descriptions-row-gap) var(--wx-descriptions-column-gap);
}

.wx-descriptions--sm {
  --wx-descriptions-row-gap: var(--wx-space-6);
  --wx-descriptions-column-gap: var(--wx-space-12);
  --wx-descriptions-cell: var(--wx-space-6) var(--wx-space-10);
}

.wx-descriptions--md {
  --wx-descriptions-row-gap: var(--wx-space-8);
  --wx-descriptions-column-gap: var(--wx-space-16);
  --wx-descriptions-cell: var(--wx-space-8) var(--wx-space-12);
}

.wx-descriptions--lg {
  --wx-descriptions-row-gap: var(--wx-space-12);
  --wx-descriptions-column-gap: var(--wx-space-24);
  --wx-descriptions-cell: var(--wx-space-10) var(--wx-space-16);
}

/*
 * Bordered: the grid's gaps become the rules. One pixel of the list's own background
 * shows between the cells, and the cells paint themselves over everything else.
 */
.wx-descriptions--bordered .wx-descriptions__list {
  gap: 1px;
  background: var(--wx-border-default);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  overflow: hidden;
}

/*
 * One column once there is no room for two. The pairs keep their own labels beside
 * their values; it is the columns that fold, not the layout.
 */
@container (max-width: 420px) {
  .wx-descriptions--horizontal .wx-descriptions__list {
    grid-template-columns: var(--wx-descriptions-label, max-content) 1fr;
  }

  .wx-descriptions--vertical .wx-descriptions__list {
    grid-template-columns: 1fr;
  }
}
</style>
