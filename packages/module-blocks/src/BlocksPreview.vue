<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { useElementWidth, WxAction, WxSegmented, WxText } from '@webx-ui/core'
import { bindFrame, blockElement, highlightBlock, replaceBlock, type FrameBinding } from './frame'

/**
 * The page in an iframe, the way it will be: the same handler, the same view, the draft
 * laid over the columns. The whole page at desktop width, unless the bar is asked for a
 * tablet or a phone; full screen is for the panel too narrow to read a desktop in.
 *
 * The panel and the site are one application on one domain, so the frame's document is
 * reachable: selection is a class, a change is a swap between the marker comments.
 */
const props = withDefaults(
  defineProps<{
    url: string | null
    selected?: string | null
    /** Bumped by the host to reload the page. */
    reload?: number
    /**
     * The preview is the screen rather than a column beside one — the shape the panel takes
     * on a phone. It starts as a phone, and its bar carries the way into the blocks, because
     * there is no tree standing next to it to click.
     */
    compact?: boolean
  }>(),
  { selected: null, reload: 0, compact: false },
)

const emit = defineEmits<{
  /** A block was clicked in the page. Null when the click landed outside every block. */
  select: [key: string | null]
  /** The way into the blocks was asked for. Only ever emitted while `compact`. */
  edit: []
}>()

const t = useTranslate('webx-blocks')

const frame = ref<HTMLIFrameElement | null>(null)
/* The bar, measured rather than assumed: it floats over the top of the window, and how much
   of the window it takes is what a block is kept clear of. */
const bar = ref<HTMLElement | null>(null)
const box = ref<HTMLElement | null>(null)
const boxWidth = useElementWidth(box)
const fullscreen = ref(false)
const ready = ref(false)

/*
 * The width asked for by hand, and null for the whole page.
 *
 * Nothing chooses it but the person looking: the preview used to be squeezed into a phone
 * while a block was open, because the column it stood in was 420 px wide and a desktop does
 * not go in there. It is not squeezed any more — the form took the tree's place instead of
 * taking the preview's room — so there is nothing left to guess at.
 */
const picked = ref<number | null>(null)

/*
 * The height of the page inside, so that the frame is exactly as tall as what it shows and
 * nothing scrolls in a box of ours: a block taller than the window is read by scrolling the
 * panel, the way the page it belongs to is read.
 *
 * Zero while it cannot be measured — a page from another origin — and then the frame keeps
 * the window-sized height the stylesheet gives it.
 */
const contentHeight = ref(0)

/**
 * Told how tall to be by the page inside — in every mode, full screen included.
 *
 * Full screen used to be the exception, a window of a fixed height with the page scrolling
 * inside it, and that exception is what made it useless on a phone: a frame that does not
 * scale shows a 1280 px page through a 375 px slot. One rule now — the frame is as tall as
 * its page and as wide as it fits — and who scrolls it is all that differs: the panel in a
 * column, the sheet's own ground in full screen.
 */
const grown = computed(() => contentHeight.value > 0)

/* Words as the accessible name, pictures on screen: three words do not fit a bar this narrow
   and a device is one of the few things a picture says faster than a word anyway. */
const widths = computed(() => [
  { value: 1280, icon: 'monitor', ariaLabel: t('field.width-desktop') },
  { value: 834, icon: 'tablet', ariaLabel: t('field.width-tablet') },
  { value: 390, icon: 'smartphone', ariaLabel: t('field.width-phone') },
])

/**
 * The width nobody asked for: the one that suits the room.
 *
 * Where the preview is the screen, that screen is usually a phone, and a 1280 px page on it
 * is a page nobody can read at the 0.3 the scale would say. A tablet is the same shape of
 * screen with twice the room, and a phone drawn in the middle of it wastes half — so the
 * middle width is the one it starts at. A column beside a tree starts at the whole page.
 */
const width = computed(() => {
  if (picked.value !== null) return picked.value
  if (!props.compact) return 1280

  return boxWidth.value > 0 && boxWidth.value < 560 ? 390 : 834
})

const scale = computed(() => {
  if (boxWidth.value === 0) return 1

  /*
   * Whatever it takes to fit, and never more than one to one. The phone used to be excepted
   * from this, back when the phone was the only narrow width there was and the column it
   * stands in is wider than 390 anyway — the exception said nothing then and lies now, when
   * a tablet can be asked for in that same column.
   */
  return Math.min(1, boxWidth.value / width.value)
})

const frameStyle = computed(() => ({
  width: `${width.value}px`,
  transform: `scale(${scale.value})`,
  ...(grown.value ? { height: `${contentHeight.value}px` } : {}),
}))

