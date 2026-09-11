<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useSlots, watch } from 'vue'
import {
  DialogClose,
  DialogContent,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
  DialogTrigger,
} from 'reka-ui'
import WxIcon from '../Icon/Icon.vue'
import { clamp, cssLength, pixelLength, usePanelMemory } from '../../composables/useOverlayPanel'
import type { DialogEmits, DialogLayout, DialogProps } from './types'

defineOptions({ name: 'WxDialog', inheritAttrs: false })

/*
 * A window over the page: a heading with its actions, a body that scrolls, a row of
 * buttons under it. `WxDrawer` is the same panel anchored to an edge of the screen —
 * reach for that one when what is behind it has to stay visible.
 *
 * What has no appearance of its own — the focus trap, the scroll lock, Escape, the
 * portal, `aria-modal` — comes from Reka's dialog primitive. Everything that can be
 * seen is ours.
 */
const props = withDefaults(defineProps<DialogProps>(), {
  title: undefined,
  width: 520,
  height: undefined,
  minWidth: 320,
  minHeight: 200,
  sidebarWidth: 200,
  closable: true,
  closeLabel: 'Close',
  closeOnOverlay: true,
  closeOnEscape: true,
  overlay: true,
  modal: true,
  draggable: false,
  resizable: false,
  persist: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<DialogEmits>()

defineSlots<{
  /** The control that opens the panel. Exactly one element. */
  trigger?: (props: { open: boolean }) => unknown
  /** The body. */
  default?: (props: { close: () => void }) => unknown
  /** Replaces `title`. */
  title?: () => unknown
  /** Next to the heading — badges, or actions that belong to the whole panel. */
  extra?: (props: { close: () => void }) => unknown
  /** A column beside the body. Its presence is what splits the body in two. */
  sidebar?: (props: { close: () => void }) => unknown
  /** A row under the body — where the Save and Cancel go. */
  footer?: (props: { close: () => void }) => unknown
}>()

const open = defineModel<boolean>('open', { default: false })

const slots = useSlots()

function close() {
  open.value = false
}

const hasTitle = computed(() => Boolean(props.title || slots.title))
const hasHead = computed(() => Boolean(hasTitle.value || slots.extra || props.closable))
const hasSidebar = computed(() => Boolean(slots.sidebar))

/* Reka needs a `DialogTitle` to name the panel; without a visible one it is hidden. */
const accessibleName = computed(() => props.title ?? props.ariaLabel ?? 'Dialog')

/** Pixels the panel was last left at. Empty until it is dragged, resized or restored. */
const layout = ref<DialogLayout>({})

const memory = usePanelMemory<DialogLayout>('wx-dialog:', () => props.persist)

/*
 * Sizes travel as custom properties rather than as `width` and `height`: the small-screen
 * rules then override them by being a later rule, instead of having to out-shout an
 * inline style with `!important`.
 */
const panelVars = computed(() => {
  const vars: Record<string, string> = {}

  const width = layout.value.width != null ? `${layout.value.width}px` : cssLength(props.width)
  if (width) vars['--wx-dialog-width'] = width

  const height = layout.value.height != null ? `${layout.value.height}px` : cssLength(props.height)
  if (height) vars['--wx-dialog-height'] = height

  if (layout.value.x) vars['--wx-dialog-x'] = `${layout.value.x}px`
  if (layout.value.y) vars['--wx-dialog-y'] = `${layout.value.y}px`

  const sidebar = cssLength(props.sidebarWidth)
  if (sidebar) vars['--wx-dialog-sidebar-width'] = sidebar

  return vars
})

const panel = ref<HTMLElement | null>(null)
const dragging = ref(false)
const resizing = ref(false)

/** The panel as it stood when the pointer went down, so every move is measured once. */
interface Gesture {
  kind: 'move' | 'resize'
  pointerX: number
  pointerY: number
  left: number
  top: number
  width: number
  height: number
  x: number
  y: number
}

let gesture: Gesture | null = null

/** Room left around the panel when a resize runs into the edge of the screen. */
const GUTTER = 32

function begin(kind: 'move' | 'resize', event: PointerEvent) {
  /*
   * Touch is left out on purpose: a finger dragging the heading is a finger not
   * scrolling, and on a screen that small the panel fills it anyway.
   */
  if (event.button !== 0 || event.pointerType === 'touch') return

  const element = panel.value
  if (!element) return

  const rect = element.getBoundingClientRect()

  gesture = {
    kind,
    pointerX: event.clientX,
    pointerY: event.clientY,
    left: rect.left,
    top: rect.top,
    /* A panel sized in per cent has no pixels of its own until it is measured. */
    width: rect.width || layout.value.width || pixelLength(props.width) || props.minWidth,
    height: rect.height || layout.value.height || pixelLength(props.height) || props.minHeight,
    x: layout.value.x ?? 0,
    y: layout.value.y ?? 0,
  }

  if (kind === 'move') dragging.value = true
  else resizing.value = true

  window.addEventListener('pointermove', move)
  window.addEventListener('pointerup', end)
  window.addEventListener('pointercancel', end)
  event.preventDefault()
}

function startDrag(event: PointerEvent) {
  if (!props.draggable) return
  /* The heading holds the × and whatever `extra` put there; those keep their clicks. */
  const target = event.target as HTMLElement | null
  if (target?.closest('button, a, input, select, textarea, [contenteditable]')) return
  begin('move', event)
}

function startResize(event: PointerEvent) {
  if (!props.resizable) return
  begin('resize', event)
}

function move(event: PointerEvent) {
  if (!gesture) return

  const dx = event.clientX - gesture.pointerX
  const dy = event.clientY - gesture.pointerY

  if (gesture.kind === 'move') {
    /* Held by its own edges, so the panel cannot be dragged off the screen. */
    const left = clamp(gesture.left + dx, 0, window.innerWidth - gesture.width)
    const top = clamp(gesture.top + dy, 0, window.innerHeight - gesture.height)

    layout.value = {
      ...layout.value,
      x: gesture.x + (left - gesture.left),
      y: gesture.y + (top - gesture.top),
    }
    return
  }

  const width = clamp(gesture.width + dx, props.minWidth, window.innerWidth - GUTTER)
  const height = clamp(gesture.height + dy, props.minHeight, window.innerHeight - GUTTER)

  /*
   * The panel is centred, so it grows by half in each direction. Shifting it by that
   * half pins the top-left corner and leaves only the one under the pointer moving.
   */
  layout.value = {
    width,
    height,
    x: gesture.x + (width - gesture.width) / 2,
    y: gesture.y + (height - gesture.height) / 2,
  }
}

function stopListening() {
  window.removeEventListener('pointermove', move)
  window.removeEventListener('pointerup', end)
  window.removeEventListener('pointercancel', end)
}

function end() {
  if (!gesture) return

  gesture = null
  dragging.value = false
  resizing.value = false
  stopListening()

  memory.write(layout.value)
  emit('layout', { ...layout.value })
}

/** Back to the declared size, in the middle of the screen, and forgotten. */
function reset() {
  layout.value = {}
  memory.clear()
}

function restore() {
  const saved = memory.read()
  if (!saved) return

  const next: DialogLayout = {}
  for (const key of ['width', 'height', 'x', 'y'] as const) {
    const value = saved[key]
    if (typeof value === 'number' && Number.isFinite(value)) next[key] = value
  }
  layout.value = next
}

/*
 * After mount rather than during setup: this renders on a server too, and a panel sized
 * from one machine's `localStorage` would not match the markup the browser hydrates.
 */
onMounted(restore)
onBeforeUnmount(stopListening)

watch(open, (value) => {
  if (value) {
    emit('open')
    return
  }

  emit('close')
  /* Without a key to remember it by, every opening starts from the declared size. */
  if (!props.persist) layout.value = {}
})

function onEscape(event: Event) {
  if (!props.closeOnEscape) event.preventDefault()
}

function onInteractOutside(event: Event) {
  if (!props.closeOnOverlay) event.preventDefault()
}

/*
 * The wrapper covers the screen, so a click beside the panel is a click inside the
 * dialog as far as Reka is concerned — and in modal mode it gives the wrapper its own
 * `pointer-events: auto`, which the overlay underneath can then never be handed. The
 * space around the panel is therefore closed here, by the element that receives it.
 */
function onViewportPointerDown() {
  if (props.closeOnOverlay) close()
}

defineExpose({ close, reset })
</script>

<template>
  <dialog-root v-model:open="open" :modal="modal">
    <!--
      `as-child`: the trigger is whatever the caller already has — a button, a menu item,
      a row in a table — and wrapping it in a button of ours would nest one inside another.
    -->
    <dialog-trigger v-if="$slots.trigger" as-child>
      <slot name="trigger" :open="open" />
    </dialog-trigger>

    <dialog-portal>
      <dialog-overlay v-if="overlay" class="wx-dialog__overlay" />

      <!--
        The content element covers the screen: it centres the panel and gives it room to
        be dragged in. `aria-describedby` is cleared because there is no description —
        Reka warns about a missing one otherwise.
      -->
      <dialog-content
        class="wx-dialog__viewport"
        :aria-describedby="undefined"
        @escape-key-down="onEscape"
        @interact-outside="onInteractOutside"
        @mousedown.self="onViewportPointerDown"
      >
        <div
          ref="panel"
          v-bind="$attrs"
          class="wx-dialog"
          :class="{
            'wx-dialog--draggable': draggable,
            'wx-dialog--dragging': dragging,
            'wx-dialog--resizing': resizing,
            'wx-dialog--split': hasSidebar,
          }"
          :style="panelVars"
        >
          <header v-if="hasHead" class="wx-dialog__head" @pointerdown="startDrag">
            <dialog-title class="wx-dialog__title" :class="{ 'wx-sr-only': !hasTitle }">
              <slot name="title">{{ hasTitle ? title : accessibleName }}</slot>
            </dialog-title>

            <div v-if="$slots.extra" class="wx-dialog__extra">
              <slot name="extra" :close="close" />
            </div>

            <dialog-close v-if="closable" class="wx-dialog__close" :aria-label="closeLabel">
              <wx-icon name="close" />
            </dialog-close>
          </header>

          <dialog-title v-else class="wx-sr-only">{{ accessibleName }}</dialog-title>

          <div class="wx-dialog__body">
            <aside v-if="hasSidebar" class="wx-dialog__sidebar">
              <slot name="sidebar" :close="close" />
            </aside>

            <div class="wx-dialog__content">
              <slot :close="close" />
            </div>
          </div>

          <footer v-if="$slots.footer" class="wx-dialog__foot">
            <slot name="footer" :close="close" />
          </footer>

          <!-- Pointer-only, and hidden with it: a corner this size cannot be hit by a finger. -->
          <div
            v-if="resizable"
            class="wx-dialog__grip"
            aria-hidden="true"
            @pointerdown="startResize"
          />
        </div>
      </dialog-content>
    </dialog-portal>
  </dialog-root>
</template>

<style>
/* The panel is teleported, so its styles cannot be scoped to the component. */
.wx-dialog__overlay {
  position: fixed;
  inset: 0;
  z-index: var(--wx-z-index-overlay);
  background: var(--wx-bg-overlay);
}

.wx-dialog__viewport {
  position: fixed;
  inset: 0;
  z-index: var(--wx-z-index-dialog);
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  padding: var(--wx-space-16);
  /*
   * The wrapper only places the panel. Left to itself it would swallow every click
   * beside it, so it lets them through to the overlay — except in modal mode, where Reka
   * writes `pointer-events: auto` back onto it and the wrapper closes the dialog itself.
   */
  pointer-events: none;
}

/* Reka focuses the content when it opens; the ring belongs on what is inside it. */
.wx-dialog__viewport:focus,
.wx-dialog__viewport:focus-visible {
  outline: none;
}

.wx-dialog {
  position: relative;
  pointer-events: auto;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  width: min(var(--wx-dialog-width, 520px), 100%);
  height: var(--wx-dialog-height, auto);
  max-height: 100%;
  translate: var(--wx-dialog-x, 0) var(--wx-dialog-y, 0);
  background: var(--wx-bg-surface);
  border-radius: var(--wx-radius-md);
  box-shadow: var(--wx-shadow-dialog);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-md);
  line-height: var(--wx-font-line-height-normal);
}

