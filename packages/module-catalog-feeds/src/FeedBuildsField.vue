<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { toast, WxAlert, WxBadge, WxButton, WxLink, WxPagination, WxText } from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate, WxDate } from '@webx-ui/module-admin'
import { useFeedEditor } from './editor'
import { NAMESPACE, useCatalogFeedsMessages } from './i18n'
import type { BuildStatus, FeedBuildDetail, FeedBuildRow, FeedBuildsPage } from './types'

/**
 * `wx-catalog-feed-builds`: the journal of a feed (§7.6 of the feeds spec). A row opens into why
 * products stayed out, what was warned about and the first products left out, each a link to its
 * form. Under it the reissues of the key, which change what a marketplace has to be told.
 */
defineOptions({ name: 'WxCatalogFeedBuilds' })

const POLL = 4000

const admin = useAdmin()
const editor = useFeedEditor()
useCatalogFeedsMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const feedId = computed(() => editor.feed.value?.id ?? null)
const canManage = computed(() => admin.can('catalog.manage'))

const page = ref<FeedBuildsPage | null>(null)
const pageNumber = ref(1)
const opened = ref<number | null>(null)
const detail = ref<FeedBuildDetail | null>(null)
const starting = ref(false)

let asked = 0
let timer: ReturnType<typeof setTimeout> | undefined

const going = (row: FeedBuildRow) => row.status === 'queued' || row.status === 'running'

async function load(): Promise<void> {
  const id = feedId.value

  if (id === null) return

  const ticket = ++asked

  clearTimeout(timer)

  try {
    const answer = await editor.api.builds(id, pageNumber.value)

    if (ticket !== asked) return

    page.value = answer

    if (answer.data.some(going)) timer = setTimeout(() => void load(), POLL)
  } catch (error) {
    if (ticket === asked) toast.danger(message(error))
  }
}

onMounted(() => void load())
watch([feedId, pageNumber], () => void load())

onBeforeUnmount(() => {
  asked++
  clearTimeout(timer)
})

async function toggle(row: FeedBuildRow): Promise<void> {
  const id = feedId.value

  if (id === null) return

  if (opened.value === row.id) {
    opened.value = null

    return
  }

  opened.value = row.id
  detail.value = null

  try {
    detail.value = await editor.api.build(id, row.id)
  } catch (error) {
    toast.danger(message(error))
  }
}

