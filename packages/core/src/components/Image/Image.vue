<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import WxLightbox from '../Lightbox/Lightbox.vue'
import { useImageGroup, type ImageGroupEntry } from '../../composables/useImageGroup'
import type { ImageEmits, ImageProps } from './types'

defineOptions({ name: 'WxImage' })

const props = withDefaults(defineProps<ImageProps>(), {
  src: undefined,
  alt: undefined,
  fit: 'cover',
  width: undefined,
  height: undefined,
  radius: undefined,
  lazy: true,
  placeholder: undefined,
  preview: false,
  previewList: undefined,
  previewStart: undefined,
  previewLabel: 'View full size',
})

const emit = defineEmits<ImageEmits>()

defineSlots<{
  /** Shown while the picture is on its way. */
  placeholder?: () => unknown
  /** Shown instead of a picture that will not load. */
  error?: () => unknown
}>()

const loaded = ref(false)
const failed = ref(false)
const previewing = ref(false)

/* A new picture starts over: the last one's outcome says nothing about this one. */
watch(
  () => props.src,
  () => {
    loaded.value = false
    failed.value = false
  },
)

function onLoad(event: Event) {
  loaded.value = true
  emit('load', event)
}

function onError(event: Event) {
  failed.value = true
  emit('error', event)
}

const root = ref<HTMLElement | null>(null)

/*
 * Inside a `WxImageGroup` the picture is one item of the group's gallery, unless it brings a
 * gallery of its own. The group reads `src` and `alt` when it opens, so a picture that
 * changed since it joined is shown as it is now.
 */
const group = useImageGroup()
const entry: ImageGroupEntry = {
  el: () => root.value,
  item: () => ({ src: props.src, alt: props.alt }),
}

watch(
  () => Boolean(group && props.preview && !props.previewList),
  (joined, _, onCleanup) => {
    if (joined && group) onCleanup(group.register(entry))
  },
  { immediate: true },
)

const previewItems = computed(() => props.previewList ?? [{ src: props.src, alt: props.alt }])

function startIndex() {
  if (props.previewStart !== undefined) return props.previewStart
  const found = previewItems.value.findIndex(
    (item) => (typeof item === 'string' ? item : item.src) === props.src,
  )
  return Math.max(0, found)
}

/* The lightbox is made on the first click: a library of a hundred pictures needs none of them until then. */
const lightboxMade = ref(false)
const lightboxIndex = ref(0)

function openPreview() {
  if (group && !props.previewList) {
    group.open(entry)
    return
  }
  lightboxIndex.value = startIndex()
  lightboxMade.value = true
  previewing.value = true
}

function length(value: number | string | undefined) {
  if (value === undefined) return undefined
  return typeof value === 'number' ? `${value}px` : value
}

const style = computed(() => ({
  width: length(props.width),
  height: length(props.height),
  borderRadius: length(props.radius),
}))
</script>

<template>
  <div
    ref="root"
    class="wx-image"
    :class="{ 'is-loaded': loaded, 'is-failed': failed }"
    :style="style"
  >
    <!--
      The placeholder is underneath rather than instead of: the picture lands on top
      of it when it arrives, so nothing in the layout moves and there is no frame in
      which the box is empty.
    -->
    <div v-if="!loaded && !failed" class="wx-image__placeholder">
      <slot name="placeholder">
        <img
          v-if="placeholder"
          class="wx-image__blur"
          :src="placeholder"
          alt=""
          aria-hidden="true"
        />
      </slot>
    </div>

    <div v-if="failed" class="wx-image__failed">
      <slot name="error">
        <wx-icon name="image" />
      </slot>
    </div>

    <img
      v-if="src && !failed"
      class="wx-image__img"
      :src="src"
      :alt="alt ?? ''"
      :loading="lazy ? 'lazy' : 'eager'"
      :decoding="lazy ? 'async' : 'auto'"
      :style="{ objectFit: fit }"
      @load="onLoad"
      @error="onError"
    />

    <button
      v-if="preview && loaded"
      type="button"
      class="wx-image__preview"
      :aria-label="previewLabel"
      @click="openPreview"
    >
      <wx-icon name="search" />
    </button>

    <wx-lightbox
      v-if="lightboxMade"
      v-model:open="previewing"
      v-model:index="lightboxIndex"
      :items="previewItems"
    />
  </div>
</template>

<style scoped>
.wx-image {
  position: relative;
  display: inline-block;
  box-sizing: border-box;
  overflow: hidden;
  background: var(--wx-bg-fill);
  vertical-align: middle;
}

.wx-image__img {
  display: block;
  width: 100%;
  height: 100%;
  border-radius: inherit;
  opacity: 0;
  transition: opacity var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-image.is-loaded .wx-image__img {
  opacity: 1;
}

.wx-image__placeholder,
.wx-image__failed {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--wx-text-placeholder);
}

.wx-image__blur {
  width: 100%;
  height: 100%;
  /* A thumbnail stretched to full size is a smear unless it is blurred on purpose. */
  filter: blur(12px);
  object-fit: cover;
  transform: scale(1.06);
}

.wx-image__failed :deep(.wx-icon) {
  width: 32px;
  height: 32px;
}

.wx-image__preview {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  background: color-mix(in srgb, var(--wx-bg-inverse) 45%, transparent);
  border: none;
  color: var(--wx-text-inverse);
  cursor: zoom-in;
  opacity: 0;
  transition: opacity var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-image__preview:hover,
.wx-image__preview:focus-visible {
  outline: none;
  opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
  .wx-image__img,
  .wx-image__preview {
    transition: none;
  }
}
</style>
