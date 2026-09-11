<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import WxDialog from '../Dialog/Dialog.vue'
import WxIcon from '../Icon/Icon.vue'
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
  <div class="wx-image" :class="{ 'is-loaded': loaded, 'is-failed': failed }" :style="style">
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
      @click="previewing = true"
    >
      <wx-icon name="search" />
    </button>

    <wx-dialog v-if="preview" v-model:open="previewing" :width="880" :title="alt" closable>
      <img class="wx-image__full" :src="src" :alt="alt ?? ''" />
    </wx-dialog>
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

.wx-image__full {
  display: block;
  max-width: 100%;
  max-height: 70vh;
  margin-inline: auto;
}

@media (prefers-reduced-motion: reduce) {
  .wx-image__img,
  .wx-image__preview {
    transition: none;
  }
}
</style>
