<script setup lang="ts">
import { computed } from 'vue'
import { WxInput } from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { useCollectionEditor } from './editor'
import { NAMESPACE, useCatalogCollectionsMessages } from './i18n'

/**
 * `wx-catalog-collection-key`: a key may be written while the collection is new and is read-only
 * from then on: templates and texts already name it (§2.2). Empty — the server makes it from the
 * title.
 */
defineOptions({ name: 'WxCatalogCollectionKey' })

const props = withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false })

const value = defineModel<string | null>({ default: null })

const editor = useCollectionEditor()
useCatalogCollectionsMessages()

const t = useTranslate(NAMESPACE)

const fixed = computed(() => (editor?.collection.value ?? null) !== null)
</script>

<template>
  <wx-input
    :model-value="value ?? ''"
    :disabled="props.disabled"
    :readonly="fixed"
    :maxlength="64"
    :placeholder="fixed ? undefined : t('collection.key-placeholder')"
    @update:model-value="(typed: unknown) => (value = typed ? String(typed) : null)"
  />
</template>
