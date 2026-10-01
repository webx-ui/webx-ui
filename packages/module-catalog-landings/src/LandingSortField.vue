<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { WxSelect, type SelectOption } from '@webx-ui/core'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { useFacetRegistry } from '@webx-ui/module-catalog'
import { useLandingEditor } from './editor'
import { NAMESPACE, useCatalogLandingsMessages } from './i18n'

/**
 * `wx-catalog-landing-sort`: the landing's default order (decision 7) — one of the catalogue's
 * sorts, or none, which leaves the category's. The sorts come from the same registry the products'
 * list reads, so the panel never offers one the storefront does not know.
 */
defineOptions({ name: 'WxCatalogLandingSort' })

const value = defineModel<string | null>({ default: null })

const NONE = 'default'

const admin = useAdmin()
const registry = useFacetRegistry(admin)
const editor = useLandingEditor()
useCatalogLandingsMessages()

const t = useTranslate(NAMESPACE)

const options = computed<SelectOption[]>(() => [
  // The registry's own 'default' stands for «none of the landing's own»: the category's order.
  { value: NONE, label: t('landing.sort-default') },
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
