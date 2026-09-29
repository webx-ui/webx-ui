<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  toast,
  WxAvatar,
  WxButton,
  WxDialog,
  WxEmpty,
  WxInput,
  WxSkeleton,
  WxText,
} from '@webx-ui/core'
import DateText from './DateText.vue'
import { useAdmin } from './admin'
import { useErrorText } from './errors'
import {
  createHistoryApi,
  historyValue,
  useHistorySubject,
  type HistoryChange,
  type HistoryEntry,
  type HistoryRun,
} from './history'
import { useTranslate } from './i18n'

defineOptions({ name: 'WxHistory' })

/**
 * Who changed a record, when, through which door, and what exactly (WEBX_UI_HISTORY.md §5).
 *
 * The frame's and not a section's, like the notes: the catalogue wants it first, and a copy per
 * module is a copy of the rule about who may read it. On a screen it is the `wx-history` node —
 * a tab of a form is one line of the description or one patch of a project — and the id comes
 * from the editor hosting the screen (`provideHistorySubject`), since the same JSON describes
 * every record.
 *
 * A row made inside an import or a bulk action links to the run: the run is where the journal
 * says what was done to all of them at once, and the row only says what this record got.
 */
const props = withDefaults(
  defineProps<{
    /** The type the module registered: `catalog.product`. */
    type: string
    /** The record; the hosting editor's when omitted. */
    id?: number | string | null
    /** Heading above the feed; the panel's own word by default, none with `''`. */
    title?: string
  }>(),
  { id: undefined, title: undefined },
)

const admin = useAdmin()
const t = useTranslate('webx-admin')
const message = useErrorText()
const subject = useHistorySubject()

