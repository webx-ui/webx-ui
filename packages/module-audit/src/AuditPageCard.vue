<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  WxAlert,
  WxBadge,
  WxButton,
  WxDrawer,
  WxLink,
  WxSkeleton,
  WxTable,
  WxTabs,
  WxText,
  type BadgeType,
  type TabItem,
  type TabValue,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import AuditDetails from './AuditDetails.vue'
import { createAuditApi } from './api'
import { useAuditMessages } from './i18n'
import type {
  AuditLinkRow,
  AuditPage,
  AuditPageCard,
  AuditResourceRow,
  AuditResourceTab,
  AuditRun,
  AuditSeverity,
} from './types'

/**
 * One page of the snapshot, sliding in from the right (§8): what it answered and what its head
 * says, its findings, the links that lead in and out of it — with the answer of each own page
 * and the class of each host — what it loads (pictures, CSS, JS) with what each answered, and
 * its structured data with what the types lack.
 *
 * «Recheck» asks this one address again (a run of the `urls` scope) and says what of its findings
 * in the last full run are fixed; «Export» downloads the page — snapshot, findings and links.
 */
const props = defineProps<{
  run: number
  /** Where the section lives — the recheck's comparison opens on its runs. */
  base?: string
  /** The page to show; null keeps the card closed. */
  pageId: number | null
  /** Check ids and their titles in the reader's language, for the findings tab. */
  titles: Record<string, string>
}>()

const emit = defineEmits<{ close: [] }>()

/*
 * The card is wide: a page has long addresses, headers and hreflang lists. Its width is kept under
 * a key of its own, so one remembered from the narrow card of before does not keep it narrow.
 */

const context = useAdmin()
const api = createAuditApi(context)
const router = useRouter()
useAuditMessages()

const canRun = context.can('audit.run') || context.can('audit.manage')
const recheck = ref<AuditRun | null>(null)
const rechecking = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

const POLL_MS = 2000

/* The page in its own words: the run it was crawled in, then one more, of just this address. */
async function startRecheck(): Promise<void> {
  if (!card.value) return

  rechecking.value = true
  recheck.value = null

  try {
    follow(await api.start('urls', [card.value.page.url]))
  } catch (error) {
    rechecking.value = false
    toast.danger(message(error))
  }
}

function follow(run: AuditRun): void {
  clearTimeout(timer)

  if (run.status === 'queued' || run.status === 'running') {
    timer = setTimeout(async () => {
      try {
        follow(await api.run(run.id))
      } catch (error) {
        rechecking.value = false
        toast.danger(message(error))
      }
    }, POLL_MS)

    return
  }

  rechecking.value = false
  recheck.value = run
}

function compareRecheck(): void {
  if (recheck.value && props.base) {
    void router.push({ path: `${props.base}/runs`, query: { to: String(recheck.value.id) } })
  }
}

onBeforeUnmount(() => clearTimeout(timer))

const t = useTranslate('webx-audit')
const message = useErrorText()

const card = ref<AuditPageCard | null>(null)
const failure = ref<string | null>(null)
const tab = ref<TabValue>('overview')
const links = ref<AuditPage<AuditLinkRow> | null>(null)
const linksLoading = ref(false)
const resources = ref<AuditPage<AuditResourceRow> | null>(null)
const resourcesLoading = ref(false)

const resourceTabs: AuditResourceTab[] = ['images', 'css', 'js']

const open = computed({
  get: () => props.pageId !== null,
  set: (value) => {
    if (!value) emit('close')
  },
})

const tabs = computed<TabItem[]>(() => [
  { value: 'overview', label: t('page.card-overview') },
  { value: 'issues', label: t('page.card-issues'), badge: card.value?.counts.issues || undefined },
  { value: 'in', label: t('page.card-incoming'), badge: card.value?.counts.incoming || undefined },
  { value: 'out', label: t('page.card-outgoing'), badge: card.value?.counts.outgoing || undefined },
  { value: 'images', label: t('page.card-images'), badge: card.value?.counts.images || undefined },
  { value: 'css', label: t('page.card-css'), badge: card.value?.counts.css || undefined },
  { value: 'js', label: t('page.card-js'), badge: card.value?.counts.js || undefined },
  {
    value: 'microdata',
    label: t('page.card-microdata'),
    badge: card.value?.counts.microdata || undefined,
  },
])

const resourceTab = computed<AuditResourceTab | null>(() =>
  resourceTabs.includes(tab.value as AuditResourceTab) ? (tab.value as AuditResourceTab) : null,
)

const severities: Record<AuditSeverity, BadgeType> = {
  error: 'danger',
  warning: 'warning',
  notice: 'info',
}

const hostTypes: Record<string, BadgeType> = {
  own: 'success',
  own_mirror: 'warning',
  dev: 'danger',
  external: 'info',
}

