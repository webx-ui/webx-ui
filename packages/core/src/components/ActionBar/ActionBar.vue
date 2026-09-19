<script setup lang="ts">
import { computed } from 'vue'
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
</script>

<template>
  <div :class="classes">
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
   * Without this the state box is shrunk to nothing — `min-width: 0` lets it — and its text
   * goes on being painted where the box no longer is, straight across the buttons. Measured on
   * a 375px screen with "Saved · goes out on 25 September at 17:06" beside two buttons: the box
   * 0px wide and 105 tall, the words over the top of "Save draft". Nothing about it looks like
   * a layout that ran out of room; it looks like the bar is broken.
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
.wx-action-bar__state {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex: 1 1 220px;
  min-width: 0;
  flex-wrap: wrap;
}

/* Against the end of the bar on one line and on two: on a line of its own the buttons would
   otherwise start at the left, under the words, which reads as a second, unrelated row. */
.wx-action-bar__actions {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex: 0 0 auto;
  margin-inline-start: auto;
}
</style>
