<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxAlert, WxBadge, WxText } from '@webx-ui/core'
import { useAuditMessages } from './i18n'

/**
 * The page's headings as a tree: each one indented by its level, in the order the page has them.
 * The indent is the level itself, not the nesting the page managed — so a level the page jumped
 * over is drawn where it should have been, as an empty dashed row, and the gap is seen rather
 * than read about. Above the tree, what departs from the usual order, in plain words.
 */
const props = defineProps<{
  /** `[level, text]` in document order; null on a run crawled before the outline was kept. */
  outline: [number, string][] | null
  /** How many headings of each level — to tell «none» from «not kept». */
  counts: Record<string, number>
}>()

useAuditMessages()

const t = useTranslate('webx-audit')

type Row = { level: number; text: string; missing: boolean; after?: number }

const rows = computed<Row[]>(() => {
  const out: Row[] = []
  let previous = 0

  for (const [level, text] of props.outline ?? []) {
    // Going down more than one step: the levels in between are the ones the page left out.
    for (let gap = previous + 1; previous > 0 && gap < level; gap++) {
      out.push({ level: gap, text: '', missing: true, after: previous })
    }

    out.push({ level, text, missing: false })
    previous = level
  }

  return out
})

/** What breaks the usual order: one H1, first; each level one step below the one above; text. */
const hints = computed<string[]>(() => {
  const outline = props.outline ?? []

  if (!outline.length) return []

  const ones = outline.filter(([level]) => level === 1).length
  const jumps = rows.value.reduce(
    (count, row, index) =>
      count + (!row.missing && index > 0 && rows.value[index - 1].missing ? 1 : 0),
    0,
  )
  const empty = outline.filter(([, text]) => text === '').length
  const out: string[] = []

  if (ones === 0) out.push(t('page.headings-no-h1'))
  if (ones > 1) out.push(t('page.headings-many-h1', { count: ones }))
  if (ones > 0 && outline[0][0] !== 1) {
    out.push(t('page.headings-h1-not-first', { level: `H${outline[0][0]}` }))
  }
  if (jumps > 0) out.push(t('page.headings-skipped', { count: jumps }))
  if (empty > 0) out.push(t('page.headings-empty', { count: empty }))

  return out
})

const total = computed(() => Object.values(props.counts).reduce((sum, count) => sum + count, 0))
</script>

<template>
  <div class="wx-audit-headings">
    <wx-text v-if="props.outline === null && total > 0" tone="muted">{{
      t('page.headings-recheck')
    }}</wx-text>
    <wx-text v-else-if="!rows.length" tone="muted">{{ t('page.no-headings') }}</wx-text>
    <template v-else>
      <wx-alert v-if="hints.length" type="warning" variant="soft" :title="t('page.headings-hints')">
        <ul class="wx-audit-headings__hints">
          <li v-for="hint in hints" :key="hint">{{ hint }}</li>
        </ul>
      </wx-alert>
      <wx-alert v-else type="success" variant="soft" :title="t('page.headings-fine')" />

      <ol class="wx-audit-headings__tree">
        <li
          v-for="(row, index) in rows"
          :key="index"
          class="wx-audit-headings__row"
          :class="{ 'is-missing': row.missing }"
        >
          <span v-for="step in row.level - 1" :key="step" class="wx-audit-headings__guide" />
          <template v-if="row.missing">
            <span class="wx-audit-headings__gap">H{{ row.level }}</span>
            <wx-text size="sm" tone="warning">{{
              t('page.heading-missing', { level: `H${row.level}`, after: `H${row.after}` })
            }}</wx-text>
          </template>
          <template v-else>
            <wx-badge :type="row.level === 1 ? 'primary' : 'info'" size="sm"
              >H{{ row.level }}</wx-badge
            >
            <span v-if="row.text" class="wx-audit-headings__text">{{ row.text }}</span>
            <wx-text v-else size="sm" tone="danger">{{ t('page.heading-empty') }}</wx-text>
          </template>
        </li>
      </ol>
    </template>
  </div>
</template>

<style scoped>
.wx-audit-headings {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-audit-headings__hints {
  margin: 0;
  padding-inline-start: var(--wx-space-18);
}

.wx-audit-headings__tree {
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-audit-headings__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-height: 32px;
}

/* One step of depth: a thin line down, so the levels read as columns, like a file tree. */
.wx-audit-headings__guide {
  align-self: stretch;
  flex: none;
  width: var(--wx-space-16);
  background: linear-gradient(var(--wx-border-default), var(--wx-border-default)) no-repeat
    calc(var(--wx-space-12) + 1px) 0 / 1px 100%;
}

/* The level that should have been here: the badge's outline, empty, in the warning colour. */
.wx-audit-headings__gap {
  display: inline-flex;
  align-items: center;
  padding: 0 var(--wx-space-6);
  border: 1px dashed var(--wx-color-warning);
  border-radius: var(--wx-radius-sm);
  color: var(--wx-color-warning);
  font-size: var(--wx-font-size-xs);
  line-height: 18px;
}

.wx-audit-headings__text {
  min-width: 0;
  overflow-wrap: anywhere;
}
</style>
