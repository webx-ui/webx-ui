<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxDate, WxDrafts } from '@webx-ui/module-admin'
import { confirm, toast, WxBadge, WxButton, WxEmpty, WxSkeleton, WxText } from '@webx-ui/core'
import { createRegionsApi } from './api'
import { useBlocksMessages } from './i18n'
import { useRegionEditor } from './region'
import type { RegionVersion } from './types'

/**
 * What was published in the region, when and by whom — the `wx-region-history` node of
 * `regions.form`.
 *
 * Publications first, and the copies of the draft — autosaves, a draft somebody else's save wrote
 * over — in a list of their own under them, as with pages. Restoring makes an old version the
 * draft, and publishing it is the usual separate step — so nothing here changes the site by
 * itself, and a header cannot be swapped by one slip in a list.
 */
defineOptions({ name: 'WxRegionHistory' })

const context = useAdmin()
const api = createRegionsApi(context)
const editor = useRegionEditor()
useBlocksMessages()

const t = useTranslate('webx-blocks')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const versions = ref<RegionVersion[] | null>(null)
const working = ref(false)

const region = computed(() => editor?.region.value ?? null)
const canManage = computed(() => editor?.canManage ?? false)

/** The newest publication is the site — while the region is on it at all. */
const live = computed(() =>
  region.value?.published ? (versions.value?.[0]?.number ?? null) : null,
)

async function load(): Promise<void> {
  const current = region.value

  // Never saved: no row, so no history to ask for.
  if (!current || current.id === null) {
    versions.value = current ? [] : null

    return
  }

  try {
    versions.value = await api.versions(current.name)
  } catch (error) {
    versions.value = []
    toast.danger(message(error))
  }
}

async function restore(version: RegionVersion): Promise<void> {
  const current = region.value

  if (!current) return

  const agreed = await confirm({
    title: t('region.restore-title', { number: version.number }),
    message: t('region.restore-text'),
    confirmText: t('region.restore'),
    cancelText: t('region.cancel'),
  })

  if (!agreed) return

  working.value = true

  try {
    await api.restoreVersion(current.name, version.number)
    // Through the editor rather than with the answer: its values are what the constructor is
    // bound to, and a version restored underneath it has to reach the tree.
    await editor?.reload()
    toast.success(t('region.restored-version', { number: version.number }))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

// The region is read again after every publication and restore; its stamps say when to list.
watch(
  () => [region.value?.name, region.value?.published_at, region.value?.updated_at],
  () => void load(),
  { immediate: true },
)
</script>

<template>
  <div class="wx-region-history-tab">
    <div class="wx-region-history">
      <wx-skeleton v-if="versions === null" class="wx-region-history__ghost" :rows="3" />
      <wx-empty v-else-if="versions.length === 0" :description="t('region.history-empty')" />
      <div
        v-for="version in versions"
        v-else
        :key="version.number"
        class="wx-region-history__row"
        :class="{ 'is-live': version.number === live }"
      >
        <code class="wx-region-history__number">{{
          t('region.version', { number: version.number })
        }}</code>
        <div class="wx-region-history__who">
          <wx-date v-if="version.created_at" :value="version.created_at" size="md" tone="default" />
          <wx-text size="sm" tone="muted">
            {{ version.author?.name ?? t(`region.source-${version.source}`) }}
            <template v-if="version.comment"> · {{ version.comment }}</template>
          </wx-text>
        </div>
        <wx-badge v-if="version.number === live" type="success" dot>{{
          t('region.version-live')
        }}</wx-badge>
        <wx-button
          v-if="canManage && version.number !== live"
          size="sm"
          variant="outline"
          :loading="working"
          @click="restore(version)"
        >
          {{ t('region.restore') }}
        </wx-button>
      </div>
    </div>

    <wx-drafts
      :id="region?.name"
      entity="regions"
      screen="regions.form"
      :can-restore="canManage"
      :stamp="[region?.published_at, region?.updated_at]"
      @restored="editor?.reload()"
    />
  </div>
</template>

<style scoped>
.wx-region-history-tab {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-24);
}

.wx-region-history {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  /* Clip, not hidden: rounding the rows' corners needs no scroll container (§4). */
  overflow: clip;
}

.wx-region-history__ghost {
  padding: var(--wx-space-10) var(--wx-space-12);
}

.wx-region-history__row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--wx-space-12);
  padding: var(--wx-space-10) var(--wx-space-12);
  border-block-end: 1px solid var(--wx-border-default);
}

.wx-region-history__row:last-child {
  border-block-end: 0;
}

.wx-region-history__number {
  width: 40px;
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-region-history__who {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 160px;
}
</style>
