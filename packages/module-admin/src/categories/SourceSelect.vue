<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { WxSelect, type SelectOption } from '@webx-ui/core'
import { useAdmin } from '../admin'
import { createCategoriesApi } from './api'

/**
 * `wx-select` as the panel draws it: the core's select, whose options may be a module's list
 * rather than written into the screen.
 *
 * A screen names the list by `source` — the path it answers at under the panel's API
 * (`catalog/brands`), the same string `wx-categories` reads — and the options are its records,
 * by name. A reference book is edited while the screen stays the same, so its options cannot be
 * part of the JSON. Without `source` this is the core's select with the options it was given.
 *
 * Everything else — `filterable`, `clearable`, the placeholder that says what empty means — goes
 * through to the core's select untouched.
 */
defineOptions({ name: 'WxSourceSelect', inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    /** The API a module's list answers at, under the panel's: `catalog/stock`. */
    source?: string
    options?: SelectOption[]
  }>(),
  { source: undefined, options: () => [] },
)

const admin = useAdmin()
const loaded = ref<SelectOption[]>([])

watch(
  () => props.source,
  async (source) => {
    if (!source) {
      loaded.value = []

      return
    }

    try {
      const payload = await createCategoriesApi(admin, source).list()
      loaded.value = payload.data.map((row) => ({ label: row.name, value: row.id }))
    } catch {
      // The value stays and shows as it is; a list that did not arrive is not a reason to lose it.
      loaded.value = []
    }
  },
  { immediate: true },
)

const offered = computed<SelectOption[]>(() => (props.source ? loaded.value : props.options))
</script>

<template>
  <wx-select v-bind="$attrs" :options="offered" />
</template>
