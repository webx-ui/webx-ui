<script setup lang="ts">
import MediaList from './MediaList.vue'
import type { MediaValue } from './types'

/**
 * `wx-gallery`: a grid of pictures in an order somebody chose.
 *
 * The props of the list underneath are deliberately not declared here. Vue turns an undeclared
 * Boolean prop into `false` rather than leaving it `undefined`, so a wrapper that declares them
 * and passes them on hands the child `false` everywhere the caller asked for nothing — and half
 * the behaviour switches itself off silently. Attributes travel as they were given.
 */
defineOptions({ name: 'WxGalleryField', inheritAttrs: false })

const model = defineModel<MediaValue[]>({ default: () => [] })
</script>

<template>
  <!-- `accept` last and fixed: a gallery of `.zip` files is not a gallery. -->
  <media-list v-bind="$attrs" v-model="model" layout="grid" accept="image" />
</template>
