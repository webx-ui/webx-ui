<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { useElementWidth, WxButton, WxSegmented, WxText } from '@webx-ui/core'
import { highlightBlock, replaceBlock } from './frame'

/**
 * The page in an iframe, the way it will be: the same handler, the same view, the draft
 * laid over the columns. Two shapes — the whole page wide while nothing is selected, a
 * phone at one to one while a block is being edited — and a full-screen mode for looking
 * at the desktop and the tablet, because a desktop in a 420 px column is a picture nobody
 * can read.
 *
 * The panel and the site are one application on one domain, so the frame's document is
 * reachable: selection is a class, a change is a swap between the marker comments.
 */
const props = withDefaults(
  defineProps<{
    url: string | null
    selected?: string | null
    /** `wide` when the page is being looked at, `phone` while a block is being edited. */
    mode?: 'wide' | 'phone'
    /** Bumped by the host to reload the page. */
    reload?: number
    /** Be as tall as what it is drawn in, and scroll the page inside rather than beside. */
    fill?: boolean
  }>(),
  { selected: null, mode: 'wide', reload: 0, fill: false },
)

const t = useTranslate('webx-blocks')

const frame = ref<HTMLIFrameElement | null>(null)
const box = ref<HTMLElement | null>(null)
const boxWidth = useElementWidth(box)
const fullscreen = ref(false)
const fullWidth = ref<number>(1280)
const ready = ref(false)

const widths = computed(() => [
  { value: 1280, label: t('field.width-desktop') },
  { value: 834, label: t('field.width-tablet') },
  { value: 390, label: t('field.width-phone') },
])

const width = computed(() =>
  fullscreen.value ? fullWidth.value : props.mode === 'phone' ? 390 : 1280,
)

const scale = computed(() => {
  if (fullscreen.value || props.mode === 'phone' || boxWidth.value === 0) return 1

  return Math.min(1, boxWidth.value / width.value)
})

const frameStyle = computed(() => ({
  width: `${width.value}px`,
  transform: `scale(${scale.value})`,
}))

const label = computed(() => {
  if (!props.url) return ''

  try {
    const parsed = new URL(props.url, location.origin)

    return `${parsed.pathname} · ${width.value} px`
  } catch {
    return props.url
  }
})

function doc(): Document | null {
  try {
    return frame.value?.contentDocument ?? null
  } catch {
    return null
  }
}

function onLoad(): void {
  ready.value = true
  const page = doc()
  if (page) highlightBlock(page, props.selected ?? null)
}

/** Swap one block's markup in place; false when the page has to be reloaded instead. */
function replace(key: string, html: string): boolean {
  const page = doc()

  if (!page || !ready.value) return false

  const done = replaceBlock(page, key, html)
  if (done) highlightBlock(page, props.selected ?? null)

  return done
}

function refresh(): void {
  ready.value = false
  if (frame.value && props.url) frame.value.src = props.url
}

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape' && fullscreen.value) {
    fullscreen.value = false
    event.stopPropagation()
  }
}

watch(
  () => props.selected,
  (key) => {
    const page = doc()
    if (page && ready.value) highlightBlock(page, key ?? null)
  },
)

watch(() => props.reload, refresh)
watch(
  () => props.url,
  () => (ready.value = false),
)

onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))

defineExpose({ replace, refresh, open: () => (fullscreen.value = true) })
</script>

<template>
  <div
    class="wx-blocks-preview"
    :class="{ 'is-fullscreen': fullscreen, 'is-phone': mode === 'phone', 'is-fill': fill }"
    :style="{ '--wx-preview-scale': scale }"
  >
    <div class="wx-blocks-preview__bar">
      <wx-text size="xs" tone="muted" class="wx-blocks-preview__label" truncate>{{
        label
      }}</wx-text>
      <div class="wx-blocks-preview__tools">
        <wx-segmented v-if="fullscreen" v-model="fullWidth" :options="widths" size="sm" />
        <wx-button
          v-if="url"
          size="sm"
          variant="outline"
          :href="url"
          target="_blank"
          rel="noopener"
        >
          {{ t('field.open-site') }}
        </wx-button>
        <wx-button size="sm" variant="outline" @click="fullscreen = !fullscreen">
          {{ fullscreen ? t('field.close') : t('field.fullscreen') }}
        </wx-button>
      </div>
    </div>
    <div ref="box" class="wx-blocks-preview__ground">
      <div class="wx-blocks-preview__clip" :style="{ width: `${Math.round(width * scale)}px` }">
        <iframe
          v-if="url"
          ref="frame"
          class="wx-blocks-preview__frame"
          :src="url"
          :style="frameStyle"
          :title="t('field.preview')"
          @load="onLoad"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.wx-blocks-preview {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  overflow: hidden;
  min-width: 0;
}

.wx-blocks-preview.is-fullscreen {
  position: fixed;
  inset: 0;
  z-index: 40;
  border: 0;
  border-radius: 0;
}

.wx-blocks-preview__bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
  padding: var(--wx-space-8) var(--wx-space-12);
  border-block-end: 1px solid var(--wx-color-border-muted, var(--wx-border-default));
}

.wx-blocks-preview__label {
  min-width: 0;
  font-family: var(--wx-font-family-mono);
}

.wx-blocks-preview__tools {
  display: flex;
  gap: var(--wx-space-8);
  align-items: center;
  flex-wrap: wrap;
}

.wx-blocks-preview__ground {
  flex: 1;
  padding: var(--wx-space-12);
  background: var(--wx-bg-subtle);
  overflow: auto;
  max-height: calc(100vh - 200px);
}

.is-fullscreen .wx-blocks-preview__ground {
  max-height: none;
}

/*
 * Told how tall to be rather than working it out from the window: the two viewport-sized caps
 * below are for a preview standing on a page that scrolls, and inside a panel that already has
 * a height they are a second, smaller box that fights the first.
 */
.wx-blocks-preview.is-fill {
  height: 100%;
}

.is-fill .wx-blocks-preview__ground {
  max-height: none;
  min-height: 0;
}

/*
 * All of the height it was given, and no more: the frame is as tall as the ground once the
 * scale has been applied to it, so the page inside scrolls in its own window rather than in a
 * box of ours. Without the division the frame is drawn at `height × scale` and the rest of the
 * ground is empty — a third of the column, at the scale a 420px preview of a 1280px page runs
 * at, and that emptiness is what the ground used to scroll.
 */
.is-fill:not(.is-fullscreen) .wx-blocks-preview__ground {
  overflow: hidden;
}

.is-fill:not(.is-fullscreen) .wx-blocks-preview__clip {
  height: 100%;
}

.is-fill:not(.is-fullscreen) .wx-blocks-preview__frame {
  height: calc(100% / var(--wx-preview-scale, 1));
}

.wx-blocks-preview__clip {
  margin-inline: auto;
  overflow: hidden;
  box-shadow: var(--wx-shadow-sm);
}

.wx-blocks-preview__frame {
  display: block;
  border: 0;
  background: #fff;
  height: min(72vh, 720px);
  transform-origin: top left;
}

.is-fullscreen .wx-blocks-preview__frame {
  height: calc(100vh - 100px);
}
</style>
