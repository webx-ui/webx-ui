<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useTranslate } from '@webx-ui/module-admin'
import { WxBadge, WxButton, WxIcon, WxText } from '@webx-ui/core'
import AuditAddress from './AuditAddress.vue'
import { differences, statusType } from './addresses'
import { useAuditMessages } from './i18n'
import type { AuditDetails, AuditDetailsColumn } from './types'

/**
 * The expansion of a finding, for every check alike (§4 of the spec): a summary line and, when
 * the check gave one, a table whose cells are drawn by their type — an address opens, a code is
 * coloured, a missing value is said in red, an editor link goes to the record in the panel, and
 * markup quoted from the page is shown as code.
 *
 * The columns have widths by their type, not by what they hold, so the tables of one check line
 * up under each other. A `location` address beside a `url` is where that address leads: the row
 * reads as from → to, and a difference of a slash, `www.` or `https` is named.
 */
const props = defineProps<{
  details: AuditDetails
  /** The count is shown elsewhere (the head of the finding) — the summary line would repeat it. */
  counted?: boolean
}>()

const router = useRouter()

useAuditMessages()

const t = useTranslate('webx-audit')

const columns = computed(() => props.details.table?.columns ?? [])

const redirects = computed(
  () =>
    columns.value.some((column) => column.key === 'url' && column.type === 'url') &&
    columns.value.some((column) => column.key === 'location' && column.type === 'url'),
)

function isSource(column: AuditDetailsColumn): boolean {
  return redirects.value && column.key === 'url'
}

function isTarget(column: AuditDetailsColumn): boolean {
  return redirects.value && column.key === 'location'
}

function cell(row: Record<string, unknown>, column: AuditDetailsColumn): unknown {
  return row[column.key]
}

/** Yes, no — or nothing, when the check could not tell (the other page was not crawled). */
function yesNo(value: unknown): string {
  if (value === null || value === undefined) return ''

  return value ? t('page.yes') : t('page.no')
}

function text(value: unknown): string {
  return value === null || value === undefined ? '' : String(value)
}

function changes(row: Record<string, unknown>) {
  return differences(text(row.url), text(row.location))
}
</script>

<template>
  <div class="wx-audit-details">
    <wx-text v-if="props.details.summary && !props.counted" size="sm">{{
      props.details.summary
    }}</wx-text>

    <div
      v-if="props.details.table && props.details.table.rows.length"
      class="wx-audit-details__scroll"
    >
      <table class="wx-audit-details__table">
        <colgroup>
          <col
            v-for="column in columns"
            :key="column.key"
            :class="`wx-audit-details__col--${column.type}`"
          />
        </colgroup>
        <thead>
          <tr>
            <th v-for="column in columns" :key="column.key">
              {{ column.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in props.details.table.rows" :key="index">
            <td v-for="column in columns" :key="column.key">
              <span v-if="isTarget(column) && text(cell(row, column))" class="wx-audit-details__to">
                <wx-icon
                  name="arrow-right"
                  size="1em"
                  class="wx-audit-details__arrow"
                  :label="t('page.leads-to')"
                />
                <audit-address :href="text(cell(row, column))" strong />
                <wx-badge
                  v-for="change in changes(row)"
                  :key="change"
                  type="default"
                  size="sm"
                  class="wx-audit-details__change"
                  >{{ t(`page.change-${change}`) }}</wx-badge
                >
              </span>
              <audit-address
                v-else-if="column.type === 'url' && text(cell(row, column))"
                :href="text(cell(row, column))"
                :type="isSource(column) ? 'muted' : undefined"
              />
              <wx-badge
                v-else-if="column.type === 'status' && cell(row, column) !== null"
                :type="statusType(cell(row, column))"
                size="sm"
                >{{ text(cell(row, column)) }}</wx-badge
              >
              <template v-else-if="column.type === 'bool'">
                {{ yesNo(cell(row, column)) }}
              </template>
              <wx-text
                v-else-if="column.type === 'missing' && !text(cell(row, column))"
                size="sm"
                tone="danger"
                >{{ t('page.missing') }}</wx-text
              >
              <wx-button
                v-else-if="column.type === 'edit' && text(cell(row, column))"
                size="sm"
                variant="text"
                icon="edit"
                @click="router.push(text(cell(row, column)))"
                >{{ t('page.open-editor') }}</wx-button
              >
              <code v-else-if="column.type === 'code'" class="wx-audit-details__code">{{
                text(cell(row, column))
              }}</code>
              <template v-else>{{ text(cell(row, column)) }}</template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.wx-audit-details {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

/* A wide table scrolls inside itself rather than pushing the panel sideways on a phone. */
.wx-audit-details__scroll {
  overflow-x: auto;
}

/*
 * Fixed layout: a column's width comes from its type, never from its longest cell, so every
 * finding of a check draws the same grid.
 */
.wx-audit-details__table {
  width: 100%;
  min-width: 560px;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: var(--wx-font-size-sm);
}

.wx-audit-details__col--status {
  width: 72px;
}

.wx-audit-details__col--bool {
  width: 96px;
}

.wx-audit-details__col--edit {
  width: 160px;
}

.wx-audit-details__table th {
  color: var(--wx-text-muted);
  font-weight: normal;
  text-align: left;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.wx-audit-details__table th,
.wx-audit-details__table td {
  padding: var(--wx-space-6) var(--wx-space-8);
  border-bottom: 1px solid var(--wx-border-muted);
  vertical-align: middle;
  overflow-wrap: anywhere;
}

.wx-audit-details__table tbody tr:last-child td {
  border-bottom: 0;
}

.wx-audit-details__to {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-audit-details__arrow {
  flex-shrink: 0;
  color: var(--wx-text-muted);
}

.wx-audit-details__change {
  flex-shrink: 0;
}

/* Quoted markup is one long line more often than not; it wraps anywhere rather than scrolling. */
.wx-audit-details__code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}
</style>