.wx-dialog__head {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  padding: var(--wx-space-14) var(--wx-space-18);
  border-bottom: 1px solid var(--wx-border-muted);
}

.wx-dialog--draggable .wx-dialog__head {
  cursor: grab;
  user-select: none;
}

.wx-dialog--dragging .wx-dialog__head {
  cursor: grabbing;
}

/* A gesture that strays over the body would otherwise paint it blue as it goes. */
.wx-dialog--dragging,
.wx-dialog--resizing {
  user-select: none;
}

.wx-dialog__title {
  flex: 1 1 auto;
  min-width: 0;
  margin: 0;
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-lg);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
}

.wx-dialog__extra {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  color: var(--wx-text-muted);
}

.wx-dialog__close {
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  /* Pulled into the padding so the × sits in the corner, not inside the text block. */
  margin-right: -6px;
  padding: 0;
  background: none;
  border: none;
  border-radius: var(--wx-radius-full);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-md);
  cursor: pointer;
}

.wx-dialog__close:hover {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}

.wx-dialog__close:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

/*
 * The body is the only part that scrolls, so the heading and the buttons stay put however
 * long the content is. Split in two, each column scrolls on its own instead.
 */
.wx-dialog__body {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  overflow: hidden;
}

.wx-dialog__content {
  flex: 1 1 auto;
  min-width: 0;
  overflow: auto;
  padding: var(--wx-space-18);
}

