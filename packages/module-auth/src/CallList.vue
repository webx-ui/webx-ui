<script setup lang="ts">
import { computed, ref, useTemplateRef, watch } from 'vue'
import {
  useAdmin,
  useTranslate,
  WxDate,
  WxFilterChips,
  type AppliedFilter,
} from '@webx-ui/module-admin'
import {
  WxBadge,
  WxButton,
  WxEntityCard,
  WxFormItem,
  WxIcon,
  WxSelect,
  WxSpace,
  WxTable,
  WxText,
  useElementWidth,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import { createAdminsApi } from './admins'
import { useAuthMessages } from './i18n'
import type { AgentCall, AgentCallFilters, AgentCallOutcome, AgentCallPage } from './types'

/**
 * What agents did through the panel: one row per tool call, newest first.
 *
 * A row is who, which tool, and how it went; what it was called with and why it was refused
 * open underneath, because arguments are a block of JSON and a column of those is a column
 * nobody reads. There is no link from a row to what the call was about — tools are about
 * different things, and a link that was right for one in three would be wrong for the rest.
 *
 * The filters offer the people and the tools that actually appear in the log, as the server
 * lists them, rather than every administrator and every tool on offer.
 */
const admin = useAdmin()
const api = createAdminsApi(admin)
useAuthMessages()

/** Where a row stops being a row. */
const CARDS = 480

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)
const asCards = computed(() => width.value > 0 && width.value < CARDS)

const t = useTranslate('webx-auth')
/* The funnel and 'reset all' are the panel's words, not this module's. */
const panel = useTranslate('webx-admin')

const page = ref<AgentCallPage | null>(null)
const filters = ref<AgentCallFilters>({ users: [], tools: [] })
const loading = ref(false)

/* Words rather than empty strings: a select reads "" as nothing chosen and shows a blank
   control where the option said "everybody". `none` is the server's own word for the stdio
   server, so it travels as it is. */
const user = ref('all')
const tool = ref('all')
const outcome = ref<'any' | AgentCallOutcome>('any')

let last: TableState = { page: 1, perPage: 30, sort: null, search: '' }

function nameOf(who: AgentCall['user']): string {
  if (who === null) return t('calls.local')

  return who.name ?? t('calls.deleted', { id: who.id })
}

const userOptions = computed(() => [
  { value: 'all', label: t('calls.everybody') },
  ...filters.value.users.map((one) => ({
    value: one.id === null ? 'none' : String(one.id),
    label: nameOf(one.id === null ? null : { id: one.id, name: one.name }),
  })),
])

const toolOptions = computed(() => [
  { value: 'all', label: t('calls.all-tools') },
  ...filters.value.tools.map((one) => ({ value: one, label: one })),
])

const outcomeOptions = computed(() => [
  { value: 'any', label: t('calls.any-outcome') },
  { value: 'ok', label: t('calls.only-answered') },
  { value: 'failed', label: t('calls.only-refused') },
  { value: 'dry', label: t('calls.only-dry') },
])

/** Which filters are on, in the reader's words — the chips beside the funnel. */
const applied = computed<AppliedFilter[]>(() => {
  const chips: AppliedFilter[] = []

  const who = userOptions.value.find((one) => one.value === user.value)
  if (user.value !== 'all' && who) {
    chips.push({
      key: 'user',
      label: `${t('calls.filter-user')}: ${who.label}`,
      clear: () => (user.value = 'all'),
    })
  }

  if (tool.value !== 'all') {
    chips.push({
      key: 'tool',
      label: `${t('calls.filter-tool')}: ${tool.value}`,
      clear: () => (tool.value = 'all'),
    })
  }

  const how = outcomeOptions.value.find((one) => one.value === outcome.value)
  if (outcome.value !== 'any' && how) {
    chips.push({
      key: 'outcome',
      label: `${t('calls.filter-outcome')}: ${how.label}`,
      clear: () => (outcome.value = 'any'),
    })
  }

  return chips
})

function clearFilters(): void {
  user.value = 'all'
  tool.value = 'all'
  outcome.value = 'any'
}

/*
 * The date and the outcome are the two a reader scans down; the rest give way as the screen
 * narrows, the client first — it is the same word on every row of one connection.
 */
const columns = computed<TableColumn<AgentCall>[]>(() => {
  if (asCards.value) return [{ key: 'card', label: '' }]

  return [
    { key: 'at', label: t('calls.when') },
    { key: 'user', label: t('calls.who'), hideBelow: 640 },
    { key: 'tool', label: t('calls.tool') },
    { key: 'outcome', label: t('calls.outcome') },
    { key: 'duration_ms', label: t('calls.took'), align: 'right', hideBelow: 820 },
    { key: 'client', label: t('calls.client'), hideBelow: 960 },
  ]
})

watch([user, tool, outcome], () => load({ ...last, page: 1 }))

async function load(state: TableState): Promise<void> {
  last = state
  loading.value = true

  try {
    page.value = await api.calls({
      user: user.value === 'all' ? null : user.value,
      tool: tool.value === 'all' ? null : tool.value,
      outcome: outcome.value === 'any' ? null : outcome.value,
      page: state.page,
      per_page: state.perPage,
    })

    filters.value = page.value.filters
  } finally {
    loading.value = false
  }
}

