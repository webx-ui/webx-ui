<script setup lang="ts">
import { computed } from 'vue'
import { useDescriptions } from '../Descriptions/context'
import type { DescriptionsItemProps } from '../Descriptions/types'

defineOptions({ name: 'WxDescriptionsItem', inheritAttrs: false })

const props = withDefaults(defineProps<DescriptionsItemProps>(), {
  label: undefined,
  span: 1,
})

defineSlots<{
  label?: () => unknown
  default?: () => unknown
}>()

const list = useDescriptions()

const stacked = computed(() => list.layout === 'vertical')

/*
 * Beside its value, a pair is two grid cells, so spanning two columns means spanning
 * four tracks — and the label keeps the one track it started with. Above its value,
 * the pair is a single cell and spans plainly.
 *
 * Clamped to the columns the list actually has: a pair asking for more tracks than
 * there are does not widen the grid, it spills out of it and takes the placement of
 * every pair after it with it.
 */
const span = computed(() => {
  const columns = Math.min(Math.max(1, props.span), list.columns)
  return stacked.value ? columns : columns * 2 - 1
})

/*
 * Handed to CSS rather than set as `grid-column` here, because the number is only right
 * while the list has all its columns. Folded to one — which is a container query, and so
 * is not knowable from script — every pair spans a single track, and an inline style is
 * exactly what a stylesheet cannot take back.
 */
const style = computed(() => ({ '--wx-descriptions-span': String(span.value) }))
</script>

<template>
  <!--
    Stacked, the pair is one box and the grid places it as one. Side by side, the
    label and the value are grid items of the list itself — that is what lets the
    labels of different pairs share a column and line up. A wrapper here would end
    that, which is why there is not one.
  -->
  <div v-if="stacked" class="wx-descriptions__pair" :style="style">
    <dt class="wx-descriptions__label">
      <slot name="label">{{ label }}</slot>
    </dt>
    <dd class="wx-descriptions__value"><slot /></dd>
  </div>

  <template v-else>
    <dt class="wx-descriptions__label">
      <slot name="label">{{ label }}</slot>
    </dt>
    <dd class="wx-descriptions__value" :style="style"><slot /></dd>
  </template>
</template>

<style>
/*
 * Unscoped: the pair is a fragment whose parts are laid out by the list above it,
 * and the bordered look is switched on from up there. A scoped rule could reach
 * neither.
 */
.wx-descriptions__label {
  min-width: 0;
  color: var(--wx-text-muted);
  font-weight: var(--wx-font-weight-medium);
  line-height: var(--wx-font-line-height-normal);
}

.wx-descriptions__value {
  min-width: 0;
  margin: 0;
  grid-column: span var(--wx-descriptions-span, 1);
  color: var(--wx-text-default);
  line-height: var(--wx-font-line-height-normal);
}

.wx-descriptions__pair {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  grid-column: span var(--wx-descriptions-span, 1);
}

/*
 * Folded to one column there is one track for a value and one for a pair, so every span
 * is one whatever it was asked for. Left alone, a pair spanning two columns still asked
 * for three tracks out of two and was placed on a row of its own past the edge of the
 * grid — which is what threw the labels and the values of every pair after it apart.
 */
@container (max-width: 420px) {
  .wx-descriptions__value,
  .wx-descriptions__pair {
    grid-column: span 1;
  }
}

/*
 * The rules are the gaps. A one-pixel gap over a background in the border colour
 * draws a lattice that is exact at any number of columns and under any span —
 * where a border per cell doubles up along every shared edge, and the rules that
 * would trim it cannot be written without knowing the column count.
 */
.wx-descriptions--bordered .wx-descriptions__label,
.wx-descriptions--bordered .wx-descriptions__value,
.wx-descriptions--bordered .wx-descriptions__pair {
  padding: var(--wx-descriptions-cell, 8px 12px);
  background: var(--wx-bg-surface);
}

.wx-descriptions--bordered.wx-descriptions--horizontal .wx-descriptions__label {
  background: var(--wx-bg-subtle);
}
</style>
