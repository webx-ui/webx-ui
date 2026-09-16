<script setup lang="ts">
import { computed, ref } from 'vue'
import { useElementWidth, WxIcon } from '@webx-ui/core'
import { stageDocument } from './frame'
import type { BlockThumbnail } from './types'

/**
 * A block drawn small: the sample render in an iframe, scaled down to the width it is given.
 *
 * An iframe rather than inline markup because a block's styles are a block's business — a
 * stray `h2 { }` in one type would otherwise restyle the whole list of cards — and because
 * the thumbnail is a picture of the site, not a part of the panel.
 */
const props = withDefaults(
  defineProps<{
    thumbnail: BlockThumbnail | null
    /** The width the block is drawn at, before scaling — a desktop. */
    width?: number
    /** How tall the picture is, in the panel's pixels. */
    height?: number
  }>(),
  { width: 1280, height: 120 },
)

const box = ref<HTMLElement | null>(null)
const boxWidth = useElementWidth(box)

const scale = computed(() => (boxWidth.value > 0 ? boxWidth.value / props.width : 0.2))

const srcdoc = computed(() =>
  props.thumbnail
    ? stageDocument({ html: props.thumbnail.html, styles: props.thumbnail.styles })
    : '',
)

const frameStyle = computed(() => ({
  width: `${props.width}px`,
  height: `${Math.round(props.height / scale.value)}px`,
  transform: `scale(${scale.value})`,
}))
</script>

<template>
  <div ref="box" class="wx-block-thumb" :style="{ height: `${height}px` }">
    <iframe
      v-if="thumbnail"
      class="wx-block-thumb__frame"
      :srcdoc="srcdoc"
      :style="frameStyle"
      sandbox="allow-same-origin"
      tabindex="-1"
      aria-hidden="true"
    />
    <div v-else class="wx-block-thumb__empty">
      <wx-icon name="grid" />
    </div>
  </div>
</template>

<style scoped>
.wx-block-thumb {
  position: relative;
  overflow: hidden;
  background: var(--wx-bg-subtle);
}

.wx-block-thumb__frame {
  position: absolute;
  inset-block-start: 0;
  inset-inline-start: 0;
  border: 0;
  transform-origin: top left;
  pointer-events: none;
  background: #fff;
}

.wx-block-thumb__empty {
  display: grid;
  place-items: center;
  height: 100%;
  color: var(--wx-text-muted);
}
</style>