async function rebuild(): Promise<void> {
  const id = feedId.value

  if (id === null) return

  starting.value = true

  try {
    await editor.api.rebuild(id)
    toast.success(t('panel.rebuild-queued'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    starting.value = false
  }
}

const TONES: Record<BuildStatus, 'default' | 'info' | 'success' | 'danger'> = {
  queued: 'default',
  running: 'info',
  done: 'success',
  failed: 'danger',
}

const counters = (record: Record<string, number>) =>
  Object.entries(record).filter(([, count]) => count > 0)

function size(bytes: number | null): string {
  if (bytes === null) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`

  return `${(bytes / 1024 ** 2).toFixed(1)} MB`
}

const productLink = (id: number) => `${editor.base}/products/${id}`
</script>

<template>
  <div class="wx-catalog-feed-builds">
    <wx-text v-if="feedId === null" size="sm" tone="muted">{{ t('panel.after-save') }}</wx-text>

    <template v-else>
      <div v-if="canManage" class="wx-catalog-feed-builds__head">
        <wx-button
          size="sm"
          variant="outline"
          icon="refresh"
          :loading="starting"
          :disabled="!editor.feed.value?.is_active"
          @click="rebuild"
        >
          {{ t('panel.rebuild') }}
        </wx-button>
      </div>

      <wx-text v-if="page && page.data.length === 0" size="sm" tone="muted">
        {{ t('panel.builds-empty') }}
      </wx-text>

      <div v-for="row in page?.data ?? []" :key="row.id" class="wx-catalog-feed-builds__build">
        <button
          type="button"
          class="wx-catalog-feed-builds__row"
          :aria-expanded="opened === row.id"
          @click="toggle(row)"
        >
          <wx-badge :type="TONES[row.status]" dot size="sm">
            {{ t(`panel.status-${row.status}`) }}
          </wx-badge>
          <wx-text size="sm">
            <wx-date
              v-if="row.finished_at ?? row.queued_at"
              :value="row.finished_at ?? row.queued_at"
            />
          </wx-text>
          <wx-text size="sm" tone="muted">{{ t(`panel.trigger-${row.trigger}`) }}</wx-text>
          <wx-text size="sm" tone="muted">
            {{ t('panel.builds-written', { count: String(row.written) }) }}
          </wx-text>
          <wx-text v-if="row.bytes !== null" size="sm" tone="muted">{{ size(row.bytes) }}</wx-text>
          <wx-badge v-if="row.current" size="sm">{{ t('panel.builds-current') }}</wx-badge>
        </button>

        <div v-if="opened === row.id" class="wx-catalog-feed-builds__detail">
          <wx-alert v-if="row.error" type="danger" variant="soft" :description="row.error" />

          <wx-text size="sm" tone="muted">
            {{ t('panel.builds-selected', { count: String(row.selected) }) }}
          </wx-text>
          <wx-text v-if="row.excluded > 0" size="sm" tone="muted">
            {{ t('panel.builds-excluded', { count: String(row.excluded) }) }}
          </wx-text>
          <wx-text v-if="row.filtered > 0" size="sm" tone="muted">
            {{ t('panel.builds-filtered', { count: String(row.filtered) }) }}
          </wx-text>

          <wx-text
            v-for="[reason, count] in counters(row.rejected)"
            :key="reason"
            size="sm"
            tone="muted"
          >
            {{
              t('panel.preview-rejected', {
                reason: t(`panel.reason-${reason}`),
                count: String(count),
              })
            }}
          </wx-text>

          <wx-text
            v-for="[warning, count] in counters(row.warnings)"
            :key="warning"
            size="sm"
            tone="warning"
          >
            {{
              t('panel.preview-rejected', {
                reason: t(`panel.warning-${warning}`),
                count: String(count),
              })
            }}
          </wx-text>

          <template v-if="detail?.id === row.id && detail.rejects.length > 0">
            <wx-text size="sm" weight="medium">{{ t('panel.builds-rejects') }}</wx-text>
            <div v-for="reject in detail.rejects" :key="reject.product_id">
              <wx-link :as="RouterLink" :to="productLink(reject.product_id)">{{
                reject.name
              }}</wx-link>
              <wx-text size="sm" tone="muted"> · {{ t(`panel.reason-${reject.reason}`) }}</wx-text>
            </div>
          </template>
        </div>
      </div>

      <wx-pagination
        v-if="page && page.last_page > 1"
        v-model:current-page="pageNumber"
        :total="page.total"
        :page-size="page.per_page"
      />

      <template v-if="page && page.reissues.length > 0">
        <wx-text size="sm" weight="medium">{{ t('panel.builds-reissues') }}</wx-text>
        <wx-text v-for="(reissue, index) in page.reissues" :key="index" size="sm" tone="muted">
          <wx-date v-if="reissue.at" :value="reissue.at" />
          <template v-if="reissue.by"> · {{ reissue.by }}</template>
        </wx-text>
      </template>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-feed-builds {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-feed-builds__head {
  display: flex;
  justify-content: flex-end;
}

.wx-catalog-feed-builds__build {
  border-top: 1px solid var(--wx-border-muted);
}

.wx-catalog-feed-builds__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-12);
  width: 100%;
  padding: var(--wx-space-8) 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  text-align: start;
  cursor: pointer;
}

.wx-catalog-feed-builds__detail {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  padding-block-end: var(--wx-space-12);
}
</style>