/*
 * The scale is a transform, and a transform does not move the layout: the frame keeps the
 * box it was given whatever it is drawn at. So the hole it is drawn through is the one that
 * carries the size the column actually sees — the frame's own, times the scale.
 */
const clipStyle = computed(() => ({
  width: `${Math.round(width.value * scale.value)}px`,
  ...(grown.value ? { height: `${Math.round(contentHeight.value * scale.value)}px` } : {}),
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

/*
 * What the page was bound with, so that a reload lets go of the document it was bound to. The
 * page inside is the site's, not ours, and a listener left on a document nobody can reach any
 * more keeps that whole document alive.
 */
let binding: FrameBinding | null = null

/* Watches the page inside grow and shrink; released with the document it was watching. */
let watcher: ResizeObserver | null = null

/**
 * How tall the page inside is.
 *
 * Off the body, and never off `documentElement.scrollHeight`: that one is never smaller than
 * the frame's own window, so once the frame has been made as tall as the page it stops being
 * a measurement of the page at all — it measures the frame, and a page that gets shorter
 * never gets its height back. The body is the tallest thing the page actually drew, and its
 * margins are part of what it drew.
 */
/**
 * Undo any scrolling of the page inside.
 *
 * A frame as tall as its page shows it from the top and from nowhere else, so a scroll in
 * there is always something that got there by accident — and what it leaves behind is a
 * preview missing its beginning, with blank frame under its end and no way to scroll back,
 * because the wheel over it moves the panel.
 */
function pin(page: Document): void {
  if (page.documentElement.scrollTop !== 0) page.documentElement.scrollTop = 0
}

function measure(): void {
  const page = doc()
  const body = page?.body

  if (!body || !page?.defaultView) return

  pin(page)

  const style = page.defaultView.getComputedStyle(body)
  const margins = (parseFloat(style.marginTop) || 0) + (parseFloat(style.marginBottom) || 0)
  const height = Math.ceil(body.scrollHeight + margins)

  if (height === contentHeight.value) return

  contentHeight.value = height

  /* The frame takes the new height on the next tick, and the panel is laid out again after
     that: a block asked for in between has to be asked for once more. */
  if (pending !== null) void nextTick(() => requestAnimationFrame(bring))
}

function watch_(page: Document): void {
  watcher?.disconnect()

  const view = page.defaultView

  if (!view || !page.body) return

  /* The frame's own `ResizeObserver`, not ours: an observer belongs to the window whose
     elements it watches, and one made out here never fires for a document in there. */
  watcher = new view.ResizeObserver(measure)
  watcher.observe(page.body)
  measure()
}

function onLoad(): void {
  ready.value = true

  const page = doc()

  if (!page) return

  watch_(page)

  binding?.release()
  binding = bindFrame(page, {
    /* No guard against answering a click with a scroll: `bring` moves nothing that is already
       readable, and a block clicked at the very bottom of the window, half under the action
       bar, is exactly the one worth moving. */
    select: (key) => emit('select', key),
  })

  highlightBlock(page, props.selected ?? null)
  reveal(props.selected ?? null)
}

/*
 * The block to bring into view, and the timer that gives up on it.
 *
 * The page inside can still be settling — a width just changed, a picture just arrived — and
 * every such change moves the block and the frame under it. So the ask is kept for as long as
 * the measured height keeps moving rather than answered once, against a layout that is about
 * to change under it.
 */
let pending: string | null = null
let forget: number | null = null

/**
 * Bring the selected block into view.
 *
 * `scrollIntoView` and nothing else: a frame as tall as its page has nothing to scroll, and
 * the browser walks out of it and scrolls the panel — which is the whole point of the frame
 * being that tall. A frame that could not be measured still scrolls in its own window, and
 * the same call does that too.
 */
function reveal(key: string | null): void {
  if (key === null) return

  pending = key

  if (forget !== null) clearTimeout(forget)
  forget = window.setTimeout(() => {
    pending = null
    forget = null
  }, 400)

  bring()
}

/**
 * Where the block stands in the window of the panel, not in the window of the frame.
 *
 * The frame does not scroll — it is as tall as its page — so a rect taken inside it is an
 * offset from the frame's own top, and the scale is a transform, so what the panel sees is
 * that offset times the scale.
 */
function place(el: Element): { top: number; height: number } | null {
  const box = frame.value?.getBoundingClientRect()

  if (!box) return null

  const rect = el.getBoundingClientRect()

  return { top: box.top + rect.top * scale.value, height: rect.height * scale.value }
}

/**
 * What the panel scrolls with: the box the preview stands in, or the window when nothing
 * between them scrolls. Walked rather than assumed, because the screen decides that, not us.
 */
function scroller(): HTMLElement | null {
  let node = frame.value?.parentElement ?? null

  while (node) {
    const overflow = getComputedStyle(node).overflowY

    if ((overflow === 'auto' || overflow === 'scroll') && node.scrollHeight > node.clientHeight) {
      return node
    }

    node = node.parentElement
  }

  return null
}

/**
 * Bring the selected block into view, by as little as it takes and no more.
 *
 * Emphatically **not** `scrollIntoView`, which is what this used to be and what made the
 * preview lose the top of its page: that one scrolls every scrollable ancestor it can find,
 * and the document inside the frame is one of them. When the panel could not move far enough
 * on its own — a short page, a block near its end — the browser made up the difference by
 * scrolling the page *inside* the frame instead, which is the one thing the frame being as
 * tall as its page is there to prevent. The result was a preview starting in the middle of
 * its page with the rest of the frame blank, and no way to scroll it back.
 *
 * So the panel is moved by hand, and nothing else is touched. Only enough to bring the block
 * in, never to centre it. The window is counted a bar short at each end — this preview's own
 * bar floats over the top of it, the screen's action bar over the bottom — and a block too
 * tall to fit between them is put against the top, where reading it starts.
 */
function bring(): void {
  const page = doc()

  if (pending === null || !page) return

  /* Before anything is measured against it: the arithmetic below reads a rect taken inside
     the frame as an offset from the frame's top, which it only is while the page in there
     stands where it was put. */
  pin(page)

  const el = blockElement(page, pending)
  const at = el ? place(el) : null

  if (!at) return

  const box = scroller()
  const rect = box?.getBoundingClientRect()
  const room = bar.value?.getBoundingClientRect().height ?? 0
  const band = {
    top: (rect?.top ?? 0) + room,
    bottom: (rect ? rect.bottom : window.innerHeight) - room,
  }
  const foot = at.top + at.height
  const above = at.top < band.top
  const tall = at.height > band.bottom - band.top
  const delta = above || tall ? at.top - band.top : foot > band.bottom ? foot - band.bottom : 0

  if (delta === 0) return

  ;(box ?? window).scrollBy({ top: delta, behavior: 'smooth' })
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
  watcher?.disconnect()
  watcher = null
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

    if (!page || !ready.value) return

    highlightBlock(page, key ?? null)
    reveal(key ?? null)
  },
)

watch(() => props.reload, refresh)
watch(
  () => props.url,
  () => (ready.value = false),
)

onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey)
  if (forget !== null) clearTimeout(forget)
  watcher?.disconnect()
  binding?.release()
})

