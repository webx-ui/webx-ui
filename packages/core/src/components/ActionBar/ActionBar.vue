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

.wx-action-bar--sticky {
  position: sticky;
  bottom: var(--wx-action-bar-bottom, 0px);
  z-index: var(--wx-z-index-sticky);
  box-shadow: var(--wx-shadow-card);
}

/*
 * The strip between the bar and the bottom edge of the window. A shell that insets its
 * column stops the bar short of the edge, and without this the page scrolling past shows
 * through the gap — a sliver of moving text under a bar that is standing still. Where the
 * bar has settled into the flow the strip is the shell's own padding, which is this colour
 * already, so it is only ever visible while the bar is stuck.
 */
.wx-action-bar--sticky::after {
  content: '';
  position: absolute;
  inset-inline: 0;
  top: 100%;
  height: var(--wx-action-bar-bottom, 0px);
  background: var(--wx-bg-body);
}

/* The left side is whatever the screen says about the state of the work — saved, a draft,
   a version number — and it is what gives way when the row runs out of width. */
.wx-action-bar__state {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex: 1 1 auto;
  min-width: 0;
  flex-wrap: wrap;
}

.wx-action-bar__actions {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex: 0 0 auto;
}
</style>
