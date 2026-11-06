<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { WxAlert } from '@webx-ui/core'
import { useAdmin, useErrorText } from '@webx-ui/module-admin'
import { WxCatalogFacetSet } from '@webx-ui/module-catalog'
import { createCollectionsApi } from './api'
import { useCollectionEditor } from './editor'
import { useCatalogCollectionsMessages } from './i18n'
import type { BaseFacet, FacetSetValue } from './types'

/**
 * `wx-catalog-collection-set`: the set of a filter (§6.3). The field itself is the catalogue's
 * `WxCatalogFacetSet`; what is a collection's lives here: the facets its category offers, asked
 * again when the category changes, and the words of a value they no longer count. The number of
 * products is the preview's, beside the conditions, so the field says none of its own.
 */
defineOptions({ name: 'WxCatalogCollectionSet' })

const value = defineModel<FacetSetValue | null>({ default: null })

const admin = useAdmin()
const api = createCollectionsApi(admin)
const editor = useCollectionEditor()
useCatalogCollectionsMessages()

const message = useErrorText()

const locked = computed(() => editor?.locked.value ?? false)

const category = computed<number | null>(() => {
  const id = editor?.values.value.category_id

  return typeof id === 'number' ? id : id ? Number(id) : null
})

const offered = ref<BaseFacet[]>([])
const failed = ref('')

let asked = 0

async function load(): Promise<void> {
  const ticket = ++asked

  failed.value = ''

  try {
    const answer = await api.facets(category.value)

    if (ticket !== asked) return

    offered.value = answer.data
    if (editor) editor.presets.value = answer.presets
  } catch (error) {
    if (ticket === asked) failed.value = message(error)
  }
}

watch(category, () => void load(), { immediate: true })

function valueWord(key: string, chosen: string): string | undefined {
  return editor?.collection.value?.filter_labels?.[key]?.[chosen]
}
</script>

<template>
  <div class="wx-catalog-collection-set">
    <wx-alert v-if="failed" type="danger">{{ failed }}</wx-alert>

    <wx-catalog-facet-set
      v-model="value"
      :facets="offered"
      :disabled="locked"
      :value-label="valueWord"
    />
  </div>
</template>

<style scoped>
.wx-catalog-collection-set {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}
</style>