.wx-dialog__sidebar {
  flex: 0 0 auto;
  width: var(--wx-dialog-sidebar-width, 200px);
  overflow: auto;
  padding: var(--wx-space-16);
  background: var(--wx-bg-subtle);
  border-right: 1px solid var(--wx-border-muted);
  border-bottom-left-radius: var(--wx-radius-md);
}

.wx-dialog__foot {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--wx-space-8);
  padding: var(--wx-space-14) var(--wx-space-18);
  border-top: 1px solid var(--wx-border-muted);
}

.wx-dialog__grip {
  position: absolute;
  right: 0;
  bottom: 0;
  width: 20px;
  height: 20px;
  cursor: nwse-resize;
  touch-action: none;
}

.wx-dialog__grip::after {
  content: '';
  position: absolute;
  right: 5px;
  bottom: 5px;
  width: 7px;
  height: 7px;
  border-right: 2px solid var(--wx-border-strong);
  border-bottom: 2px solid var(--wx-border-strong);
  opacity: 0.5;
}

.wx-dialog__grip:hover::after {
  opacity: 1;
}

@keyframes wx-dialog-in {
  from {
    opacity: 0;
    transform: translateY(8px) scale(0.97);
  }
}

@keyframes wx-dialog-out {
  to {
    opacity: 0;
    transform: scale(0.98);
  }
}

