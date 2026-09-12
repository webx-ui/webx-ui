<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { SELECTABLE, valueOf } from './directive'
import type {
  SelectionAreaEmits,
  SelectionAreaProps,
  SelectionAreaSlotProps,
  SelectionValue,
} from './types'

defineOptions({ name: 'WxSelectionArea' })

const props = withDefaults(defineProps<SelectionAreaProps>(), {
  multiple: true,
  match: 'intersect',
  threshold: 5,
  clickSelect: true,
  touch: false,
  edgeScroll: 48,
  disabled: false,
})

const emit = defineEmits<SelectionAreaEmits>()

const model = defineModel<SelectionValue[]>({ default: () => [] })

defineSlots<{ default?: (props: SelectionAreaSlotProps) => unknown }>()

interface Box {
  left: number
  top: number
  width: number
  height: number
}

interface Candidate extends Box {
  value: SelectionValue
  right: number
  bottom: number
}

/*
 * Anything a pointer already means something to is left alone: a drag that begins on a
 * link, a button or a field is that control's drag, not a selection. `data-wx-no-select`
 * opts a region out by hand — a description a reader needs to copy, say.
 */
const INTERACTIVE =
  'a[href], button, input, select, textarea, label, summary, [contenteditable], [draggable="true"], [data-wx-no-select]'

const root = ref<HTMLElement | null>(null)

const selecting = ref(false)

const box = ref<Box | null>(null)

const selected = computed(() => new Set(model.value))

function isSelected(value: SelectionValue) {
  return selected.value.has(value)
}

/* The drag is plain variables rather than refs: none of it is rendered except the box. */
let pointer = -1
let started = false
/** Whether this gesture is allowed to become a box, or is only ever a click. */
let boxDrag = true
/**
 * What the gesture began on. Read here rather than off the event that ends it: once the
 * pointer is captured every event is retargeted to the area, so the release reports the
 * area itself — which read as a click on the background, and cleared the selection every
 * time a card was clicked. It is also the right question to ask: a click is a press and a
 * release on the same thing.
 */
let downTarget: HTMLElement | null = null
let downX = 0
let downY = 0
let originX = 0
let originY = 0
let atX = 0
let atY = 0
let borderLeft = 0
let borderTop = 0
let candidates: Candidate[] = []
let base: SelectionValue[] = []
let mode: 'replace' | 'add' | 'subtract' = 'replace'
let anchor = -1
let scroller: HTMLElement | null = null
let frame = 0

function items() {
  const el = root.value
  if (!el) return [] as { el: HTMLElement; value: SelectionValue }[]
  const found: { el: HTMLElement; value: SelectionValue }[] = []
  for (const node of el.querySelectorAll<HTMLElement>(`[${SELECTABLE}]`)) {
    const value = valueOf(node)
    if (value !== undefined) found.push({ el: node, value })
  }
  return found
}

/*
 * Everything is worked out in the area's own content coordinates — the distance from the
 * inside of its top left corner, with whatever it is scrolled by added back. Those numbers
 * do not move when anything scrolls, which is why the items can be measured once at the
 * start of a drag and still be right after the box has pulled the list down a screen and a
 * half. It is also the frame the box is drawn in: an absolutely positioned child of a
 * scrolling parent is placed in exactly these coordinates.
 */
function point(clientX: number, clientY: number) {
  const el = root.value
  if (!el) return { x: 0, y: 0 }
  const rect = el.getBoundingClientRect()
  return {
    x: clientX - rect.left - borderLeft + el.scrollLeft,
    y: clientY - rect.top - borderTop + el.scrollTop,
  }
}

function measure() {
  const el = root.value
  if (!el) return
  const style = getComputedStyle(el)
  borderLeft = Number.parseFloat(style.borderLeftWidth) || 0
  borderTop = Number.parseFloat(style.borderTopWidth) || 0
  const rect = el.getBoundingClientRect()
  const left = rect.left + borderLeft - el.scrollLeft
  const top = rect.top + borderTop - el.scrollTop
  candidates = items().map(({ el: node, value }) => {
    const at = node.getBoundingClientRect()
    return {
      value,
      left: at.left - left,
      top: at.top - top,
      right: at.right - left,
      bottom: at.bottom - top,
      width: at.width,
      height: at.height,
    }
  })
}

function hits(at: Box) {
  const right = at.left + at.width
  const bottom = at.top + at.height
  const caught: SelectionValue[] = []
  for (const item of candidates) {
    const inside =
      props.match === 'contain'
        ? item.left >= at.left && item.top >= at.top && item.right <= right && item.bottom <= bottom
        : item.left < right && item.right > at.left && item.top < bottom && item.bottom > at.top
    if (inside) caught.push(item.value)
  }
  return caught
}

/*
 * What the drag started from decides what it does to what was already selected: on its own
 * it replaces it, held with a modifier it adds to it, held with alt it takes away. The
 * selection the drag began with is kept aside so that every move works out from there
 * rather than from the last frame — a box dragged back over its own path has to let items
 * go again.
 */
