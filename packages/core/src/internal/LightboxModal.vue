<script setup lang="ts">
import { ref } from 'vue'
import WxLightbox from '../components/Lightbox/Lightbox.vue'
import { useModal } from '../composables/useModal'
import type { LightboxSource } from '../components/Lightbox/types'

defineOptions({ name: 'WxLightboxModal', inheritAttrs: false })

/*
 * The lightbox from code — all `openLightbox` is. Only `items` and `start` are declared; the rest rides
 * through on `$attrs`, so the lightbox's own defaults survive (see `ImageEditorDialog`).
 */
const props = withDefaults(defineProps<{ items: LightboxSource[]; start?: number }>(), { start: 0 })

const { open, dismiss } = useModal<void>()
const index = ref(props.start)

/* The host settles on `update:open` of its child, which this wrapper is, not the lightbox. */
function onOpen(value: boolean) {
  if (!value) dismiss()
}
</script>

<template>
  <wx-lightbox
    v-bind="$attrs"
    v-model:index="index"
    :items="items"
    :open="open"
    @update:open="onOpen"
  />
</template>
