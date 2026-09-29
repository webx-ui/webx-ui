<script setup lang="ts">
import { nextTick, onMounted, ref, useTemplateRef } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import {
  useLocales,
  useModal,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSpace,
} from '@webx-ui/core'
import { createCatalogApi } from './api'
import { useCatalogMessages } from './i18n'
import { firstError } from './errors'
import type { ProductDetail } from './types'

/**
 * A new product: what it is called, and nothing else. It is created unpublished and without a
 * category — the form is where there is room for those, and a product cannot be published before
 * it has a main category anyway (decision 3). The address is made of the name.
 */
const { open, resolve, dismiss } = useModal<ProductDetail>()

const context = useAdmin()
const api = createCatalogApi(context)
const locales = useLocales()
useCatalogMessages()

const t = useTranslate('webx-catalog')

const name = ref('')
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const field = useTemplateRef<HTMLElement>('field')

onMounted(async () => {
  await nextTick()
  field.value?.querySelector('input')?.focus()
})

async function submit(): Promise<void> {
  if (name.value.trim() === '' || saving.value) return

  saving.value = true
  errors.value = {}

  try {
    resolve(
      await api.createProduct({
        name: { [locales.active.value]: name.value.trim() },
        is_published: false,
      }),
    )
  } catch (error) {
    errors.value = (error as { body?: { errors?: Record<string, string[]> } }).body?.errors ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.new-product')" :width="440">
    <wx-form-item
      :label="t('product.name')"
      :error="firstError(errors, 'name') ?? firstError(errors, 'slug')"
      required
    >
      <div ref="field">
        <wx-input v-model="name" :aria-label="t('product.name')" @keyup.enter="submit" />
      </div>
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" :disabled="name.trim() === ''" @click="submit">
          {{ t('panel.create') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
