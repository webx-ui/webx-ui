<script setup lang="ts">
import { computed } from 'vue'
import { WxBadge, WxText } from '@webx-ui/core'
import { toneBadge } from './tones'
import type { ColumnValue } from './types'

/**
 * One satellite's value in a row of the list (`ProductColumns`, §7.4) — drawn by its shape, so
 * that a satellite gets a column without this package knowing it:
 *
 * - words or a number — as they are;
 * - a record `{ name }` — its name, muted when `visible: false` (a brand taken off the site stays
 *   on its products, and the list is where somebody sees that it does);
 * - a record with a tone, `{ name, color }` — a tag of that tone (a stock status);
 * - a list of those — tags side by side (labels), in the order the server gave.
 */
defineOptions({ name: 'WxCatalogColumnValue' })

const props = defineProps<{ value: ColumnValue | undefined }>()

interface Piece {
  key: string
  name: string
  tone: string | null
  muted: boolean
}

function piece(value: unknown, index: number): Piece | null {
  if (value === null || value === undefined || value === '') return null

  if (typeof value === 'object' && !Array.isArray(value) && 'name' in value) {
    const record = value as { id?: unknown; name?: unknown; color?: unknown; visible?: unknown }

    return {
      key: String(record.id ?? index),
      name: String(record.name ?? ''),
      tone: typeof record.color === 'string' ? record.color : null,
      muted: record.visible === false,
    }
  }

  if (typeof value === 'boolean') {
    return { key: String(index), name: value ? '✓' : '—', tone: null, muted: !value }
  }

  return { key: String(index), name: String(value), tone: null, muted: false }
}

const pieces = computed<Piece[]>(() =>
  (Array.isArray(props.value) ? props.value : [props.value])
    .map((one, index) => piece(one, index))
    .filter((one): one is Piece => one !== null),
)
</script>

<template>
  <span v-if="pieces.length === 0" class="wx-catalog-column-value is-empty">—</span>
  <span v-else class="wx-catalog-column-value">
    <template v-for="one in pieces" :key="one.key">
      <wx-badge v-if="one.tone !== null" :type="toneBadge(one.tone)" size="sm">
        {{ one.name }}
      </wx-badge>
      <wx-text v-else size="sm" truncate :tone="one.muted ? 'muted' : undefined">
        {{ one.name }}
      </wx-text>
    </template>
  </span>
</template>

<style scoped>
.wx-catalog-column-value {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  max-width: 100%;
}

.wx-catalog-column-value.is-empty {
  color: var(--wx-text-muted);
}
</style>
