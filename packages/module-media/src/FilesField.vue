<script setup lang="ts">
import MediaList from './MediaList.vue'
import type { MediaValue } from './types'

/**
 * `wx-files`: a list of attachments, one to a line.
 *
 * The props of the list underneath are not declared here on purpose — see `GalleryField`.
 */
defineOptions({ name: 'WxFilesField', inheritAttrs: false })

const model = defineModel<MediaValue[]>({ default: () => [] })
</script>

<template>
  <media-list v-bind="$attrs" v-model="model" layout="rows" />
</template>
