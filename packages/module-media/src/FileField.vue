<script setup lang="ts">
import { computed } from 'vue'
import MediaList from './MediaList.vue'
import type { MediaValue } from './types'

/**
 * `wx-file`: one document, on a card that says what it is.
 *
 * The same list underneath, kept to one item. What is stored is a single value rather than a
 * list of one, because that is what a template reading `props.file.url` expects — the shape of
 * the value follows the field, not the component that draws it.
 *
 * The props of the list underneath are not declared here on purpose — see `GalleryField`.
 */
defineOptions({ name: 'WxFileField', inheritAttrs: false })

const model = defineModel<MediaValue | null>({ default: null })

const list = computed<MediaValue[]>({
  get: () => (model.value ? [model.value] : []),
  set: (next) => {
    model.value = next[0] ?? null
  },
})
</script>

<template>
  <media-list v-bind="$attrs" v-model="list" layout="rows" single />
</template>
