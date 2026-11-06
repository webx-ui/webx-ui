<script setup lang="ts">
import { computed } from 'vue'
import { WxFormItem, WxSelect, type SelectOption } from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { presetFields, presetOf } from './api'
import { useCollectionEditor } from './editor'
import { NAMESPACE, useCatalogCollectionsMessages } from './i18n'

/**
 * `wx-catalog-collection-preset`: a ready way of filling a collection (§3.4). It writes the ordinary
 * fields below it and stores nothing of its own, so changing any of them leaves the preset by
 * itself. What was in the fields before goes, except the category, which no preset touches.
 */
defineOptions({ name: 'WxCatalogCollectionPreset' })

const CUSTOM = 'custom'

const editor = useCollectionEditor()
useCatalogCollectionsMessages()

const t = useTranslate(NAMESPACE)

const presets = computed(() => editor?.presets.value ?? [])

const options = computed<SelectOption[]>(() => [
  { value: CUSTOM, label: t('collection.preset-custom') },
  ...presets.value.map((preset) => ({
    value: preset.key,
    label: t(`collection.preset-${preset.key}`),
  })),
])

const current = computed(() =>
  editor ? (presetOf(editor.values.value, presets.value) ?? CUSTOM) : CUSTOM,
)

function pick(key: unknown): void {
  const preset = presets.value.find((one) => one.key === key)

  if (!editor || !preset) return

  editor.values.value = { ...editor.values.value, ...presetFields(preset, editor.values.value) }
}
</script>

<template>
  <wx-form-item :label="t('collection.preset')">
    <wx-select
      :model-value="current"
      :options="options"
      :disabled="editor?.locked.value ?? false"
      @update:model-value="pick"
    />
  </wx-form-item>
</template>