/** A refusal is a refusal whether or not it was a dry run; a dry run that went through is one. */
function outcomeOf(row: AgentCall): { type: 'success' | 'danger' | 'info'; label: string } {
  if (!row.ok) return { type: 'danger', label: t('calls.refused') }
  if (row.dry_run) return { type: 'info', label: t('calls.dry-run') }

  return { type: 'success', label: t('calls.answered') }
}

/** Only a row with something underneath gets the disclosure. */
function hasDetails(row: AgentCall): boolean {
  return row.arguments !== null || row.error !== null
}

defineExpose({ reload: () => load(last) })
</script>

<template>
  <div ref="root" class="wx-call-list">
    <wx-table
      :data="page"
      :columns="columns"
      row-key="id"
      flush
      :loading="loading"
      :clickable="false"
      :expandable="!asCards"
      :expandable-if="hasDetails"
      :empty-text="t('calls.empty')"
      :cards-below="CARDS"
      :filters-count="applied.length"
      :filters-label="panel('filters.title')"
      @state-change="load"
    >
      <template #filters>
        <wx-form-item :label="t('calls.filter-user')">
          <wx-select v-model="user" :options="userOptions" size="sm" />
        </wx-form-item>

        <wx-form-item :label="t('calls.filter-tool')">
          <wx-select v-model="tool" :options="toolOptions" size="sm" />
        </wx-form-item>

        <wx-form-item :label="t('calls.filter-outcome')">
          <wx-select v-model="outcome" :options="outcomeOptions" size="sm" />
        </wx-form-item>

        <wx-button v-if="applied.length > 0" variant="text" size="sm" block @click="clearFilters">
          <template #icon><wx-icon name="close" /></template>
          {{ panel('filters.reset') }}
        </wx-button>
      </template>

      <template #applied>
        <wx-filter-chips :filters="applied" />
      </template>

      <template #cell-at="{ row }">
        <wx-date :value="row.at" tone="default" compact />
      </template>

      <template #cell-user="{ row }">
        <wx-text :tone="row.user === null || row.user.name === null ? 'muted' : 'default'">
          {{ nameOf(row.user) }}
        </wx-text>
      </template>

      <template #cell-tool="{ row }">
        <wx-text mono size="sm">{{ row.tool }}</wx-text>
      </template>

      <template #cell-outcome="{ row }">
        <wx-badge :type="outcomeOf(row).type" dot>{{ outcomeOf(row).label }}</wx-badge>
      </template>

      <template #cell-duration_ms="{ row }">
        <wx-text size="sm" tone="muted">{{ t('calls.ms', { count: row.duration_ms }) }}</wx-text>
      </template>

      <template #cell-client="{ row }">
        <wx-text v-if="row.client" size="sm">{{ row.client }}</wx-text>
        <wx-text v-else size="sm" tone="muted">—</wx-text>
      </template>

      <!-- What it was called with, and why it was refused: a block of text, not a column. -->
      <template #expanded="{ row }">
        <div class="wx-call-list__details">
          <wx-text v-if="row.error" tone="danger" size="sm" class="wx-call-list__error">
            <strong>{{ t('calls.error') }}:</strong> {{ row.error }}
          </wx-text>

          <pre v-if="row.arguments" class="wx-call-list__arguments">{{ row.arguments }}</pre>
          <wx-text v-else size="sm" tone="muted">{{ t('calls.no-arguments') }}</wx-text>
        </div>
      </template>

      <!--
        Narrow, a row is the tool and who ran it, with the outcome as a badge and the details
        underneath: there is no disclosure column on a card, so what would open is simply shown.
      -->
      <template #cell-card="{ row }">
        <wx-entity-card variant="plain" :title="row.tool" :subtitle="nameOf(row.user)">
          <!-- Not the first letter of the tool's name, which is what the card draws for a
               title with no picture: a call is not a thing with a face. -->
          <template #media>
            <span class="wx-call-list__glyph" aria-hidden="true"><wx-icon name="sliders" /></span>
          </template>

          <template #meta>
            <wx-badge :type="outcomeOf(row).type" dot size="sm">{{
              outcomeOf(row).label
            }}</wx-badge>
            <wx-date :value="row.at" compact />
            <wx-text v-if="row.client" size="xs" tone="muted">{{ row.client }}</wx-text>
          </template>

          <wx-space
            v-if="hasDetails(row)"
            direction="vertical"
            size="xs"
            class="wx-call-list__card-details"
          >
            <wx-text v-if="row.error" tone="danger" size="sm">{{ row.error }}</wx-text>
            <pre v-if="row.arguments" class="wx-call-list__arguments">{{ row.arguments }}</pre>
          </wx-space>
        </wx-entity-card>
      </template>
    </wx-table>
  </div>
</template>

<style>
.wx-call-list {
  min-width: 0;
}

.wx-call-list__details {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  padding: var(--wx-space-8) var(--wx-space-16);
}

.wx-call-list__card-details {
  margin-top: var(--wx-space-8);
}

.wx-call-list__glyph {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: var(--wx-text-muted);
  background: var(--wx-bg-subtle);
}

.wx-call-list__arguments {
  margin: 0;
  padding: var(--wx-space-8) var(--wx-space-12);
  max-height: 320px;
  overflow: auto;
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-subtle);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  line-height: var(--wx-font-line-height-relaxed);
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
