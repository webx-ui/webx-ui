<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useTranslate } from '@webx-ui/module-admin'
import { WxBadge, WxButton, WxLink, WxText, type BadgeType } from '@webx-ui/core'
import { useAuditMessages } from './i18n'
import type { AuditDetails, AuditDetailsColumn } from './types'

/**
 * The expansion of a finding, for every check alike (§4 of the spec): a summary line and, when
 * the check gave one, a table whose cells are drawn by their type — an address opens, a code is
 * coloured, a missing value is said in red, an editor link goes to the record in the panel, and
 * markup quoted from the page is shown as code.
 */
const props = defineProps<{ details: AuditDetails }>()

const router = useRouter()

useAuditMessages()

const t = useTranslate('webx-audit')

function statusType(value: unknown): BadgeType {
  const code = Number(value)

  if (code >= 500 || code === 0) return 'danger'
  if (code >= 400) return 'warning'
  if (code >= 300) return 'info'

  return 'success'
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
</script>

<template>
  <div class="wx-audit-details">
    <wx-text v-if="props.details.summary" size="sm">{{ props.details.summary }}</wx-text>

    <div
      v-if="props.details.table && props.details.table.rows.length"
      class="wx-audit-details__scroll"
    >
      <table class="wx-audit-details__table">
        <thead>
          <tr>
            <th v-for="column in props.details.table.columns" :key="column.key">
              {{ column.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in props.details.table.rows" :key="index">
            <td v-for="column in props.details.table.columns" :key="column.key">
              <template v-if="column.type === 'url' && text(cell(row, column))">
                <wx-link
                  :href="text(cell(row, column))"
                  target="_blank"
                  class="wx-audit-details__url"
                  >{{ text(cell(row, column)) }}</wx-link
                >
              </template>
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

.wx-audit-details__table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--wx-font-size-sm);
}

.wx-audit-details__table th {
  color: var(--wx-text-muted);
  font-weight: normal;
  text-align: left;
  white-space: nowrap;
}

.wx-audit-details__table th,
.wx-audit-details__table td {
  padding: var(--wx-space-4) var(--wx-space-8);
  border-bottom: 1px solid var(--wx-border-muted);
  vertical-align: top;
}

/* Quoted markup is one long line more often than not; it wraps anywhere rather than scrolling. */
.wx-audit-details__code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

.wx-audit-details__url {
  overflow-wrap: anywhere;
}
</style>
