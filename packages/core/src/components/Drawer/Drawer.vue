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
import type { DrawerEmits, DrawerLayout, DrawerProps } from './types'

defineOptions({ name: 'WxDrawer', inheritAttrs: false })

/*
 * The same panel as `WxDialog` — heading, body, footer — anchored to an edge of the
 * screen instead of floating in the middle of it. It is the one to reach for when the
 * page behind it is the point: a record opened beside the table it came from.
 *
 * The dialog primitive underneath is Reka's, so the focus trap, the scroll lock, Escape
 * and `aria-modal` are the same as everywhere else. What can be seen is ours.
 */
const props = withDefaults(defineProps<DrawerProps>(), {
  title: undefined,
  side: 'right',
  size: 380,
  minSize: 280,
  sidebarWidth: 200,
  closable: true,
  closeLabel: 'Close',
  closeOnOverlay: true,
  closeOnEscape: true,
  overlay: true,
  modal: true,
  resizable: false,
  persist: undefined,
  ariaLabel: undefined,
  resizeLabel: 'Resize the panel',
})

const emit = defineEmits<DrawerEmits>()

defineSlots<{
  /** The control that opens the panel. Exactly one element. */
  trigger?: (props: { open: boolean }) => unknown
  /** The body. The one part of the panel that scrolls. */
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
const accessibleName = computed(() => props.title ?? props.ariaLabel ?? 'Panel')

/** Which way the panel grows: across the screen, or down it. */
const horizontal = computed(() => props.side === 'left' || props.side === 'right')

/** Pixels the panel was last left at. Empty until it is resized or restored. */
const layout = ref<DrawerLayout>({})

const memory = usePanelMemory<DrawerLayout>('wx-drawer:', () => props.persist)

/*
 * The size travels as a custom property rather than as `width` or `height`: the
 * small-screen rule then takes the panel full-screen by being a later rule, instead of
 * having to out-shout an inline style with `!important`.
 */
const panelVars = computed(() => {
  const vars: Record<string, string> = {}

  const size = layout.value.size != null ? `${layout.value.size}px` : cssLength(props.size)
  if (size) vars['--wx-drawer-size'] = size

  const sidebar = cssLength(props.sidebarWidth)
  if (sidebar) vars['--wx-drawer-sidebar-width'] = sidebar

  return vars
})

const resizing = ref(false)

/** Room left between the panel and the far edge of the screen. */
const GUTTER = 32

/** The panel as it stood when the gesture began, so every move is measured once. */
let gesture: { pointerX: number; pointerY: number; size: number } | null = null

/**
 * The panel around the handle. Taken from the event rather than from a template ref: the
 * panel is a Reka component, and what a ref on one holds is the component, not the box
 * that has to be measured.
 */
function panelOf(event: Event): HTMLElement | null {
  return (event.currentTarget as HTMLElement | null)?.closest('.wx-drawer') ?? null
}

/** What the panel measures now, falling back to what it was told to be. */
function currentSize(element: HTMLElement | null): number {
  const rect = element?.getBoundingClientRect()
  const measured = horizontal.value ? rect?.width : rect?.height
  /* A panel sized in per cent has no pixels of its own until it is measured. */
  return measured || layout.value.size || pixelLength(props.size) || props.minSize
}

function maxSize(): number {
  const room = horizontal.value ? window.innerWidth : window.innerHeight
  return room - GUTTER
}

/** Pointer movement, turned into a size: every side grows towards the middle. */
function sizeFrom(startSize: number, dx: number, dy: number): number {
  const delta =
    props.side === 'right' ? -dx : props.side === 'left' ? dx : props.side === 'top' ? dy : -dy
  return clamp(startSize + delta, props.minSize, maxSize())
}

function startResize(event: PointerEvent) {
  /*
   * Touch is left out on purpose: a handle this narrow cannot be aimed at with a finger,
   * and on a screen that small the panel covers everything anyway.
   */
  if (!props.resizable || event.button !== 0 || event.pointerType === 'touch') return

  gesture = { pointerX: event.clientX, pointerY: event.clientY, size: currentSize(panelOf(event)) }
  resizing.value = true

  window.addEventListener('pointermove', move)
  window.addEventListener('pointerup', end)
  window.addEventListener('pointercancel', end)
  event.preventDefault()
}

function move(event: PointerEvent) {
  if (!gesture) return

  layout.value = {
    size: sizeFrom(
      gesture.size,
      event.clientX - gesture.pointerX,
      event.clientY - gesture.pointerY,
    ),
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
  resizing.value = false
  stopListening()
  store()
}

/** The same resize from the keyboard, which is the only way to reach it without a mouse. */
const STEP = 24

function onHandleKeydown(event: KeyboardEvent) {
  if (!props.resizable) return

  const towards = horizontal.value
    ? { ArrowLeft: -STEP, ArrowRight: STEP }
    : { ArrowUp: -STEP, ArrowDown: STEP }
  const delta = (towards as Record<string, number | undefined>)[event.key]
  if (delta === undefined) return

  event.preventDefault()
  const from = currentSize(panelOf(event))
  layout.value = {
    size: sizeFrom(from, horizontal.value ? delta : 0, horizontal.value ? 0 : delta),
  }
  store()
}

function store() {
  memory.write(layout.value)
  emit('layout', { ...layout.value })
}

/** Back to the declared size, and forgotten. */
function reset() {
  layout.value = {}
  memory.clear()
}

function restore() {
  const saved = memory.read()
  if (!saved) return
  if (typeof saved.size === 'number' && Number.isFinite(saved.size)) {
    layout.value = { size: saved.size }
  }
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
      <dialog-overlay v-if="overlay" class="wx-drawer__overlay" />

      <!--
        Here the content element is the panel itself — nothing has to be centred, and it
        is the element Reka slides in. `aria-describedby` is cleared because there is no
        description; Reka warns about a missing one otherwise.
      -->
      <dialog-content
        v-bind="$attrs"
        class="wx-drawer"
        :class="[
          `wx-drawer--${side}`,
          { 'wx-drawer--resizing': resizing, 'wx-drawer--split': hasSidebar },
        ]"
        :style="panelVars"
        :aria-describedby="undefined"
        @escape-key-down="onEscape"
        @interact-outside="onInteractOutside"
      >
        <header v-if="hasHead" class="wx-drawer__head">
          <dialog-title class="wx-drawer__title" :class="{ 'wx-sr-only': !hasTitle }">
            <slot name="title">{{ hasTitle ? title : accessibleName }}</slot>
          </dialog-title>

          <div v-if="$slots.extra" class="wx-drawer__extra">
            <slot name="extra" :close="close" />
          </div>

          <dialog-close v-if="closable" class="wx-drawer__close" :aria-label="closeLabel">
            <wx-icon name="close" />
          </dialog-close>
        </header>

        <dialog-title v-else class="wx-sr-only">{{ accessibleName }}</dialog-title>

        <div class="wx-drawer__body">
          <aside v-if="hasSidebar" class="wx-drawer__sidebar">
            <slot name="sidebar" :close="close" />
          </aside>

          <div class="wx-drawer__content">
            <slot :close="close" />
          </div>
        </div>

        <footer v-if="$slots.footer" class="wx-drawer__foot">
          <slot name="footer" :close="close" />
        </footer>

        <!--
          A separator rather than a decoration: it takes focus and answers the arrow keys,
          which is the only way to resize the panel without a pointer.
        -->
        <div
          v-if="resizable"
          class="wx-drawer__handle"
          role="separator"
          tabindex="0"
          :aria-label="resizeLabel"
          :aria-orientation="horizontal ? 'vertical' : 'horizontal'"
          @pointerdown="startResize"
          @keydown="onHandleKeydown"
        />
      </dialog-content>
    </dialog-portal>
  </dialog-root>
</template>

<style>
/* The panel is teleported, so its styles cannot be scoped to the component. */
.wx-drawer__overlay {
  position: fixed;
  inset: 0;
  z-index: var(--wx-z-index-overlay);
  background: var(--wx-bg-overlay);
}

.wx-drawer {
  /* One set of paddings for the three parts, so the small screens can halve them once. */
  --wx-drawer-pad-x: var(--wx-space-18);
  --wx-drawer-pad-y: var(--wx-space-14);
  --wx-drawer-body-pad: var(--wx-space-18);
  position: fixed;
  z-index: var(--wx-z-index-dialog);
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  background: var(--wx-bg-surface);
  box-shadow: var(--wx-shadow-dialog);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-md);
  line-height: var(--wx-font-line-height-normal);
}

.wx-drawer:focus,
.wx-drawer:focus-visible {
  outline: none;
}

/*
 * `dvh` rather than `vh`: on a phone the address bar comes and goes, and a panel measured
 * against the taller of the two would keep its footer under it.
 */
.wx-drawer--left,
.wx-drawer--right {
  top: 0;
  height: 100dvh;
  width: min(var(--wx-drawer-size, 380px), 100%);
}

.wx-drawer--right {
  right: 0;
  border-left: 1px solid var(--wx-border-default);
}

.wx-drawer--left {
  left: 0;
  border-right: 1px solid var(--wx-border-default);
}

.wx-drawer--top,
.wx-drawer--bottom {
  left: 0;
  width: 100%;
  height: min(var(--wx-drawer-size, 380px), 100dvh);
}

.wx-drawer--top {
  top: 0;
  border-bottom: 1px solid var(--wx-border-default);
}

.wx-drawer--bottom {
  bottom: 0;
  border-top: 1px solid var(--wx-border-default);
}

.wx-drawer__head {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  padding: var(--wx-drawer-pad-y) var(--wx-drawer-pad-x);
  border-bottom: 1px solid var(--wx-border-muted);
}

.wx-drawer__title {
  flex: 1 1 auto;
  min-width: 0;
  margin: 0;
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-lg);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
}