defineExpose({ replace, refresh, open: () => (fullscreen.value = true) })
</script>

<template>
  <div
    class="wx-blocks-preview"
    :class="{ 'is-fullscreen': fullscreen, 'is-grown': grown, 'is-compact': compact }"
    :style="{ '--wx-preview-scale': scale }"
  >
    <div ref="bar" class="wx-blocks-preview__bar">
      <wx-text size="xs" tone="muted" class="wx-blocks-preview__label" truncate>{{
        label
      }}</wx-text>
      <wx-segmented
        class="wx-blocks-preview__widths"
        :model-value="width"
        :options="widths"
        size="sm"
        :aria-label="t('field.width')"
        @update:model-value="picked = Number($event)"
      />
      <!-- A plain row and not `WxActions`: two buttons that never collapse into anything, and
           a row that can collapse renders a dropdown beside itself — a second root, which no
           scoped rule of ours would then reach. -->
      <div class="wx-blocks-preview__tools">
        <!-- The way into the blocks, and only where the preview is the whole screen: with a
             tree beside it this would be a second door into the room you are standing in. -->
        <wx-action
          v-if="compact"
          size="sm"
          tone="primary"
          icon="edit"
          :title="t('field.blocks')"
          @click="emit('edit')"
        />
        <wx-action
          v-if="url"
          size="sm"
          icon="external-link"
          tone="neutral"
          :title="t('field.open-site')"
          :href="url"
          target="_blank"
          rel="noopener"
        />
        <wx-action
          size="sm"
          :icon="fullscreen ? 'minimize' : 'maximize'"
          tone="neutral"
          :title="fullscreen ? t('field.close') : t('field.fullscreen')"
          @click="fullscreen = !fullscreen"
        />
      </div>
    </div>
    <div ref="box" class="wx-blocks-preview__ground">
      <div class="wx-blocks-preview__clip" :style="clipStyle">
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
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  /*
   * `clip` and not `hidden`, because of the sticky bar below: `hidden` makes the box a scroll
   * container — one that never scrolls, since nothing here overflows it — and a sticky child
   * sticks to the nearest one of those. It would have been pinned to a box that does not
   * move, which looks exactly like `position: sticky` having no effect at all. `clip` cuts
   * the corners off the frame just the same and leaves the page as the thing that scrolls.
   */
  overflow: clip;
  min-width: 0;
}

