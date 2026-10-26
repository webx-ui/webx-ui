<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxDate, WxDrafts } from '@webx-ui/module-admin'
import { confirm, toast, WxBadge, WxButton, WxEmpty, WxSkeleton, WxText } from '@webx-ui/core'
import { createConfiguratorsApi } from './api'
import { useConfiguratorEditor } from './editor'
import type { ConfiguratorVersion } from './types'

/**
 * What was published, when, by whom and from where — and the copies of the draft between
 * publications under them (§13.2). Putting a version back makes it the draft; publishing it is the
 * same separate step it always is, so nothing here changes the site by itself.
 */
defineOptions({ name: 'WxConfiguratorHistory' })

const context = useAdmin()
const api = createConfiguratorsApi(context)
const editor = useConfiguratorEditor()
const t = useTranslate('webx-configurator')
const message = useErrorText()

const versions = ref<ConfiguratorVersion[] | null>(null)
const working = ref(false)

const row = computed(() => editor.detail.value?.configurator ?? null)

async function load(): Promise<void> {
  if (!row.value) return

  try {
    versions.value = await api.versions(row.value.id)
  } catch (error) {
    versions.value = []
    toast.danger(message(error))
  }
}

async function restore(version: ConfiguratorVersion): Promise<void> {
  if (!row.value) return

  const agreed = await confirm({
    title: t('configurator.history-restore-title', { number: version.number }),
    message: t('configurator.history-restore-text'),
    confirmText: t('configurator.history-restore'),
    cancelText: t('configurator.cancel'),
  })

  if (!agreed) return

  working.value = true

  try {
    await api.restoreVersion(row.value.id, version.number)
    // Through the editor: the values it holds are what every tab is bound to.
    await editor.reload()
    toast.success(t('configurator.history-restored', { number: version.number }))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

// Read again after every publication and restore: the record's stamps are the signal.
watch(
  () => [row.value?.id, row.value?.published_at, row.value?.updated_at],
  () => void load(),
  { immediate: true },
)
</script>

<template>
  <div class="wx-configurator-history">
    <wx-text size="sm" tone="muted">{{ t('configurator.history-help') }}</wx-text>

    <div class="wx-configurator-history__list">
      <wx-skeleton v-if="versions === null" class="wx-configurator-history__ghost" :rows="3" />
      <wx-empty v-else-if="versions.length === 0" :description="t('configurator.history-empty')" />
      <div
        v-for="version in versions"
        v-else
        :key="version.number"
        class="wx-configurator-history__row"
      >
        <code class="wx-configurator-history__number">
          {{ t('configurator.history-version', { number: version.number }) }}
        </code>
        <div class="wx-configurator-history__who">
          <wx-date v-if="version.created_at" :value="version.created_at" size="md" tone="default" />
          <wx-text size="sm" tone="muted">
            {{ version.author ?? t(`configurator.source-${version.source}`) }}
            <template v-if="version.comment"> · {{ version.comment }}</template>
          </wx-text>
        </div>
        <wx-badge v-if="version.on_site" type="success" dot>
          {{ t('configurator.history-on-site') }}
        </wx-badge>
        <wx-button
          v-if="editor.canManage.value && !version.on_site"
          size="sm"
          variant="outline"
          :loading="working"
          @click="restore(version)"
        >
          {{ t('configurator.history-restore') }}
        </wx-button>
      </div>
    </div>

    <wx-drafts
      :id="row?.id"
      entity="configurators"
      :can-restore="editor.canManage.value"
      :stamp="[row?.published_at, row?.updated_at]"
      @restored="editor.reload()"
    />
  </div>
</template>

<style scoped>
.wx-configurator-history {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  max-width: 720px;
}

.wx-configurator-history__list {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  overflow: hidden;
}

.wx-configurator-history__ghost {
  padding: var(--wx-space-10) var(--wx-space-12);
}

.wx-configurator-history__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-12);
  padding: var(--wx-space-10) var(--wx-space-12);
  border-block-end: 1px solid var(--wx-border-default);
}

.wx-configurator-history__row:last-child {
  border-block-end: 0;
}

.wx-configurator-history__number {
  min-width: 72px;
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-configurator-history__who {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 160px;
}
</style>
