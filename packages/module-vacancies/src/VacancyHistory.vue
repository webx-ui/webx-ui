<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxDate, WxDrafts } from '@webx-ui/module-admin'
import { confirm, toast, WxBadge, WxButton, WxEmpty, WxSkeleton, WxText } from '@webx-ui/core'
import { createVacanciesApi } from './api'
import { useVacancyEditor } from './editor'
import { useVacanciesMessages } from './i18n'
import type { VacancyVersion } from './types'

/**
 * What was published, when, by whom and from where — and under it, in a list of its own, the
 * copies of the draft: the autosaves, and a draft somebody else's save wrote over.
 *
 * Restoring makes the old version the draft. Publishing it is the same separate step it always
 * is, so nothing here changes the site by itself.
 */
defineOptions({ name: 'WxVacancyHistory' })

const context = useAdmin()
const api = createVacanciesApi(context)
const editor = useVacancyEditor()
useVacanciesMessages()

const t = useTranslate('webx-vacancies')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const versions = ref<VacancyVersion[] | null>(null)
const working = ref(false)

const vacancy = computed(() => editor?.vacancy.value ?? null)
const canManage = computed(() => editor?.canManage ?? false)

/** The newest publication is the site — while the vacancy is on it at all. */
const live = computed(() =>
  vacancy.value?.status === 'published' || vacancy.value?.status === 'modified'
    ? (versions.value?.[0]?.number ?? null)
    : null,
)

async function load(): Promise<void> {
  const current = vacancy.value

  if (!current) return

  try {
    versions.value = await api.versions(current.id)
  } catch (error) {
    versions.value = []
    toast.danger(message(error))
  }
}

async function restore(version: VacancyVersion): Promise<void> {
  const current = vacancy.value

  if (!current) return

  const agreed = await confirm({
    title: t('editor.restore-title', { number: version.number }),
    message: t('editor.restore-text'),
    confirmText: t('editor.restore-version'),
    cancelText: t('panel.cancel'),
  })

  if (!agreed) return

  working.value = true

  try {
    await api.restoreVersion(current.id, version.number)
    // Through the editor rather than with the answer: the values it holds are what the form is
    // bound to, and a version restored underneath it has to reach the fields.
    await editor?.reload()
    toast.success(t('editor.restored-version', { number: version.number }))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

// The vacancy is read again after every publication and every restore, so its stamp is the
// signal that there is something new to list.
watch(
  () => [vacancy.value?.id, vacancy.value?.published_at, vacancy.value?.updated_at],
  () => void load(),
  { immediate: true },
)
</script>

<template>
  <div class="wx-vacancy-history-tab">
    <div class="wx-vacancy-history">
      <wx-skeleton v-if="versions === null" class="wx-vacancy-history__ghost" :rows="3" />
      <wx-empty v-else-if="versions.length === 0" :description="t('editor.history-empty')" />
      <div
        v-for="version in versions"
        v-else
        :key="version.number"
        class="wx-vacancy-history__row"
        :class="{ 'is-live': version.number === live }"
      >
        <code class="wx-vacancy-history__number">{{
          t('editor.version', { number: version.number })
        }}</code>
        <div class="wx-vacancy-history__who">
          <wx-date v-if="version.created_at" :value="version.created_at" size="md" tone="default" />
          <wx-text size="sm" tone="muted">
            {{ version.author ?? t(`editor.source-${version.source}`) }}
            <template v-if="version.comment"> · {{ version.comment }}</template>
          </wx-text>
        </div>
        <wx-badge v-if="version.number === live" type="success" dot>{{
          t('editor.version-live')
        }}</wx-badge>
        <wx-button
          v-if="canManage && version.number !== live"
          size="sm"
          variant="outline"
          :loading="working"
          @click="restore(version)"
        >
          {{ t('editor.restore-version') }}
        </wx-button>
      </div>
    </div>

    <wx-drafts
      entity="vacancies"
      :id="vacancy?.id"
      :can-restore="canManage"
      :stamp="[vacancy?.published_at, vacancy?.updated_at]"
      @restored="editor?.reload()"
    />
  </div>
</template>

<style scoped>
.wx-vacancy-history-tab {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-24);
}

.wx-vacancy-history {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  overflow: hidden;
}

.wx-vacancy-history__ghost {
  padding: var(--wx-space-10) var(--wx-space-12);
}

.wx-vacancy-history__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  padding: var(--wx-space-10) var(--wx-space-12);
  border-block-end: 1px solid var(--wx-border-default);
  flex-wrap: wrap;
}

.wx-vacancy-history__row:last-child {
  border-block-end: 0;
}

.wx-vacancy-history__number {
  width: 40px;
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-vacancy-history__who {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 160px;
}
</style>