/*
 * Over the panel, header and all.
 *
 * It used to carry a bare `40`, which is below everything the panel stacks with — so on a
 * phone, where the preview is the whole screen, it opened *under* the sticky header and its
 * own bar went with it: the way out was behind the burger. The overlay layer is what a sheet
 * covering the page belongs to; toasts still land on top of it, which is right, because a
 * toast is the only thing that has something to say while this is open.
 */
.wx-blocks-preview.is-fullscreen {
  position: fixed;
  inset: 0;
  z-index: var(--wx-z-index-overlay);
  border: 0;
  border-radius: 0;
}

.wx-blocks-preview__bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
  padding: var(--wx-space-8) var(--wx-space-12);
  border-block-end: 1px solid var(--wx-border-muted);
}

/*
 * The bar stays while the page under it goes by — the width, the way out and the address
 * belong to the whole preview, and a preview that is now as tall as its page would otherwise
 * take all three off the screen with the first scroll.
 *
 * Against the top of the window and not at the offset the tree and the form stick at: sticking
 * moves the bar, not the page under it, so an offset leaves a gap that the page inside goes on
 * showing through — twelve pixels of a headline sliding past above the bar. It needs a
 * background of its own for the same reason, the card's not travelling to a child.
 *
 * Never in full screen. There the card is `position: fixed`, so nothing inside it scrolls with
 * the document at all, and the offset would simply push the bar 12px down from where it
 * already is.
 */
.wx-blocks-preview:not(.is-fullscreen) .wx-blocks-preview__bar {
  position: sticky;
  top: 0;
  z-index: 1;
  background: var(--wx-bg-surface);
}

/*
 * Three parts, and the middle one in the middle: the two sides share whatever the widths
 * control leaves, so it sits at the centre of the bar rather than wherever the address
 * happens to end. `flex: 1 1 0` and not `auto` — from zero, so that a long address takes no
 * more room than the buttons opposite it and the control stays put.
 */
.wx-blocks-preview__label {
  flex: 1 1 0;
  min-width: 0;
  font-family: var(--wx-font-family-mono);
}

.wx-blocks-preview__widths {
  flex: none;
}

.wx-blocks-preview__tools {
  display: flex;
  flex: 1 1 0;
  gap: var(--wx-space-4);
  align-items: center;
  justify-content: flex-end;
}

/*
 * The cap and the scrollbar are what is left for a page that cannot be measured — one from
 * another origin. A page that can be is given the frame its own height, and then this box
 * has nothing to scroll: see `is-grown` below.
 */
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
 * No grey either side of the page where the preview is the screen.
 *
 * Twelve pixels at each edge is a frame around a picture on a desktop and a sixteenth of the
 * screen on a phone — and worse than the width it costs is what it does to the eye: the page
 * ends up narrower than the card it is in, which is the same width as the bar and the header,
 * so the one thing the whole screen is about looks like the one thing that is out of line.
 */
.is-compact .wx-blocks-preview__ground {
  padding-inline: 0;
}

/*
 * As tall as the page inside, and the panel scrolls.
 *
 * A window of ours over a page that has its own window is two scrollbars for one document,
 * and the inner one is the one nobody can reach with the wheel over the panel: a block taller
 * than the window could not be seen whole at all. Now there is one scroll, the browser's, and
 * the frame ends where the page does.
 *
 * Full screen keeps its own, because there the sheet is fixed to the window and the page
 * behind it does not move: the ground is the only thing left that can scroll.
 */
.is-grown:not(.is-fullscreen) .wx-blocks-preview__ground {
  overflow: visible;
  max-height: none;
}

.wx-blocks-preview__clip {
  margin-inline: auto;
  overflow: hidden;
  box-shadow: var(--wx-shadow-sm);
}

/* The height here is the fallback for a page that cannot be measured — one from another
   origin. Everything else is told its height in pixels by the measurement. */
.wx-blocks-preview__frame {
  display: block;
  border: 0;
  background: #fff;
  height: min(72vh, 720px);
  transform-origin: top left;
}
</style>
