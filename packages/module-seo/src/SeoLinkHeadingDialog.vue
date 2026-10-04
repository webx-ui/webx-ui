<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { createSeoApi } from './api'
import { useSeoMessages } from './i18n'
import type { SeoLinkHeadingResult } from './types'

/**
 * One heading on many donors (§18.1, decision 5): the ticked ones, or every donor whose address
 * starts with what is typed. How many it reaches is shown before it is applied, because a prefix
 * one segment too short is a whole section of the site.
 */
const props = withDefaults(defineProps<{ ids?: number[] }>(), { ids: () => [] })

const { resolve, dismiss, open } = useModal<boolean>()

const context = useAdmin()
const api = createSeoApi(context)
useSeoMessages()

const t = useTranslate('webx-seo')
const message = useErrorText()

const heading = ref('')
const prefix = ref('')
const preview = ref<SeoLinkHeadingResult | null>(null)
const errors = ref<Record<string, string[]>>({})
const working = ref(false)

const ticked = computed(() => props.ids.length > 0)

/** The donors shown by name before the rest are counted. */
const SHOWN = 8

watch([heading, prefix], () => {
  preview.value = null
})

function input(dryRun: boolean) {
  const value = heading.value.trim()

  return {
    ...(ticked.value ? { ids: props.ids } : { prefix: prefix.value.trim() }),
    heading: value === '' ? null : value,
    dry_run: dryRun,
  }
}

async function run(dryRun: boolean): Promise<void> {
  working.value = true
  errors.value = {}

  try {
    const result = await api.linksHeading(input(dryRun))

    if (!dryRun) {
      toast.success(t('links.heading-applied', { count: result.count }))
      resolve(true)

      return
    }

    preview.value = result
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) errors.value = body.errors
    else toast.danger(message(error, t('page.failed')))
  } finally {
    working.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('links.heading-title')" :width="560">
    <div class="wx-seo-heading">
      <wx-text v-if="ticked" size="sm">{{
        t('links.scope-selected', { count: ids.length })
      }}</wx-text>

      <wx-form-item
        v-else
        :label="t('links.prefix')"
        :help="t('links.prefix-help')"
        :error="errors.prefix?.[0]"
      >
        <wx-input v-model="prefix" placeholder="/catalog/tech/" />
      </wx-form-item>

      <wx-form-item
        :label="t('links.heading')"
        :help="t('links.heading-help')"
        :error="errors.heading?.[0]"
      >
        <wx-input v-model="heading" />
      </wx-form-item>

      <template v-if="preview">
        <wx-alert
          v-if="preview.count === 0"
          type="warning"
          variant="soft"
          :description="t('links.heading-none')"
        />
        <wx-alert
          v-else
          type="info"
          variant="soft"
          :title="t('links.heading-count', { count: preview.count })"
        >
          <ul class="wx-seo-heading__donors">
            <li v-for="donor in preview.donors.slice(0, SHOWN)" :key="donor">
              <wx-text mono size="sm">{{ donor }}</wx-text>
            </li>
            <li v-if="preview.donors.length > SHOWN">
              <wx-text size="sm" tone="muted">
                {{ t('links.more', { count: preview.donors.length - SHOWN }) }}
              </wx-text>
            </li>
          </ul>
        </wx-alert>
      </template>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('page.cancel') }}</wx-button>
        <wx-button
          v-if="!preview"
          type="primary"
          :disabled="!ticked && prefix.trim() === ''"
          :loading="working"
          @click="run(true)"
        >
          {{ t('links.preview') }}
        </wx-button>
        <wx-button
          v-else
          type="primary"
          :disabled="preview.count === 0"
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
.wx-seo-heading {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-seo-heading__donors {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}
</style>
