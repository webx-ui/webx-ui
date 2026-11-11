<script setup lang="ts">
import { WxTextarea } from '@webx-ui/core'

/**
 * `wx-embed`: the frame of a service — Google Maps' "Embed a map" — pasted as the service gives
 * it, the whole `<iframe …>` or its address. What is kept is the server's to say (`EmbedType`):
 * the address alone, and a code of any other site is refused on save with the source's words.
 *
 * `source` names the service for the server and is not handed to the textarea; the rest of the
 * node's props — `rows`, `placeholder` — travel to `WxTextarea` as they were written.
 */
defineOptions({ name: 'WxEmbedField', inheritAttrs: false })

defineProps<{
  modelValue?: string | null
  /** The service the server holds the value to: `google-maps`. */
  source?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
</script>

<template>
  <wx-textarea
    v-bind="$attrs"
    class="wx-embed-field"
    :model-value="modelValue ?? ''"
    spellcheck="false"
    autocapitalize="off"
    @update:model-value="(value: string) => emit('update:modelValue', value)"
  />
</template>

<style scoped>
.wx-embed-field :deep(.wx-textarea__inner) {
  font-family: var(--wx-font-family-mono);
}
</style>
