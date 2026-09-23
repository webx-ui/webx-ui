<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { ActionBarProps } from './types'

defineOptions({ name: 'WxActionBar' })

/**
 * The row of a screen's own actions, along the bottom.
 *
 * A screen's buttons live in its head, and the head scrolls away with everything else — so a
 * form long enough to need saving is a form whose save button is off the top of the window by
 * the time it is needed. This is that button, brought back: the state of the work on the left,
 * what can be done about it on the right.
 *
 * It is part of the screen rather than of the shell — a list has none, a form has one — and it
 * is in the flow, as the last row of the screen, rather than laid over it. That is the whole of
 * why it never covers anything: at the end of a page it is the last thing on it, and above that
 * it sticks to the bottom of the window without taking the room it would need to be there.
 */
const props = withDefaults(defineProps<ActionBarProps>(), {
  sticky: true,
  bordered: true,
})

const classes = computed(() => [
  'wx-action-bar',
  {
    'wx-action-bar--sticky': props.sticky,
    'wx-action-bar--bordered': props.bordered,
  },
])

/*
 * How much of the bottom of the window the bar takes while it sticks there, told to the screen
 * it sits in as `--wx-action-bar-room`.
 *
 * Anything else in that screen that stands still against the window — the column of blocks
 * beside the page preview — would otherwise run its foot under the bar, and its own scroll with
 * it: the last field of a long form was out of reach behind the buttons. The bar's height is
 * whatever its buttons and its wrapped state make it (59.6px measured, not the 56 its minimum
 * says), so it is measured rather than assumed. It is written on the parent, the one element
 * that is sure to be above both the bar and whatever reads it.
 */
const root = ref<HTMLElement | null>(null)
let observer: ResizeObserver | null = null
let host: HTMLElement | null = null

function publish() {
  if (!root.value || !host) return
  const bottom = Number.parseFloat(getComputedStyle(root.value).bottom) || 0
  host.style.setProperty(
    '--wx-action-bar-room',
    `${root.value.getBoundingClientRect().height + bottom}px`,
  )
}

function release() {
  observer?.disconnect()
  observer = null
  host?.style.removeProperty('--wx-action-bar-room')
  host = null
}

function track() {
  release()
  if (!props.sticky || !root.value?.parentElement || typeof ResizeObserver === 'undefined') {
    return
  }
  host = root.value.parentElement
  observer = new ResizeObserver(publish)
  observer.observe(root.value)
  publish()
}

onMounted(track)
watch(() => props.sticky, track, { flush: 'post' })
onBeforeUnmount(release)
</script>

<template>
  <div ref="root" :class="classes">
    <div class="wx-action-bar__state">
      <slot name="state" />
    </div>

    <div class="wx-action-bar__actions">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.wx-action-bar {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  /*
   * Two lines rather than one, once the buttons no longer leave the state room to be read.
   *
   * Without this there is nowhere for the state to go once the buttons have taken the width,
   * and what it does instead is overflow them: measured on a 375px screen with "Saved · goes
   * out on 25 September at 17:06" beside two buttons, the words ran over the top of "Save
   * draft". Nothing about that looks like a layout that ran out of room; it looks like the bar
   * is broken. The `min-width` on the state box below is the other half of the same fix.
   */
  flex-wrap: wrap;
  box-sizing: border-box;
  /* Never squeezed by a screen that is exactly as tall as the window: the bar keeps its
     height and the content above it is what shrinks. */
  flex: none;
  /* Down at the bottom of whatever room the screen has, rather than directly under the last
     card: in a flex column an auto margin eats the free space, and a screen kept at least as
     tall as its column has free space to give whenever the form is short. Anywhere else it
     computes to nothing. */
  margin-block-start: auto;
  min-height: var(--wx-action-bar-height, 56px);
  padding: var(--wx-space-8) var(--wx-space-12);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

.wx-action-bar--bordered {
  border: 1px solid var(--wx-border-default);
}

/*
 * A floating card, and it casts the shadow of one, all the way round.
 *
 * It used to paint a strip of the body colour in the gap it keeps above the bottom of the
 * window, so that the page scrolling past did not show through. That strip is gone. An
 * element's outer shadow is painted with its background, before any of its descendants, so a
 * pseudo-element under the bar erased the part of the shadow that fell there — a flat band
 * with a stub of it left showing at each corner — and no stacking order fixes that, because a
 * descendant is always on top of its ancestor's shadow.
 *
 * What is seen through the gap instead is a sliver of the page still moving, which is honest:
 * the bar is floating over a page that scrolls, and that is what it looks like.
 */
.wx-action-bar--sticky {
  position: sticky;
  bottom: var(--wx-action-bar-bottom, 0px);
  z-index: var(--wx-z-index-sticky);
  box-shadow: var(--wx-shadow-card);
}

/* The left side is whatever the screen says about the state of the work — saved, a draft,
   a version number. It gives way as the row narrows, down to the width of a short sentence;
   past that the buttons take a line of their own rather than the words giving way to nothing. */
/*
 * As wide as what it holds, and never narrower than the longest word in it.
 *
 * It used to ask for 220px whatever it was given — which is right for a sentence and wrong for
 * everything else: a state that is one small mark took a line of its own on a phone and left an
 * empty strip above the buttons, because 220 plus two buttons does not fit 375. The basis is
 * the content now, so a mark sits beside the buttons and a sentence still pushes itself onto
 * the next line the moment it no longer fits.
 *
 * `min-width` is what the sentence is saved by, and it replaces the `0` that used to be here:
 * at zero the box was shrunk to nothing while its words went on being painted where the box no
 * longer was, straight across the buttons — measured on a 375px screen, the box 0 wide and the
 * words over the top of "Save draft". At `min-content` it cannot be shrunk past its longest
 * word, so it wraps instead of overflowing.
 */
.wx-action-bar__state {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex: 1 1 auto;
  min-width: min-content;
  flex-wrap: wrap;
}

/* Against the end of the bar on one line and on two: on a line of its own the buttons would
   otherwise start at the left, under the words, which reads as a second, unrelated row. */
/*
 * The buttons wrap too, for the same reason the bar does.
 *
 * `flex: 0 0 auto` kept them on one line whatever the width, and a line of five was 815px inside
 * a bar 359 wide — measured on a 375px screen, where it took the whole page sideways with it and
 * left a scrollbar under a list that fitted perfectly well. They keep the end of the bar while
 * there is room and fold into rows when there is not; `justify-content` is what keeps the fold
 * aligned with the edge the buttons came from.
 */
.wx-action-bar__actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  flex: 0 1 auto;
  min-width: 0;
  margin-inline-start: auto;
}
</style>
