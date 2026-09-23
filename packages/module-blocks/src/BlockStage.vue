<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { useElementWidth, WxSegmented, WxSkeleton } from '@webx-ui/core'
import {
  blockElement,
  fillStage,
  freezeFrame,
  mountScript,
  STAGE_KEY,
  stageDocument,
  type FrameBinding,
} from './frame'

/**
 * The block as it will stand on the site, at a chosen width: a desktop scaled down to fit the
 * column, a tablet or a phone at one to one.
 *
 * With `stage` the frame is a page of the site — its layout, header, footer and styles, with
 * an empty place for the block — loaded once; every change after that swaps the block and its
 * styles in place, so the site around it does not redraw under the editor's typing. Without
 * it (a server older than the stage) the frame is the block on a bare document, as before.
 */
const props = withDefaults(
  defineProps<{
    html: string
    styles: string
    script?: string | null
    runtime?: string | null
    /** The address of the stage page; null draws the block on a bare document. */
    stage?: string | null
    loading?: boolean
  }>(),
  { script: null, runtime: null, stage: null, loading: false },
)

const t = useTranslate('webx-blocks')

/* Pictures on screen and words as the accessible name, the same way the page preview does it:
   a device is one of the few things a picture says faster than a word. */
const widths = computed(() => [
  { value: 1280, icon: 'monitor', ariaLabel: t('page.width-desktop') },
  { value: 834, icon: 'tablet', ariaLabel: t('page.width-tablet') },
  { value: 390, icon: 'smartphone', ariaLabel: t('page.width-phone') },
])

const width = ref<number>(1280)
const box = ref<HTMLElement | null>(null)
const frame = ref<HTMLIFrameElement | null>(null)
const boxWidth = useElementWidth(box)
const contentHeight = ref(360)

/**
 * Which width the stage opens at: the widest one the column can draw at half size or better.
 *
 * A desktop is the right answer on a desktop and the wrong one on a phone, where 1280 in a
 * 340px column is a picture of a page rather than a page — the words in it are two pixels
 * tall. Until the editor picks a width the stage keeps choosing, so a column that grows or a
 * phone that turns gets the picture it can show; from the first click the choice is theirs.
 */
const picked = ref(false)

watch([boxWidth, widths], () => {
  if (picked.value || boxWidth.value === 0) return

  /* Under about half size the words in a block stop being words, so a narrower device is a
     better picture of the block than a desktop nobody can read. */
  const fits = widths.value.find((option) => boxWidth.value / option.value >= 0.45)

  width.value = (fits ?? widths.value[widths.value.length - 1]).value
})

function pick(value: number): void {
  picked.value = true
  width.value = value
}

const scale = computed(() => {
  if (boxWidth.value === 0) return 1

  return Math.min(1, boxWidth.value / width.value)
})

const srcdoc = computed(() =>
  props.stage
    ? undefined
    : stageDocument({
        html: props.html,
        styles: props.styles,
        script: props.script,
        runtime: props.runtime,
        base: typeof location === 'undefined' ? null : location.origin + '/',
      }),
)

/**
 * A new page for every new script, and only then: a script registered in a page cannot be
 * unregistered, so the old one would keep running beside the new one. Everything else is
 * swapped into the page that is already there.
 */
const generation = ref(0)
let loadedScript: string | null = null

watch(
  () => props.script,
  (script) => {
    if (props.stage && script !== loadedScript) generation.value += 1
  },
)

/**
 * The frame is as tall as the page in it: a stage that scrolls inside itself hides half of it.
 *
 * Measured on the body, never on `documentElement` — that one is never shorter than the
 * frame's own window, so once the frame has been given a height it measures itself and a
 * block that got shorter keeps the height of the one before it, with white under it.
 */
function measure(): void {
  const body = frame.value?.contentDocument?.body

  if (!body) return

  /* Where the body ends, margin included, and rounded up: a site's layout is fractional and
     may keep a margin on `body`, and a height a pixel short of either gives the frame a
     scrollbar of its own. */
  const view = body.ownerDocument.defaultView
  const margin = view ? parseFloat(view.getComputedStyle(body).marginBottom) || 0 : 0
  const bottom = body.getBoundingClientRect().bottom + margin + (view?.scrollY ?? 0)

  contentHeight.value = Math.max(120, Math.ceil(Math.max(body.scrollHeight, bottom)))
}

let binding: FrameBinding | null = null
let observer: ResizeObserver | null = null

function release(): void {
  binding?.release()
  binding = null
  observer?.disconnect()
  observer = null
}

/** Put the current block on the page — its script once per page, the rest every time. */
function fill(doc: Document): void {
  if (!props.stage) return

  if (props.script && loadedScript !== props.script) {
    loadedScript = props.script
    mountScript(doc, props.script)
  }

  fillStage(doc, { html: props.html, styles: props.styles })
}

/**
 * Bring the block into view: on a page of the site it stands under the header, and at a
 * desktop width scaled into a column the header alone can be most of what shows. Done when the
 * page arrives and not on every keystroke — that would take the scroll from the editor.
 */
function reveal(doc: Document): void {
  const element = blockElement(doc, STAGE_KEY)
  const ground = box.value

  if (!element || !ground) return

  const top = element.getBoundingClientRect().top + (doc.defaultView?.scrollY ?? 0)
  ground.scrollTop = Math.max(0, top * scale.value - 24)
}

function onLoad(): void {
  release()

  const doc = frame.value?.contentDocument

  if (!doc?.body) return

  if (props.stage) {
    loadedScript = null
    binding = freezeFrame(doc)
    fill(doc)
  }

  measure()

  /* The observer of the frame's own window: one made out here does not see into another
     document. It follows fonts, images and a block that grows as its script runs. */
  const Observer = (doc.defaultView as (Window & typeof globalThis) | null)?.ResizeObserver

  if (Observer) {
    observer = new Observer(() => measure())
    observer.observe(doc.body)
  }

  if (props.stage) {
    // The height has to be set before there is anything to scroll to.
    requestAnimationFrame(() => reveal(doc))
  }
}

watch(
  () => [props.html, props.styles] as const,
  () => {
    const doc = frame.value?.contentDocument

    if (props.stage && doc?.body && binding !== null) fill(doc)
  },
)

/* A stage that lives in a tab is measured while that tab is hidden, and a hidden document
   has no height: every redraw made behind another tab would leave the frame at its floor.
   The width coming back is the tab coming back. */
watch(boxWidth, (now, before) => {
  if (now > 0 && before === 0) setTimeout(measure, 50)
})

/* Another device moves the block down the page — a phone's header is taller — so the block is
   brought back into view as well. */
watch(width, () =>
  setTimeout(() => {
    measure()

    const doc = frame.value?.contentDocument

    if (props.stage && doc) requestAnimationFrame(() => reveal(doc))
  }, 50),
)

onBeforeUnmount(release)

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
      <wx-segmented
        :model-value="width"
        :options="widths"
        size="sm"
        :aria-label="t('page.width')"
        @update:model-value="pick(Number($event))"
      />
    </div>
    <div ref="box" class="wx-block-stage__ground">
      <wx-skeleton v-if="loading && !html" :rows="3" />
      <div v-else class="wx-block-stage__clip" :style="clipStyle">
        <iframe
          ref="frame"
          :key="generation"
          class="wx-block-stage__frame"
          :src="stage ?? undefined"
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
