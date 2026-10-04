<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxAlert,
  WxBadge,
  WxButton,
  WxDialog,
  WxFormItem,
  WxRadioGroup,
  WxSpace,
  WxStatistic,
  WxText,
  WxUpload,
  type UploadFile,
} from '@webx-ui/core'
import { createSeoApi } from './api'
import { useSeoMessages } from './i18n'
import type { SeoLinkImportMode, SeoLinkImportResult } from './types'

/**
 * A brief in its own format (§18.4): a file is read and checked first, and only what the
 * preview showed is written. The preview is the same request with `dry_run`, so what it counts
 * is exactly what applying will do — and a row it marks as an error is a row applying skips.
 */
const { resolve, dismiss, open } = useModal<boolean>()

const context = useAdmin()
const api = createSeoApi(context)
useSeoMessages()

const t = useTranslate('webx-seo')
const message = useErrorText()

const files = ref<UploadFile[]>([])
const mode = ref<SeoLinkImportMode>('replace')
const preview = ref<SeoLinkImportResult | null>(null)
const fileError = ref<string | undefined>()
const working = ref(false)

const file = computed(() => files.value[0]?.raw ?? null)

const modes = computed(() => [
  { value: 'replace', label: t('links.mode-replace') },
  { value: 'append', label: t('links.mode-append') },
])

/* A preview belongs to one file and one mode; either changing makes it a preview of something
   else, and "Apply" must not stand under numbers that are no longer true. */
watch([file, mode], () => {
  preview.value = null
  fileError.value = undefined
})

const numbers = computed(() => {
  const result = preview.value

  if (result === null) return []

  return [
    { key: 'donors', label: t('links.result-donors'), value: result.donors },
    { key: 'links', label: t('links.result-links'), value: result.links },
    { key: 'created', label: t('links.result-created'), value: result.created },
    result.mode === 'append'
      ? { key: 'appended', label: t('links.result-appended'), value: result.appended }
      : { key: 'replaced', label: t('links.result-replaced'), value: result.replaced },
    { key: 'errors', label: t('links.result-errors'), value: result.errors },
  ]
})

async function run(dryRun: boolean): Promise<void> {
  if (file.value === null) return

  working.value = true
  fileError.value = undefined

  try {
    const result = await api.importLinks(file.value, mode.value, dryRun)

    if (!dryRun) {
      toast.success(t('links.imported', { count: result.donors }))
      resolve(true)

      return
    }

    preview.value = result
  } catch (error) {
    const errors = (error as { body?: { errors?: Record<string, string[]> } }).body?.errors

    if (errors?.file?.[0]) fileError.value = errors.file[0]
    else toast.danger(message(error, t('page.failed')))
  } finally {
    working.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('links.import-title')" :width="720">
    <div class="wx-seo-import">
      <wx-text size="sm" tone="muted">{{ t('links.import-help') }}</wx-text>

      <wx-form-item :label="t('links.file')" :error="fileError">
        <wx-upload
          v-model="files"
          accept=".csv,.txt,.xlsx"
          button-only
          :button-text="t('links.choose-file')"
        />
      </wx-form-item>

      <wx-form-item :label="t('links.mode')">
        <wx-radio-group v-model="mode" :options="modes" />
      </wx-form-item>

      <template v-if="preview">
        <div class="wx-seo-import__numbers">
          <wx-statistic
            v-for="number in numbers"
            :key="number.key"
            :title="number.label"
            :value="number.value"
          />
        </div>

        <wx-alert
          v-if="preview.donors === 0"
          type="warning"
          variant="soft"
          :description="t('links.nothing')"
        />
        <wx-alert
          v-else-if="preview.problems.length === 0"
          type="success"
          variant="soft"
          :description="t('links.no-problems')"
        />

        <section v-if="preview.problems.length > 0" class="wx-seo-import__problems">
          <wx-text weight="medium">{{ t('links.problems') }}</wx-text>
          <ul class="wx-seo-import__list">
            <li
              v-for="(problem, index) in preview.problems"
              :key="index"
              class="wx-seo-import__problem"
            >
              <wx-text size="sm" tone="muted" class="wx-seo-import__line">
                {{ t('links.line', { line: problem.line }) }}
              </wx-text>
              <wx-badge :type="problem.level === 'error' ? 'danger' : 'warning'">
                {{ problem.level === 'error' ? t('links.error') : t('links.warning') }}
              </wx-badge>
              <wx-text size="sm" class="wx-seo-import__message">{{ problem.message }}</wx-text>
            </li>
          </ul>
        </section>
      </template>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('page.cancel') }}</wx-button>
        <wx-button
          v-if="!preview"
          type="primary"
          :disabled="file === null"
          :loading="working"
          @click="run(true)"
        >
          {{ t('links.preview') }}
        </wx-button>
        <wx-button
          v-else
          type="primary"
          :disabled="preview.donors === 0"
          :loading="working"
          @click="run(false)"
        >
          {{ t('links.apply') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-seo-import {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  container-type: inline-size;
}

.wx-seo-import__numbers {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: var(--wx-space-12);
}

@container (max-width: 520px) {
  .wx-seo-import__numbers {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.wx-seo-import__problems {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-seo-import__list {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  max-height: 280px;
  margin: 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;
}

.wx-seo-import__problem {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  align-items: baseline;
}

.wx-seo-import__line {
  flex: 0 0 auto;
  min-width: 64px;
}

.wx-seo-import__message {
  flex: 1 1 240px;
  min-width: 0;
}
</style>
