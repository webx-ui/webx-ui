<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxDate } from '@webx-ui/module-admin'
import { confirm, toast, WxBadge, WxButton, WxEmpty, WxSkeleton, WxText } from '@webx-ui/core'
import { createPagesApi } from './api'
import { usePageEditor } from './editor'
import { usePagesMessages } from './i18n'
import type { PageVersion } from './types'

/**
 * What was published, when, by whom and from where.
 *
 * Publications only. The autosaves a save writes are insurance rather than history — a ring of
 * the last few copies of the draft, replaced every couple of minutes — and a list with them in
 * it would be a list nobody can read.
 *
 * Restoring makes the old version the draft. Publishing it is the same separate step it always
 * is, which is what keeps the history a line: nothing here changes the site by itself.
 */
defineOptions({ name: 'WxPageHistory' })

const context = useAdmin()
const api = createPagesApi(context)
const editor = usePageEditor()
usePagesMessages()

const t = useTranslate('webx-pages')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const versions = ref<PageVersion[] | null>(null)
const working = ref(false)

const page = computed(() => editor?.page.value ?? null)
const canManage = computed(() => context.can('pages.manage'))

/**
 * Which line is the site: the newest publication, and only while the page is on the site at
 * all. A page taken down still has its history, and none of it is live.
 */
const live = computed(() =>
  page.value?.published_at ? (versions.value?.[0]?.number ?? null) : null,
)

async function load(): Promise<void> {
  const current = page.value

  if (!current) return

  try {
    versions.value = await api.versions(current.id)
  } catch (error) {
    versions.value = []
    toast.danger(message(error))
  }
}

async function restore(version: PageVersion): Promise<void> {
  const current = page.value

  if (!current) return

  const agreed = await confirm({
    title: t('page.restore-title', { number: version.number }),
    message: t('page.restore-text'),
    confirmText: t('page.restore'),
    cancelText: t('page.cancel'),
  })

  if (!agreed) return

  working.value = true

  try {
    await api.restoreVersion(current.id, version.number)
    // Through the editor rather than with the answer: the values it holds are what the form is
    // bound to, and a version restored underneath it has to reach the fields.
    await editor?.reload()
    toast.success(t('page.restored-version', { number: version.number }))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

// The page is reloaded after every publication and every restore, so its stamp is the signal
// that there is something new to list.
watch(
  () => [page.value?.id, page.value?.published_at, page.value?.updated_at],
  () => void load(),
  {
    immediate: true,
  },
)
</script>

<template>
  <div class="wx-page-history">
    <!-- Where a row would stand, not against the border: a skeleton flush with a rounded
         corner has its ends clipped by it, which reads as a drawing fault rather than as
         something loading. -->
    <wx-skeleton v-if="versions === null" class="wx-page-history__ghost" :rows="3" />
    <wx-empty v-else-if="versions.length === 0" :description="t('page.history-empty')" />
    <div
      v-for="version in versions"
      v-else
      :key="version.number"
      class="wx-page-history__row"
      :class="{ 'is-live': version.number === live }"
    >
      <code class="wx-page-history__number">{{
        t('page.version', { number: version.number })
      }}</code>
      <div class="wx-page-history__who">
        <wx-date v-if="version.created_at" :value="version.created_at" size="md" tone="default" />
        <wx-text size="sm" tone="muted">
          {{ version.author ?? t(`page.source-${version.source}`) }}
          <template v-if="version.comment"> · {{ version.comment }}</template>
        </wx-text>
      </div>
      <wx-badge v-if="version.number === live" type="success" dot>{{
        t('page.version-live')
      }}</wx-badge>
      <wx-button
        v-if="canManage && version.number !== live"
        size="sm"
        variant="outline"
        :loading="working"
        @click="restore(version)"
      >
        {{ t('page.restore') }}
      </wx-button>
    </div>
  </div>
</template>

<style scoped>
.wx-page-history {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  /* Nothing reaches past the rounding: a row, or the skeleton standing in for three of them,
     is a square box and its corners showed through the panel's. */
  overflow: hidden;
}

.wx-page-history__ghost {
  padding: var(--wx-space-10) var(--wx-space-12);
}

.wx-page-history__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  padding: var(--wx-space-10) var(--wx-space-12);
  border-block-end: 1px solid var(--wx-border-default);
  flex-wrap: wrap;
}

.wx-page-history__row:last-child {
  border-block-end: 0;
}

.wx-page-history__number {
  width: 40px;
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-page-history__who {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 160px;
}
</style>
