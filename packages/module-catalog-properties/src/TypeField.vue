<script setup lang="ts">
import { computed } from 'vue'
import { WxSelect, type SelectOption } from '@webx-ui/core'
import { usePropertyEditor } from './editor'

/**
 * `wx-catalog-property-type`: the type of a property, chosen once (decision 1).
 *
 * A property that exists shows its type and does not let it change — the values of its products
 * have that shape, and «change the type» is a new property and a bulk move. The server refuses it
 * too (`errors.type-fixed`); the field says so before anybody tries. Without an editor above it —
 * a demo, a screen used for a new record — it is the select it looks like.
 */
defineOptions({ name: 'WxCatalogPropertyType', inheritAttrs: false })

const props = withDefaults(defineProps<{ options?: SelectOption[] }>(), { options: () => [] })

const value = defineModel<string | null>({ default: null })

const editor = usePropertyEditor()

const fixed = computed(() => (editor?.property.value?.id ?? null) !== null)
</script>

<template>
  <wx-select
    v-bind="$attrs"
    v-model="value"
    :options="props.options"
    :disabled="fixed || undefined"
  />
</template>
