<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { useElementWidth, WxSegmented, WxSkeleton } from '@webx-ui/core'
import { stageDocument } from './frame'

/**
 * The block on its own, at a chosen width: a desktop scaled down to fit the column, a tablet
 * or a phone at one to one. The document is the block, its styles, its script and the
 * runtime, and nothing of the panel — an iframe, so the block's CSS stays the block's.
 */
const props = withDefaults(
  defineProps<{
    html: string
    styles: string
    script?: string | null
    runtime?: string | null
    loading?: boolean
  }>(),
  { script: null, runtime: null, loading: false },
)

const t = useTranslate('webx-blocks')

const widths = computed(() => [
  { value: 1280, label: t('page.width-desktop') },
  { value: 834, label: t('page.width-tablet') },
  { value: 390, label: t('page.width-phone') },
])

const width = ref<number>(1280)
const box = ref<HTMLElement | null>(null)
const frame = ref<HTMLIFrameElement | null>(null)
const boxWidth = useElementWidth(box)
const contentHeight = ref(360)

const scale = computed(() => {
  if (boxWidth.value === 0) return 1

  return Math.min(1, boxWidth.value / width.value)
})

const srcdoc = computed(() =>
  stageDocument({
    html: props.html,
    styles: props.styles,
    script: props.script,
    runtime: props.runtime,
    base: typeof location === 'undefined' ? null : location.origin + '/',
  }),
)

/** The frame grows to its content: a stage that scrolls inside itself hides half the block. */
function measure(): void {
  const doc = frame.value?.contentDocument

  if (!doc?.documentElement) return

  contentHeight.value = Math.max(120, doc.documentElement.scrollHeight)
}

function onLoad(): void {
  measure()
  // Fonts and images arrive after `load` of the document itself.
  setTimeout(measure, 300)
}

watch(width, () => setTimeout(measure, 50))

const frameStyle = computed(() => ({
  width: `${width.value}px`,
  height: `${contentHeight.value}px`,
  transform: `scale(${scale.value})`,
}))

const clipStyle = computed(() => ({
  width: `${Math.round(width.value * scale.value)}px`,
  height: `${Math.round(contentHeight.value * scale.value)}px`,
}))
</script>

<template>
  <div class="wx-block-stage">
    <div class="wx-block-stage__bar">
      <wx-segmented v-model="width" :options="widths" size="sm" />
    </div>
    <div ref="box" class="wx-block-stage__ground">
      <wx-skeleton v-if="loading && !html" :rows="3" />
      <div v-else class="wx-block-stage__clip" :style="clipStyle">
        <iframe
          ref="frame"
          class="wx-block-stage__frame"
          :srcdoc="srcdoc"
          :style="frameStyle"
          sandbox="allow-same-origin allow-scripts"
          :title="t('page.preview')"
          @load="onLoad"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.wx-block-stage {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  overflow: hidden;
}

.wx-block-stage__bar {
  display: flex;
  justify-content: space-between;
  gap: var(--wx-space-8);
  padding: var(--wx-space-8) var(--wx-space-12);
  border-block-end: 1px solid var(--wx-border-muted);
}

.wx-block-stage__ground {
  padding: var(--wx-space-12);
  background: var(--wx-bg-subtle);
  overflow: auto;
  max-height: 62vh;
}

.wx-block-stage__clip {
  overflow: hidden;
  margin-inline: auto;
  box-shadow: var(--wx-shadow-sm);
}

.wx-block-stage__frame {
  display: block;
  border: 0;
  background: #fff;
  transform-origin: top left;
}
</style>
