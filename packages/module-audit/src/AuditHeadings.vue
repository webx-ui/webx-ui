<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxBadge, WxText, WxTooltip } from '@webx-ui/core'
import { useAuditMessages } from './i18n'

/**
 * The page's headings as a tree: each one indented by its level, in the order the page has them.
 * The indent is the level itself, not the nesting the page managed — an H4 straight under an H2
 * stands two steps in, with a gap a reader sees, and its badge says which level was jumped.
 */
const props = defineProps<{
  /** `[level, text]` in document order; null on a run crawled before the outline was kept. */
  outline: [number, string][] | null
  /** How many headings of each level — to tell «none» from «not kept». */
  counts: Record<string, number>
}>()

useAuditMessages()

const t = useTranslate('webx-audit')

const rows = computed(() =>
  (props.outline ?? []).map(([level, text], index, all) => {
    const previous = index === 0 ? 0 : all[index - 1][0]

    return {
      level,
      text,
      skipped: previous > 0 && level > previous + 1 ? `H${previous} → H${level}` : null,
    }
  }),
)

const total = computed(() => Object.values(props.counts).reduce((sum, count) => sum + count, 0))
</script>

<template>
  <wx-text v-if="props.outline === null && total > 0" tone="muted">{{
    t('page.headings-recheck')
  }}</wx-text>
  <wx-text v-else-if="!rows.length" tone="muted">{{ t('page.no-headings') }}</wx-text>
  <ol v-else class="wx-audit-headings">
    <li v-for="(row, index) in rows" :key="index" class="wx-audit-headings__row">
      <span v-for="step in row.level - 1" :key="step" class="wx-audit-headings__guide" />
      <wx-tooltip
        v-if="row.skipped"
        :content="t('page.heading-skipped', { skip: row.skipped })"
        :max-width="320"
      >
        <wx-badge type="warning" size="sm" tabindex="0">H{{ row.level }}</wx-badge>
      </wx-tooltip>
      <wx-badge v-else :type="row.level === 1 ? 'primary' : 'info'" size="sm"
        >H{{ row.level }}</wx-badge
      >
      <span v-if="row.text" class="wx-audit-headings__text">{{ row.text }}</span>
      <wx-text v-else size="sm" tone="danger">{{ t('page.heading-empty') }}</wx-text>
    </li>
  </ol>
</template>

<style scoped>
.wx-audit-headings {
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

.wx-audit-headings__text {
  min-width: 0;
  overflow-wrap: anywhere;
}
</style>
