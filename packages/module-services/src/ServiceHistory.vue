<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxDate } from '@webx-ui/module-admin'
import { confirm, toast, WxBadge, WxButton, WxEmpty, WxSkeleton, WxText } from '@webx-ui/core'
import { createServicesApi } from './api'
import { useServiceEditor } from './editor'
import { useServicesMessages } from './i18n'
import type { ServiceVersion } from './types'

/**
 * What was published, when, by whom and from where — publications only; the autosaves are
 * insurance, not history.
 *
 * Restoring makes the old version the draft. Publishing it is the same separate step it always
 * is, so nothing here changes the site by itself.
 */
defineOptions({ name: 'WxServiceHistory' })

const context = useAdmin()
const api = createServicesApi(context)
const editor = useServiceEditor()
useServicesMessages()

const t = useTranslate('webx-services')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const versions = ref<ServiceVersion[] | null>(null)
const working = ref(false)

const service = computed(() => editor?.service.value ?? null)
const canManage = computed(() => editor?.canManage ?? false)

/** The newest publication is the site — while the service is on it at all. */
const live = computed(() =>
  service.value?.status === 'published' || service.value?.status === 'modified'
    ? (versions.value?.[0]?.number ?? null)
    : null,
)

async function load(): Promise<void> {
  const current = service.value

  if (!current) return

  try {
    versions.value = await api.versions(current.id)
  } catch (error) {
    versions.value = []
    toast.danger(message(error))
  }
}

async function restore(version: ServiceVersion): Promise<void> {
  const current = service.value

  if (!current) return

  const agreed = await confirm({
    title: t('service.restore-title', { number: version.number }),
    message: t('service.restore-text'),
    confirmText: t('service.restore-version'),
    cancelText: t('panel.cancel'),
  })

  if (!agreed) return

  working.value = true

  try {
    await api.restoreVersion(current.id, version.number)
    // Through the editor rather than with the answer: the values it holds are what the form is
    // bound to, and a version restored underneath it has to reach the fields.
    await editor?.reload()
    toast.success(t('service.restored-version', { number: version.number }))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

// The service is read again after every publication and every restore, so its stamp is the
// signal that there is something new to list.
watch(
  () => [service.value?.id, service.value?.published_at, service.value?.updated_at],
  () => void load(),
  { immediate: true },
)
</script>

<template>
  <div class="wx-service-history">
    <wx-skeleton v-if="versions === null" class="wx-service-history__ghost" :rows="3" />
    <wx-empty v-else-if="versions.length === 0" :description="t('service.history-empty')" />
    <div
      v-for="version in versions"
      v-else
      :key="version.number"
      class="wx-service-history__row"
      :class="{ 'is-live': version.number === live }"
    >
      <code class="wx-service-history__number">{{
        t('service.version', { number: version.number })
      }}</code>
      <div class="wx-service-history__who">
        <wx-date v-if="version.created_at" :value="version.created_at" size="md" tone="default" />
        <wx-text size="sm" tone="muted">
          {{ version.author ?? t(`service.source-${version.source}`) }}
          <template v-if="version.comment"> · {{ version.comment }}</template>
        </wx-text>
      </div>
      <wx-badge v-if="version.number === live" type="success" dot>{{
        t('service.version-live')
      }}</wx-badge>
      <wx-button
        v-if="canManage && version.number !== live"
        size="sm"
        variant="outline"
        :loading="working"
        @click="restore(version)"
      >
        {{ t('service.restore-version') }}
      </wx-button>
    </div>
  </div>
</template>

<style scoped>
.wx-service-history {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  overflow: hidden;
}

.wx-service-history__ghost {
  padding: var(--wx-space-10) var(--wx-space-12);
}

.wx-service-history__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  padding: var(--wx-space-10) var(--wx-space-12);
  border-block-end: 1px solid var(--wx-border-default);
  flex-wrap: wrap;
}

.wx-service-history__row:last-child {
  border-block-end: 0;
}

.wx-service-history__number {
  width: 40px;
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-service-history__who {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 160px;
}
</style>
