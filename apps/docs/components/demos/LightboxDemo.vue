<script setup lang="ts">
import { WxButton, WxImage, WxImageGroup, openLightbox, type LightboxItem } from '@webx-ui/core'

const photo = (seed: string, width = 1600, height = 1000) =>
  `https://picsum.photos/seed/${seed}/${width}/${height}`

const thumb = (seed: string) => `https://picsum.photos/seed/${seed}/240/160`

const seeds = ['wx-lb-1', 'wx-lb-2', 'wx-lb-3', 'wx-lb-4', 'wx-lb-5']

const product: LightboxItem[] = [
  { src: photo('wx-lb-6'), thumb: thumb('wx-lb-6'), alt: 'The front' },
  { src: photo('wx-lb-7', 1000, 1400), thumb: thumb('wx-lb-7'), alt: 'A tall one' },
  {
    src: photo('wx-lb-8'),
    thumb: thumb('wx-lb-8'),
    alt: 'A video on YouTube',
    video: 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
  },
  {
    src: photo('wx-lb-9'),
    thumb: thumb('wx-lb-9'),
    alt: 'A video file',
    video: 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
  },
]
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A group: every picture opens the same gallery</span>
      <wx-image-group class="row">
        <wx-image
          v-for="(seed, i) in seeds"
          :key="seed"
          :src="photo(seed)"
          :alt="`Picture ${i + 1}`"
          :width="120"
          :height="80"
          radius="8px"
          preview
        />
      </wx-image-group>
      <span class="wx-demo__note">
        Arrows or ← →, a swipe on a phone; a double click, the wheel or a pinch to zoom.
      </span>
    </div>

    <div>
      <span class="wx-demo__label">A cover with a gallery behind it, videos too</span>
      <div class="row">
        <wx-image
          :src="product[0]!.src"
          alt="A product"
          :width="200"
          :height="130"
          radius="12px"
          preview
          :preview-list="product"
        />
        <wx-button @click="openLightbox(product, 2)">Open on the third, from code</wx-button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-12);
}

.wx-demo__note {
  display: block;
  margin-top: var(--wx-space-6);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}
</style>