const linkColumns = computed<TableColumn<AuditLinkRow>[]>(() => [
  { key: 'url', label: t('page.address'), minWidth: 220 },
  { key: 'status', label: t('page.field-status'), width: 80 },
  { key: 'kind', label: t('page.kind'), width: 90 },
  { key: 'anchor', label: t('page.anchor'), minWidth: 140 },
  { key: 'host_class', label: t('page.host'), width: 120 },
])

/** Pictures show their alt; stylesheets and scripts how they are cached and compressed. */
const resourceColumns = computed<TableColumn<AuditResourceRow>[]>(() => [
  { key: 'url', label: t('page.address'), minWidth: 220 },
  { key: 'status', label: t('page.field-status'), width: 80 },
  { key: 'bytes', label: t('page.size'), width: 110 },
  ...(resourceTab.value === 'images'
    ? [{ key: 'alt', label: t('page.alt'), minWidth: 120 }]
    : [
        { key: 'cache_control', label: t('page.cache'), minWidth: 120 },
        { key: 'compression', label: t('page.field-compression'), width: 100 },
      ]),
])

function size(row: AuditResourceRow): string {
  if (row.bytes === null) return ''

  const kb = row.bytes / 1024
  const text = kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.round(kb)} KB`

  return row.width && row.height ? `${text} · ${row.width}×${row.height}` : text
}

/** The answer, the markup and where the page came from — label and value, in reading order. */
const facts = computed<[string, string][]>(() => {
  const page = card.value?.page

  if (!page) return []

  const rows: [string, unknown][] = [
    [t('page.field-status'), page.status ?? t('page.status-none')],
    [t('page.field-final_status'), page.redirect_to ? page.final_status : null],
    [t('page.field-redirect_to'), page.redirect_to],
    [t('page.error'), page.error],
    [t('page.field-content_type'), page.content_type],
    [t('page.field-bytes'), page.bytes],
    [t('page.field-ttfb_ms'), page.ttfb_ms],
    [t('page.field-total_ms'), page.total_ms],
    [t('page.field-compression'), page.compression],
    [t('page.field-source'), t(`page.source-${page.source}`)],
    [t('page.field-depth'), page.depth],
    [t('page.field-indexable'), page.indexable ? t('page.yes') : t('page.no')],
    [t('page.field-blocked_by_robots'), page.blocked_by_robots ? t('page.yes') : null],
    [t('page.field-title'), page.title],
    [t('page.field-description'), page.description],
    [t('page.field-h1'), page.h1.join(' · ') || null],
    [t('page.field-canonical'), page.canonical],
    [t('page.field-robots_meta'), page.robots_meta],
    [t('page.field-x_robots_tag'), page.x_robots_tag],
    [t('page.field-lang'), page.lang],
    [
      'hreflang',
      page.hreflang.map((alternate) => `${alternate.lang} ${alternate.url}`).join('\n') || null,
    ],
    [
      'og',
      Object.entries(page.og)
        .map(([key, value]) => `og:${key} ${value}`)
        .join('\n') || null,
    ],
    [
      'JSON-LD',
      page.json_ld.map((block) => block.error ?? block.types.join(', ')).join('\n') || null,
    ],
    [t('page.field-word_count'), page.word_count],
    [t('page.field-links_in'), page.links_in],
    [t('page.field-links_out_internal'), page.links_out_internal],
    [t('page.field-links_out_external'), page.links_out_external],
    [t('page.field-images'), page.images],
    [t('page.field-images_without_alt'), page.images_without_alt || null],
  ]

  return rows
    .filter(([, value]) => value !== null && value !== undefined && value !== '')
    .map(([label, value]) => [label, String(value)])
})

function statusType(value: number | null): BadgeType {
  if (value === null) return 'danger'
  if (value >= 500) return 'danger'
  if (value >= 400) return 'warning'
  if (value >= 300) return 'info'

  return 'success'
}

async function load(id: number): Promise<void> {
  clearTimeout(timer)
  rechecking.value = false
  recheck.value = null
  card.value = null
  failure.value = null
  links.value = null
  resources.value = null
  tab.value = 'overview'

  try {
    card.value = await api.page(props.run, id)
  } catch (error) {
    failure.value = message(error)
  }
}

async function loadLinks(state: TableState): Promise<void> {
  if (props.pageId === null || (tab.value !== 'in' && tab.value !== 'out')) return

  linksLoading.value = true

  try {
    links.value = await api.links(props.run, props.pageId, {
      direction: tab.value,
      page: state.page,
      per_page: state.perPage,
    })
  } catch (error) {
    failure.value = message(error)
  } finally {
    linksLoading.value = false
  }
}

async function loadResources(state: TableState): Promise<void> {
  const current = resourceTab.value

  if (props.pageId === null || current === null) return

  resourcesLoading.value = true

  try {
    resources.value = await api.resources(props.run, props.pageId, {
      tab: current,
      page: state.page,
      per_page: state.perPage,
    })
  } catch (error) {
    failure.value = message(error)
  } finally {
    resourcesLoading.value = false
  }
}

watch(
  () => props.pageId,
  (id) => {
    if (id !== null) void load(id)
  },
  { immediate: true },
)

watch(tab, () => {
  links.value = null
  resources.value = null
})
</script>

<template>
  <wx-drawer
    v-model:open="open"
    size="min(1100px, 75vw)"
    :min-size="480"
    resizable
    persist="webx-audit.page-card-wide"
  >
    <template #title>
      <span class="wx-audit-card__title">
        <wx-badge v-if="card" :type="statusType(card.page.status)" size="sm">{{
          card.page.status ?? '—'
        }}</wx-badge>
        <span class="wx-audit-card__url">{{ card?.page.url ?? '' }}</span>
      </span>
    </template>

    <template #extra>
      <span v-if="card" class="wx-audit-card__extra">
        <wx-button
          v-if="canRun"
          size="sm"
          icon="refresh"
          :loading="rechecking"
          @click="startRecheck"
          >{{ t('page.recheck') }}</wx-button
        >
        <wx-button
          size="sm"
          variant="text"
          icon="download"
          :href="api.pageFile(props.run, card.page.id)"
          >{{ t('page.export-page') }}</wx-button
        >
        <wx-link :href="card.page.url" target="_blank">{{ t('page.open-page') }}</wx-link>
      </span>
    </template>

    <wx-text v-if="failure" tone="danger">{{ failure }}</wx-text>
    <wx-skeleton v-else-if="!card" :rows="6" />

    <div v-else class="wx-audit-card">
      <wx-alert v-if="rechecking" type="info" :description="t('page.recheck-running')" />
      <wx-alert
        v-else-if="recheck && recheck.status === 'done' && recheck.counts"
        :type="recheck.counts.new > 0 ? 'warning' : 'success'"
      >
        <div class="wx-audit-card__recheck">
          <span>{{ t('page.recheck-fixed', { count: recheck.counts.fixed }) }}</span>
          <span>{{ t('page.recheck-new', { count: recheck.counts.new }) }}</span>
          <wx-button v-if="props.base" size="sm" variant="text" @click="compareRecheck">{{
            t('page.recheck-compare')
          }}</wx-button>
        </div>
      </wx-alert>
      <wx-alert
        v-else-if="recheck"
        type="danger"
        :description="recheck.error ?? t(`page.status-${recheck.status}`)"
      />
      <wx-text v-if="card.page.title" weight="semibold">{{ card.page.title }}</wx-text>

      <wx-tabs v-model="tab" :items="tabs">
        <dl v-if="tab === 'overview'" class="wx-audit-card__facts">
          <template v-for="[label, value] in facts" :key="label">
            <dt>{{ label }}</dt>
            <dd>{{ value }}</dd>
          </template>
          <template v-if="Object.keys(card.page.headers).length">
            <dt class="wx-audit-card__section">{{ t('page.headers') }}</dt>
            <dd />
            <template v-for="(value, name) in card.page.headers" :key="name">
              <dt class="wx-audit-card__code">{{ name }}</dt>
              <dd class="wx-audit-card__code">{{ value }}</dd>
            </template>
          </template>
        </dl>

        <div v-else-if="tab === 'issues'" class="wx-audit-card__issues">
          <wx-text v-if="!card.issues.length" tone="muted">{{ t('page.no-issues') }}</wx-text>
          <div v-for="issue in card.issues" :key="issue.id" class="wx-audit-card__issue">
            <div class="wx-audit-card__issue-head">
              <wx-badge :type="severities[issue.severity]" dot>{{
                t(`page.severity-${issue.severity}`)
              }}</wx-badge>
              <wx-text size="sm" weight="semibold">{{
                props.titles[issue.check] ?? issue.check
              }}</wx-text>
              <wx-badge v-if="issue.state === 'new'" type="primary" size="sm">{{
                t('page.state-new')
              }}</wx-badge>
            </div>
            <audit-details :details="issue.details" />
          </div>
        </div>

        <wx-table
          v-else-if="resourceTab"
          :key="`resources-${String(tab)}`"
          :data="resources"
          :columns="resourceColumns"
          row-key="id"
          flush
          :loading="resourcesLoading"
          :per-page-options="[]"
          :empty-text="t('page.no-resources')"
          @state-change="loadResources"
        >
          <template #cell-url="{ row }">
            <span class="wx-audit-card__url">{{ row.url }}</span>
            <wx-text v-if="row.location" size="sm" tone="muted" class="wx-audit-card__url">
              → {{ row.location }}</wx-text
            >
            <wx-text v-if="row.error" size="sm" tone="danger">{{ row.error }}</wx-text>
          </template>
          <template #cell-status="{ row }">
            <wx-badge v-if="row.status !== null" :type="statusType(row.status)" size="sm">{{
              row.status
            }}</wx-badge>
            <wx-badge v-else-if="row.checked" type="danger" size="sm">—</wx-badge>
            <wx-text v-else size="sm" tone="muted">{{ t('page.not-checked') }}</wx-text>
          </template>
          <template #cell-bytes="{ row }">{{ size(row) }}</template>
          <template #cell-alt="{ row }">
            <wx-text v-if="row.alt === null && row.kind === 'img'" size="sm" tone="danger">{{
              t('page.missing')
            }}</wx-text>
            <template v-else>{{ row.alt ?? '' }}</template>
          </template>
        </wx-table>

        <div v-else-if="tab === 'microdata'" class="wx-audit-card__issues">
          <wx-text v-if="!card.page.json_ld.length" tone="muted">{{
            t('page.no-json-ld')
          }}</wx-text>
          <div
            v-for="(block, index) in card.page.json_ld"
            :key="index"
            class="wx-audit-card__issue"
          >
            <div class="wx-audit-card__issue-head">
              <wx-text size="sm" weight="semibold">{{
                t('page.jsonld-block', { number: index + 1 })
              }}</wx-text>
              <wx-badge v-for="type in block.types" :key="type" size="sm">{{ type }}</wx-badge>
            </div>
            <wx-text v-if="block.error" size="sm" tone="danger"
              >{{ t('page.jsonld-error') }} {{ block.error }}</wx-text
            >
            <template v-for="(item, position) in block.items ?? []" :key="position">
              <wx-text v-if="item.missing.length" size="sm" tone="danger"
                >{{ item.type }} — {{ t('page.jsonld-required') }}
                {{ item.missing.join(', ') }}</wx-text
              >
              <wx-text v-if="item.recommended.length" size="sm" tone="muted"
                >{{ item.type }} — {{ t('page.jsonld-recommended') }}
                {{ item.recommended.join(', ') }}</wx-text
              >
              <wx-text
                v-if="!item.missing.length && !item.recommended.length"
                size="sm"
                tone="success"
                >{{ item.type }} — {{ t('page.jsonld-complete') }}</wx-text
              >
            </template>
            <pre v-if="block.source" class="wx-audit-card__source">{{ block.source }}</pre>
          </div>
        </div>

        <wx-table
          v-else
          :key="String(tab)"
          :data="links"
          :columns="linkColumns"
          row-key="id"
          flush
          :loading="linksLoading"
          :per-page-options="[]"
          :empty-text="t('page.no-links')"
          @state-change="loadLinks"
        >
          <template #cell-url="{ row }">
            <span class="wx-audit-card__url">{{ row.url ?? '—' }}</span>
          </template>
          <template #cell-status="{ row }">
            <wx-badge v-if="row.status !== null" :type="statusType(row.status)" size="sm">{{
              row.status
            }}</wx-badge>
          </template>
          <template #cell-host_class="{ row }">
            <wx-badge v-if="row.host_class" :type="hostTypes[row.host_class] ?? 'info'" size="sm">{{
              t(`page.host-${row.host_class}`)
            }}</wx-badge>
          </template>
        </wx-table>
      </wx-tabs>
    </div>
  </wx-drawer>
</template>

<style scoped>
.wx-audit-card {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-audit-card__title {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-audit-card__url {
  min-width: 0;
  overflow-wrap: anywhere;
}

.wx-audit-card__facts {
  display: grid;
  grid-template-columns: minmax(120px, max-content) minmax(0, 1fr);
  gap: var(--wx-space-6) var(--wx-space-16);
  margin: var(--wx-space-12) 0 0;
  font-size: var(--wx-font-size-sm);
}

.wx-audit-card__facts dt {
  color: var(--wx-text-muted);
}

.wx-audit-card__facts dd {
  margin: 0;
  overflow-wrap: anywhere;
  white-space: pre-line;
}

.wx-audit-card__section {
  margin-top: var(--wx-space-12);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-audit-card__code {
  font-family: var(--wx-font-family-mono);
}

.wx-audit-card__issues {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  margin-top: var(--wx-space-12);
}

.wx-audit-card__issue {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-audit-card__source {
  max-height: 240px;
  margin: 0;
  padding: var(--wx-space-8);
  overflow: auto;
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-muted);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.wx-audit-card__issue-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-audit-card__extra {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-audit-card__recheck {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-12);
}
</style>
