<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { WxSelect, type SelectOption } from '@webx-ui/core'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { useFacetRegistry } from '@webx-ui/module-catalog'
import { useCollectionEditor } from './editor'
import { NAMESPACE, useCatalogCollectionsMessages } from './i18n'

/**
 * `wx-catalog-collection-sort`: the order of a filter — one of the catalogue's sorts, or none, which
 * is the catalogue's own. The sorts come from the registry the products' list reads, so the panel
 * never offers one the storefront does not know.
 */
defineOptions({ name: 'WxCatalogCollectionSort' })

const value = defineModel<string | null>({ default: null })

const NONE = 'default'

const admin = useAdmin()
const registry = useFacetRegistry(admin)
const editor = useCollectionEditor()
useCatalogCollectionsMessages()

const t = useTranslate(NAMESPACE)

const options = computed<SelectOption[]>(() => [
  { value: NONE, label: t('collection.sort-default') },
  ...registry.sorts.value
    .filter((sort) => sort.key !== NONE)
    .map((sort) => ({ value: sort.key, label: sort.label })),
])

onMounted(() => void registry.load().catch(() => undefined))
</script>

<template>
  <wx-select
    :model-value="value ?? NONE"
    :options="options"
    :disabled="editor?.locked.value ?? false"
    @update:model-value="
      (picked: unknown) => (value = picked && picked !== NONE ? String(picked) : null)
    "
  />
</template>