.wx-drawer__extra {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  color: var(--wx-text-muted);
}

.wx-drawer__close {
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

.wx-drawer__close:hover {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}

.wx-drawer__close:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

/*
 * The body is the only part that scrolls: the heading and the buttons stay where they are
 * however long the content is. Split in two, each column scrolls on its own instead.
 */
.wx-drawer__body {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  overflow: hidden;
}

.wx-drawer__content {
  flex: 1 1 auto;
  min-width: 0;
  overflow: auto;
  padding: var(--wx-drawer-body-pad);
}

.wx-drawer__sidebar {
  flex: 0 0 auto;
  width: var(--wx-drawer-sidebar-width, 200px);
  overflow: auto;
  padding: var(--wx-drawer-pad-y) var(--wx-drawer-pad-x);
  background: var(--wx-bg-subtle);
  border-right: 1px solid var(--wx-border-muted);
}

.wx-drawer__foot {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--wx-space-8);
  padding: var(--wx-drawer-pad-y) var(--wx-drawer-pad-x);
  border-top: 1px solid var(--wx-border-muted);
}

/* The handle sits on the edge the panel faces the page with. */
.wx-drawer__handle {
  position: absolute;
  touch-action: none;
  background: transparent;
  transition: background var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-drawer__handle:hover,
.wx-drawer--resizing .wx-drawer__handle {
  background: var(--wx-color-primary-soft);
}

.wx-drawer__handle:focus-visible {
  outline: none;
  background: var(--wx-color-primary);
}

.wx-drawer--right .wx-drawer__handle,
.wx-drawer--left .wx-drawer__handle {
  top: 0;
  bottom: 0;
  width: 6px;
  cursor: col-resize;
}

.wx-drawer--right .wx-drawer__handle {
  left: 0;
}

.wx-drawer--left .wx-drawer__handle {
  right: 0;
}

.wx-drawer--top .wx-drawer__handle,
.wx-drawer--bottom .wx-drawer__handle {
  left: 0;
  right: 0;
  height: 6px;
  cursor: row-resize;
}

.wx-drawer--top .wx-drawer__handle {
  bottom: 0;
}

.wx-drawer--bottom .wx-drawer__handle {
  top: 0;
}

/*
 * One pair of keyframes for all four sides: the side sets where the panel sits when it is
 * off-screen, and `translate` is a property of its own, so nothing here collides with a
 * `transform` set elsewhere.
 */
.wx-drawer--right {
  --wx-drawer-from: 100% 0;
}

.wx-drawer--left {
  --wx-drawer-from: -100% 0;
}

.wx-drawer--top {
  --wx-drawer-from: 0 -100%;
}

.wx-drawer--bottom {
  --wx-drawer-from: 0 100%;
}

@keyframes wx-drawer-in {
  from {
    translate: var(--wx-drawer-from);
  }
}

@keyframes wx-drawer-out {
  to {
    translate: var(--wx-drawer-from);
  }
}

@keyframes wx-drawer-fade-in {
  from {
    opacity: 0;
  }
}

@keyframes wx-drawer-fade-out {
  to {
    opacity: 0;
  }
}

.wx-drawer[data-state='open'] {
  animation: wx-drawer-in var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-drawer[data-state='closed'] {
  animation: wx-drawer-out var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-drawer__overlay[data-state='open'] {
  animation: wx-drawer-fade-in var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-drawer__overlay[data-state='closed'] {
  animation: wx-drawer-fade-out var(--wx-duration-normal) var(--wx-easing-standard);
}

/*
 * Small screens: a panel that leaves a strip of the page visible is a panel too narrow to
 * work in, so it takes the whole screen — the remembered size with it.
 */
@media (max-width: 640px) {
  .wx-drawer {
    /* Tighter than on a desktop: every pixel of padding is a line of content lost. */
    --wx-drawer-pad-x: var(--wx-space-12);
    --wx-drawer-pad-y: var(--wx-space-10);
    --wx-drawer-body-pad: var(--wx-space-12);
    inset: 0;
    width: 100%;
    height: 100dvh;
    border: none;
  }

  .wx-drawer__head {
    gap: var(--wx-space-8);
  }

  .wx-drawer--split .wx-drawer__body {
    flex-direction: column;
    overflow: auto;
  }

  .wx-drawer--split .wx-drawer__sidebar,
  .wx-drawer--split .wx-drawer__content {
    overflow: visible;
  }

  .wx-drawer__sidebar {
    width: auto;
    border-right: none;
    border-bottom: 1px solid var(--wx-border-muted);
  }

  .wx-drawer__handle {
    display: none;
  }
}

/* A handle six pixels wide cannot be aimed at with a finger. */
@media (pointer: coarse) {
  .wx-drawer__handle {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .wx-drawer,
  .wx-drawer__overlay {
    animation: none;
  }
}
</style>