@keyframes wx-dialog-fade-in {
  from {
    opacity: 0;
  }
}

@keyframes wx-dialog-fade-out {
  to {
    opacity: 0;
  }
}

/*
 * The wrapper is what animates, not the panel inside it: Reka waits for the animation on
 * the element it owns before unmounting, and a centred panel scaled from the middle of
 * the screen is a panel scaled from its own middle.
 */
.wx-dialog__viewport[data-state='open'] {
  animation: wx-dialog-in var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-dialog__viewport[data-state='closed'] {
  animation: wx-dialog-out var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-dialog__overlay[data-state='open'] {
  animation: wx-dialog-fade-in var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-dialog__overlay[data-state='closed'] {
  animation: wx-dialog-fade-out var(--wx-duration-fast) var(--wx-easing-standard);
}

/*
 * Small screens: the panel takes what there is, and the remembered size and position go
 * with it — a dialog dragged into a corner on a desktop must not open half off a phone.
 */
@media (max-width: 640px) {
  .wx-dialog__viewport {
    padding: var(--wx-space-8);
  }

  .wx-dialog {
    width: 100%;
    height: auto;
    translate: none;
  }

  .wx-dialog--split .wx-dialog__body {
    flex-direction: column;
    overflow: auto;
  }

  .wx-dialog--split .wx-dialog__sidebar,
  .wx-dialog--split .wx-dialog__content {
    overflow: visible;
  }

  .wx-dialog__sidebar {
    width: auto;
    border-right: none;
    border-bottom: 1px solid var(--wx-border-muted);
    border-radius: 0;
  }
}

/* Nothing here can be aimed at with a finger. */
@media (pointer: coarse) {
  .wx-dialog__grip {
    display: none;
  }

  .wx-dialog--draggable .wx-dialog__head {
    cursor: default;
  }
}

@media (prefers-reduced-motion: reduce) {
  .wx-dialog__viewport,
  .wx-dialog__overlay {
    animation: none;
  }
}
</style>