function resolve(caught: SelectionValue[]) {
  if (mode === 'replace') return caught
  if (mode === 'subtract') {
    const drop = new Set(caught)
    return base.filter((value) => !drop.has(value))
  }
  const have = new Set(base)
  return [...base, ...caught.filter((value) => !have.has(value))]
}

function apply(next: SelectionValue[]) {
  const now = model.value
  if (next.length === now.length && next.every((value, index) => value === now[index])) return
  model.value = next
}

function update() {
  const at = point(atX, atY)
  const next = {
    left: Math.min(at.x, originX),
    top: Math.min(at.y, originY),
    width: Math.abs(at.x - originX),
    height: Math.abs(at.y - originY),
  }
  box.value = next
  apply(resolve(hits(next)))
}

function viewport(el: HTMLElement) {
  if (el === document.scrollingElement) {
    return { left: 0, top: 0, right: window.innerWidth, bottom: window.innerHeight }
  }
  const rect = el.getBoundingClientRect()
  return { left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom }
}

/* The further past the edge the pointer is, the faster it goes. */
function speed(at: number, min: number, max: number, edge: number) {
  const step = 18
  if (at < min + edge) return -Math.ceil(Math.min(1, (min + edge - at) / edge) * step)
  if (at > max - edge) return Math.ceil(Math.min(1, (at - (max - edge)) / edge) * step)
  return 0
}

function scrollerOf(from: HTMLElement) {
  const scrolls = /auto|scroll|overlay/
  let el: HTMLElement | null = from
  while (el) {
    const style = getComputedStyle(el)
    if (
      (scrolls.test(style.overflowY) && el.scrollHeight > el.clientHeight) ||
      (scrolls.test(style.overflowX) && el.scrollWidth > el.clientWidth)
    ) {
      return el
    }
    el = el.parentElement
  }
  return (document.scrollingElement as HTMLElement | null) ?? null
}

/*
 * The frame loop is there for the edge scrolling alone. A box held still against the foot
 * of the list has to keep pulling it up, and a pointer that is not moving sends no events —
 * so where it last was is remembered, and read again every frame.
 */
function tick() {
  frame = requestAnimationFrame(tick)
  if (scroller) {
    const view = viewport(scroller)
    const dx = speed(atX, view.left, view.right, props.edgeScroll)
    const dy = speed(atY, view.top, view.bottom, props.edgeScroll)
    if (dx) scroller.scrollLeft += dx
    if (dy) scroller.scrollTop += dy
  }
  update()
}

function stop() {
  if (frame) cancelAnimationFrame(frame)
  frame = 0
  const el = root.value
  if (el && pointer !== -1 && el.hasPointerCapture?.(pointer)) el.releasePointerCapture(pointer)
  pointer = -1
  started = false
  downTarget = null
  scroller = null
  selecting.value = false
  box.value = null
}

function pick(event: PointerEvent, from: HTMLElement | null) {
  const el = from?.closest(`[${SELECTABLE}]`)
  if (!el) {
    /* The background is what clears a selection — the one gesture every file list shares. */
    if (mode === 'replace') apply([])
    return
  }
  const value = valueOf(el)
  if (value === undefined) return
  const index = candidates.findIndex((item) => item.value === value)

  /*
   * One at a time. The run and the toggle are both ways of ending up holding more than
   * one, so neither is offered: picking is picking, and the background is how you end up
   * holding none.
   */
  if (!props.multiple) {
    anchor = index
    apply([value])
    return
  }

  if (event.shiftKey && anchor >= 0 && index >= 0) {
    const [from, to] = anchor < index ? [anchor, index] : [index, anchor]
    apply(resolve(candidates.slice(from, to + 1).map((item) => item.value)))
    return
  }

  anchor = index

  /*
   * A finger has no ctrl key and no box to draw, so on a touch screen the tap is the only
   * gesture there is — and a tap that replaces the selection can never build one. It adds
   * and removes instead; the background still clears, which is the way back out.
   */
  const toggles = event.ctrlKey || event.metaKey || event.pointerType === 'touch'

  if (toggles) {
    apply(selected.value.has(value) ? base.filter((held) => held !== value) : [...base, value])
    return
  }

  apply([value])
}