const entries = ref<HistoryEntry[]>([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
const loadingMore = ref(false)

const recordId = computed(() => props.id ?? subject?.id.value ?? null)
const api = computed(() =>
  recordId.value === null ? null : createHistoryApi(admin, props.type, recordId.value),
)
const heading = computed(() => props.title ?? t('history.title'))

async function load(): Promise<void> {
  entries.value = []
  page.value = 1
  lastPage.value = 1

  if (api.value === null) return

  loading.value = true

  try {
    const first = await api.value.list(1)
    entries.value = first.data
    lastPage.value = first.last_page
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

async function more(): Promise<void> {
  if (api.value === null || loadingMore.value) return

  loadingMore.value = true

  try {
    const next = await api.value.list(page.value + 1)
    entries.value = [...entries.value, ...next.data]
    page.value = next.current_page
    lastPage.value = next.last_page
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loadingMore.value = false
  }
}

/* The feed follows the record: an editor that swaps one record for the next in place would
   otherwise show the previous one's history under the new name. */
watch([() => props.type, recordId], () => void load(), { immediate: true })

defineExpose({ reload: load })

function who(entry: HistoryEntry): string {
  return entry.admin?.name ?? t('history.unknown-author')
}

/** "Changed · in the panel" — what happened and through which door, in one muted line. */
function what(entry: HistoryEntry): string {
  return `${t(`history.event-${entry.event}`)} · ${t(`history.source-${entry.source}`)}`
}

function from(change: HistoryChange): string {
  return historyValue(change.from, t('history.empty-value'))
}

function to(change: HistoryChange): string {
  return historyValue(change.to, t('history.empty-value'))
}

function long(change: HistoryChange): string {
  return t('history.long', {
    from: String(change.from_length ?? 0),
    to: String(change.to_length ?? 0),
  })
}

/** The run's own words beside the link, when it has some: "Part of a run · prices.csv". */
function runLabel(entry: HistoryEntry): string {
  const name = entry.run?.summary?.what

  return typeof name === 'string' && name !== ''
    ? `${t('history.run')} · ${name}`
    : t('history.run')
}

/* The run, in a dialog over the feed: what it was, and its rows with a search by record. */
const runOpen = ref(false)
const run = ref<HistoryRun | null>(null)
const runId = ref<number | null>(null)
const runSearch = ref('')
const runLoading = ref(false)
let runTicket = 0

async function loadRun(id: number, next = 1): Promise<void> {
  if (api.value === null) return

  runLoading.value = true
  const ticket = ++runTicket

  try {
    const search = /^\d+$/.test(runSearch.value.trim()) ? Number(runSearch.value.trim()) : null
    const answer = await api.value.run(id, next, search)

    // Typing "105" asks three times; only the last answer is the one the box is showing.
    if (ticket !== runTicket) return

    run.value =
      next === 1 || run.value === null
        ? answer
        : {
            run: answer.run,
            rows: { ...answer.rows, data: [...run.value.rows.data, ...answer.rows.data] },
          }
  } catch (error) {
    toast.danger(message(error))
  } finally {
    runLoading.value = false
  }
}

function openRun(id: number): void {
  runId.value = id
  run.value = null
  runSearch.value = ''
  runOpen.value = true
  void loadRun(id)
}

/* A search is typed, not submitted: the rows follow what is in the box. */
watch(runSearch, () => {
  if (runId.value !== null && runOpen.value) void loadRun(runId.value)
})

/** What a run was, as lines: the module's own keys, except the two the panel says itself. */
const summaryLines = computed(() => {
  const summary = run.value?.run.summary ?? {}

  return Object.entries(summary)
    .filter(([key]) => key !== 'rows' && key !== 'failed')
    .map(([key, value]) => ({ key, value: historyValue(value, t('history.empty-value')) }))
})

const runFailed = computed(() => {
  const failed = run.value?.run.summary?.failed

  return typeof failed === 'string' ? failed : null
})
</script>

<template>
  <section class="wx-history">
    <wx-text v-if="heading" weight="medium" class="wx-history__title">{{ heading }}</wx-text>

    <wx-empty v-if="recordId === null" size="sm" :description="t('history.unsaved')" />

    <wx-skeleton v-else-if="loading" :rows="3" />

    <wx-empty v-else-if="entries.length === 0" size="sm" :description="t('history.empty')" />

    <ul v-else class="wx-history__list">
      <li v-for="entry in entries" :key="entry.id" class="wx-history-entry">
        <wx-avatar :name="who(entry)" size="sm" tone="auto" />

        <div class="wx-history-entry__body">
          <div class="wx-history-entry__head">
            <wx-text size="sm" weight="medium" truncate>{{ who(entry) }}</wx-text>
            <date-text :value="entry.created_at" />
          </div>

          <wx-text size="sm" tone="muted">{{ what(entry) }}</wx-text>

          <ul v-if="entry.changes.length > 0" class="wx-history-entry__changes">
            <li v-for="change in entry.changes" :key="change.field" class="wx-history-change">
              <span class="wx-history-change__label">{{ change.label }}:</span>
              <template v-if="change.long">
                <span class="wx-history-change__long">{{ long(change) }}</span>
              </template>
              <template v-else>
                <span class="wx-history-change__from">{{ from(change) }}</span>
                <span class="wx-history-change__arrow" aria-hidden="true">→</span>
                <span class="wx-history-change__to">{{ to(change) }}</span>
              </template>
            </li>
          </ul>

          <!-- A link in looks and a button in the form around the screen: `type="button"`, or
               the editor's form would be submitted by it. -->
          <button
            v-if="entry.run"
            type="button"
            class="wx-history-entry__run"
            @click="openRun(entry.run.id)"
          >
            {{ runLabel(entry) }}
          </button>
        </div>
      </li>
    </ul>

    <div v-if="page < lastPage" class="wx-history__more">
      <wx-button size="sm" :loading="loadingMore" @click="more">{{ t('history.more') }}</wx-button>
    </div>

    <wx-dialog v-model:open="runOpen" :title="t('history.run-title')" :width="640">
      <wx-skeleton v-if="run === null" :rows="4" />

      <div v-else class="wx-history-run">
        <div class="wx-history-run__head">
          <wx-text size="sm" weight="medium">{{ who(run.run) }}</wx-text>
          <date-text :value="run.run.created_at" />
        </div>
        <wx-text size="sm" tone="muted">{{ what(run.run) }}</wx-text>

        <dl v-if="summaryLines.length > 0" class="wx-history-run__summary">
          <template v-for="line in summaryLines" :key="line.key">
            <dt>{{ line.key }}</dt>
            <dd>{{ line.value }}</dd>
          </template>
        </dl>

        <wx-text size="sm">{{
          t('history.run-rows', { count: String(run.run.rows ?? 0) })
        }}</wx-text>
        <wx-text v-if="runFailed" size="sm" tone="danger">
          {{ t('history.run-failed', { message: runFailed }) }}
        </wx-text>

        <wx-input
          v-model="runSearch"
          size="sm"
          inputmode="numeric"
          :placeholder="t('history.search')"
          clearable
        />

        <ul class="wx-history__list">
          <li v-for="row in run.rows.data" :key="row.id" class="wx-history-run__row">
            <wx-text size="sm" weight="medium">#{{ row.subject.id }}</wx-text>
            <ul class="wx-history-entry__changes">
              <li v-for="change in row.changes" :key="change.field" class="wx-history-change">
                <span class="wx-history-change__label">{{ change.label }}:</span>
                <template v-if="change.long">
                  <span class="wx-history-change__long">{{ long(change) }}</span>
                </template>
                <template v-else>
                  <span class="wx-history-change__from">{{ from(change) }}</span>
                  <span class="wx-history-change__arrow" aria-hidden="true">→</span>
                  <span class="wx-history-change__to">{{ to(change) }}</span>
                </template>
              </li>
            </ul>
          </li>
        </ul>

        <div v-if="run.rows.current_page < run.rows.last_page" class="wx-history__more">
          <wx-button
            size="sm"
            :loading="runLoading"
            @click="runId !== null && loadRun(runId, run.rows.current_page + 1)"
          >
            {{ t('history.more') }}
          </wx-button>
        </div>
      </div>
    </wx-dialog>
  </section>
</template>

<style scoped>
.wx-history {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
  /* Laid out by its own width: it hangs in a tab or a card, whose width the window does not know. */
  container-type: inline-size;
}

.wx-history__list {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-history-entry {
  display: flex;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-history-entry__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  flex: 1 1 auto;
  min-width: 0;
}

/* The date keeps the end of the line, so entries by long and short names line up. */
.wx-history-entry__head,
.wx-history-run__head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-history-entry__head > :last-child,
.wx-history-run__head > :last-child {
  margin-inline-start: auto;
  flex: none;
}

.wx-history-entry__changes {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-history-change {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-default);
  overflow-wrap: anywhere;
}

.wx-history-change__label {
  color: var(--wx-text-muted);
  margin-inline-end: var(--wx-space-4);
}

/* Was and is are told apart by more than colour: the old value is struck through. */
.wx-history-change__from {
  color: var(--wx-text-muted);
  text-decoration: line-through;
}

.wx-history-change__arrow {
  margin-inline: var(--wx-space-4);
  color: var(--wx-text-muted);
}

.wx-history-change__long {
  color: var(--wx-text-muted);
  font-style: italic;
}

/* Unpadded, so its words start where the lines above them do. */
.wx-history-entry__run {
  align-self: flex-start;
  margin: 0;
  padding: 0;
  border: 0;
  border-radius: var(--wx-radius-xs);
  background: none;
  font: inherit;
  font-size: var(--wx-font-size-sm);
  text-align: start;
  color: var(--wx-text-link);
  cursor: pointer;
}

.wx-history-entry__run:hover {
  text-decoration: underline;
}

.wx-history-entry__run:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-history__more {
  display: flex;
  justify-content: center;
}

.wx-history-run {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-history-run__summary {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: var(--wx-space-4) var(--wx-space-12);
  margin: 0;
  font-size: var(--wx-font-size-sm);
}

.wx-history-run__summary dt {
  color: var(--wx-text-muted);
}

.wx-history-run__summary dd {
  margin: 0;
  overflow-wrap: anywhere;
}

.wx-history-run__row {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
}
</style>
