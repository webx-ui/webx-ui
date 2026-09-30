<script setup lang="ts">
import { computed, nextTick, onMounted, ref, useTemplateRef } from 'vue'
import {
  useModal,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxRadioGroup,
  WxSpace,
} from '@webx-ui/core'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { createPropertiesApi } from './api'
import { NAMESPACE } from './i18n'
import type { PropertyDetail, PropertyType } from './types'

/**
 * A new property: its name and its type, and nothing else.
 *
 * The type is asked here because it is asked once (decision 1): the values of the products take
 * its shape, and the page that opens next shows only the fields the type has. The code is made of
 * the name on the server, per language.
 */
defineOptions({ name: 'WxCatalogPropertyCreateDialog' })

const { open, resolve, dismiss } = useModal<PropertyDetail>()

const api = createPropertiesApi(useAdmin())
const t = useTranslate(NAMESPACE)

const title = ref('')
const type = ref<PropertyType>('select')
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const field = useTemplateRef<HTMLElement>('field')

const types = computed(() =>
  (['select', 'number', 'text', 'bool'] as const).map((value) => ({
    value,
    label: t(`property.types.${value}`),
  })),
)

onMounted(async () => {
  await nextTick()
  field.value?.querySelector('input')?.focus()
})

function errorOf(name: string): string | undefined {
  const key = Object.keys(errors.value).find((one) => one === name || one.startsWith(`${name}.`))

  return key === undefined ? undefined : errors.value[key]?.[0]
}

async function submit(): Promise<void> {
  if (title.value.trim() === '' || saving.value) return

  saving.value = true
  errors.value = {}

  try {
    resolve(await api.create({ title: title.value.trim(), type: type.value }))
  } catch (error) {
    // The code is what can be refused here — taken by another property or a facet of the
    // catalogue — and it is made of the name, so the refusal goes under the name.
    errors.value = (error as { body?: { errors?: Record<string, string[]> } }).body?.errors ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.new')" :width="440">
    <wx-form-item
      :label="t('property.title')"
      :error="errorOf('title') ?? errorOf('code')"
      required
    >
      <div ref="field">
        <wx-input v-model="title" :aria-label="t('property.title')" @keyup.enter="submit" />
      </div>
    </wx-form-item>

    <wx-form-item
      :label="t('property.type')"
      :help="t('property.type-help')"
      :error="errorOf('type')"
    >
      <wx-radio-group v-model="type" :options="types" />
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
