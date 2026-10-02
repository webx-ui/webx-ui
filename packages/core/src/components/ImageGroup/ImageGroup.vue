<script setup lang="ts">
import { provide, ref, shallowRef, useAttrs } from 'vue'
import WxLightbox from '../Lightbox/Lightbox.vue'
import { imageGroupKey, type ImageGroupEntry } from '../../composables/useImageGroup'
import type { LightboxItem } from '../Lightbox/types'
import type { ImageGroupProps } from './types'

defineOptions({ name: 'WxImageGroup', inheritAttrs: false })

/*
 * Every `<wx-image preview>` inside opens one gallery of all of them.
 *
 * The group is a component rather than a shared name on each picture: two cards on one
 * screen that both call their pictures "photos" would otherwise become one gallery.
 *
 * The lightbox's props are not declared here. They ride through on `$attrs`: a wrapper that
 * re-declares a child's booleans turns every one the caller left alone into `false`, and
 * the strip's "on when there is more than one" would be lost on the way.
 */
withDefaults(defineProps<ImageGroupProps>(), { tag: 'div' })

/* Functions, not `computed`: `attrs` is not reactive, and a cached split would go stale. */
const attrs = useAttrs()
const rootAttrs = () => ({ class: attrs.class, style: attrs.style })
const lightboxAttrs = () =>
  Object.fromEntries(Object.entries(attrs).filter(([name]) => name !== 'class' && name !== 'style'))

const entries = new Set<ImageGroupEntry>()

const open = ref(false)
const index = ref(0)
const items = shallowRef<LightboxItem[]>([])

/*
 * Order is the page's, read when the gallery opens: pictures are added, removed and
 * reordered by whatever renders them, and the page is the one place that is never stale.
 */
function ordered() {
  return [...entries].sort((a, b) => {
    const first = a.el()
    const second = b.el()
    if (!first || !second) return 0
    return first.compareDocumentPosition(second) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1
  })
}

provide(imageGroupKey, {
  register(entry) {
    entries.add(entry)
    return () => entries.delete(entry)
  },
  open(entry) {
    const list = ordered()
    items.value = list.map((one) => one.item())
    index.value = Math.max(0, list.indexOf(entry))
    open.value = true
  },
})
</script>

<template>
  <component :is="tag" class="wx-image-group" v-bind="rootAttrs()">
    <slot />
    <wx-lightbox
      v-bind="lightboxAttrs()"
      v-model:open="open"
      v-model:index="index"
      :items="items"
    />
  </component>
</template>