function onPointerDown(event: PointerEvent) {
  const el = root.value
  if (props.disabled || !el || event.button !== 0) return
  if ((event.target as HTMLElement).closest(INTERACTIVE)) return

  const isTouch = event.pointerType === 'touch'

  /*
   * A rubber band is a gesture for taking several things, so there is none where only one
   * may be held. And a finger dragged across a list means scroll: without `touch` that
   * gesture is followed but never becomes a box. It is still followed — a tap has to pick
   * the item under it, and bailing out here is how a touch screen came to select nothing.
   */
  boxDrag = props.multiple && (!isTouch || props.touch)

  pointer = event.pointerId
  started = false
  downTarget = event.target as HTMLElement
  downX = event.clientX
  downY = event.clientY
  atX = event.clientX
  atY = event.clientY
  base = [...model.value]
  mode = event.altKey
    ? 'subtract'
    : event.shiftKey || event.ctrlKey || event.metaKey
      ? 'add'
      : 'replace'

  measure()
  const at = point(event.clientX, event.clientY)
  originX = at.x
  originY = at.y

  /* Puts the shortcuts within reach. */
  el.focus({ preventScroll: true })

  /*
   * A finger's gesture belongs to the page unless the box is about to take it. A mouse
   * has nothing to lose either way, so it is captured whether or not a box follows —
   * that is what keeps a press and its release one gesture.
   */
  if (isTouch && !boxDrag) return

  /*
   * Takes the caret out of any text the box crosses — and, on a finger, the scroll out
   * of the page, which is why a tap-only gesture is left alone.
   */
  event.preventDefault()
  /*
   * Capture is what keeps the moves coming once the pointer leaves the area, and it is
   * best effort: a pointer the browser is not tracking — a synthetic event, a test harness —
   * cannot be captured, and the drag is no worse for it while the pointer stays inside.
   */
  try {
    el.setPointerCapture?.(event.pointerId)
  } catch {
    /* No live pointer by that id. */
  }
}

function onPointerMove(event: PointerEvent) {
  if (event.pointerId !== pointer) return
  atX = event.clientX
  atY = event.clientY

  if (!started) {
    if (Math.hypot(event.clientX - downX, event.clientY - downY) < props.threshold) return
    /* Travelled too far to be a tap, and there is no box to turn into: it was a scroll. */
    if (!boxDrag) {
      stop()
      return
    }
    started = true
    selecting.value = true
    emit('start')
    if (props.edgeScroll > 0 && root.value) scroller = scrollerOf(root.value)
    if (scroller) frame = requestAnimationFrame(tick)
  }

  update()
}

function onPointerUp(event: PointerEvent) {
  if (event.pointerId !== pointer) return
  const dragged = started
  /* `stop` forgets where the gesture began, and the click still has to be told. */
  const from = downTarget
  stop()
  if (dragged) emit('end', [...model.value])
  else if (props.clickSelect) pick(event, from)
}

/*
 * The browser taking the gesture over — a finger that turned into a scroll, a palm on the
 * screen. Whatever was drawn is over, but nothing was chosen: a cancelled gesture that
 * picked the item it happened to start on is how a scroll comes to change the selection.
 */
function onPointerCancel(event: PointerEvent) {
  if (event.pointerId !== pointer) return
  const dragged = started
  stop()
  if (dragged) emit('end', [...model.value])
}

function onKeydown(event: KeyboardEvent) {
  if (props.disabled) return

  if (event.key === 'Escape') {
    if (started) {
      apply(base)
      stop()
    } else if (model.value.length) {
      apply([])
    }
    return
  }

  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a') {
    if (!props.multiple) return
    if ((event.target as HTMLElement).closest(INTERACTIVE)) return
    event.preventDefault()
    selectAll()
  }
}

/** Everything there is. Nothing at all where only one may be held. */
function selectAll() {
  if (!props.multiple) return
  apply(items().map((item) => item.value))
}

function clear() {
  apply([])
}

onBeforeUnmount(stop)

defineExpose({ selectAll, clear })
</script>

<template>
  <div
    ref="root"
    class="wx-selection-area"
    :class="{ 'is-selecting': selecting, 'is-disabled': disabled, 'is-touch': touch }"
    tabindex="-1"
    @pointerdown="onPointerDown"
    @pointermove="onPointerMove"
    @pointerup="onPointerUp"
    @pointercancel="onPointerCancel"
    @keydown="onKeydown"
  >
    <slot :selected="selected" :is-selected="isSelected" :selecting="selecting" />

    <div
      v-if="box"
      class="wx-selection-area__box"
      :style="{
        left: `${box.left}px`,
        top: `${box.top}px`,
        width: `${box.width}px`,
        height: `${box.height}px`,
      }"
    />
  </div>
</template>

<style scoped>
.wx-selection-area {
  position: relative;
  /* Its own stacking context, so the box is over its items and under nothing else. */
  isolation: isolate;
}

/* The area is focusable so the shortcuts have somewhere to land, not as a stop on the way. */
.wx-selection-area:focus {
  outline: none;
}

/*
 * A finger drag is the browser's before it is ours: left alone it becomes a scroll and the
 * pointer events stop arriving, which is why box selection worked in a desktop browser's
 * device emulator and did nothing at all on a phone. Claiming the gesture is the price of
 * `touch`, and it is why `touch` is off by default.
 */
.wx-selection-area.is-touch {
  touch-action: none;
}

.wx-selection-area.is-selecting {
  user-select: none;
  -webkit-user-select: none;
}

.wx-selection-area__box {
  position: absolute;
  /* Over the items it is drawn across — within this area, which is a stacking context. */
  z-index: 10;
  background: color-mix(in srgb, var(--wx-color-primary) 12%, transparent);
  border: 1px solid var(--wx-color-primary);
  border-radius: var(--wx-radius-xs);
  pointer-events: none;
}
</style>
