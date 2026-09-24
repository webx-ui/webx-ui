<script setup lang="ts">
import { nextTick, onMounted, ref, useTemplateRef } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { useModal, WxButton, WxDialog, WxFormItem, WxInput, WxSpace } from '@webx-ui/core'
import { createServicesApi } from './api'
import { useServicesMessages } from './i18n'
import type { ServiceRow } from './types'

/**
 * A new service: what it is called, and nothing else. The server makes the address out of the
 * title; the address field on the settings tab is where there is room to say what changing it
 * costs.
 */
const { open, resolve, dismiss } = useModal<ServiceRow>()

const context = useAdmin()
const api = createServicesApi(context)
useServicesMessages()

const t = useTranslate('webx-services')

const title = ref('')
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const field = useTemplateRef<HTMLElement>('field')

onMounted(async () => {
  await nextTick()
  field.value?.querySelector('input')?.focus()
})

async function submit(): Promise<void> {
  if (title.value.trim() === '' || saving.value) return

  saving.value = true
  errors.value = {}

  try {
    resolve(await api.create({ title: title.value.trim() }))
  } catch (error) {
    // The address is what can be refused here, and it is refused under the title that made it:
    // the registry sees the categories and the pages too, so "taken" can mean taken by either.
    errors.value = (error as { body?: { errors?: Record<string, string[]> } }).body?.errors ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.new-title')" :width="440">
    <wx-form-item
      :label="t('panel.field-title')"
      :error="errors.title?.[0] ?? errors.slug?.[0]"
      required
    >
      <div ref="field">
        <wx-input v-model="title" :aria-label="t('panel.field-title')" @keyup.enter="submit" />
      </div>
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" :disabled="title.trim() === ''" @click="submit">
          {{ t('panel.create') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
