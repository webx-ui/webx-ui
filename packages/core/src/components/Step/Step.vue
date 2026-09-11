<script setup lang="ts">
import { computed, onBeforeUnmount } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import { useSteps } from '../Steps/context'
import type { StepProps } from '../Steps/types'

defineOptions({ name: 'WxStep' })

withDefaults(defineProps<StepProps>(), {
  title: undefined,
  description: undefined,
  icon: undefined,
})

defineSlots<{
  title?: () => unknown
  /** The explanation. Overrides `description`. */
  default?: () => unknown
  /** Replaces the marker — a number by default, a tick once it is done. */
  marker?: (props: { index: number; state: string }) => unknown
}>()

const steps = useSteps()

const id = Symbol('wx-step')

/*
 * Registered while setting up rather than on mount. Children set up in the order
 * they are written, so the numbering is right for the first paint — registering
 * later means every step renders as finished for one frame before finding its
 * place, which is a flicker on every page that has a wizard on it.
 */
steps.register(id)

onBeforeUnmount(() => steps.unregister(id))

const index = computed(() => steps.indexOf(id))

const state = computed(() => steps.stateOf(index.value))

const interactive = computed(() => steps.clickable && state.value === 'done')

const classes = computed(() => [
  'wx-step',
  `wx-step--${state.value}`,
  { 'is-interactive': interactive.value },
])
</script>

<template>
  <li :class="classes" :aria-current="state === 'current' ? 'step' : undefined">
    <component
      :is="interactive ? 'button' : 'div'"
      class="wx-step__head"
      :type="interactive ? 'button' : undefined"
      @click="interactive && steps.choose(index)"
    >
      <span class="wx-step__marker">
        <slot name="marker" :index="index" :state="state">
          <wx-icon v-if="state === 'done'" name="check" />
          <wx-icon v-else-if="state === 'error'" name="close" />
          <wx-icon v-else-if="icon" :name="icon" />
          <span v-else>{{ index + 1 }}</span>
        </slot>
      </span>

      <span class="wx-step__text">
        <span v-if="title || $slots.title" class="wx-step__title">
          <slot name="title">{{ title }}</slot>
        </span>
        <span v-if="description || $slots.default" class="wx-step__description">
          <slot>{{ description }}</slot>
        </span>
      </span>
    </component>
  </li>
</template>

<style scoped>
.wx-step {
  position: relative;
  display: flex;
  min-width: 0;
  /*
   * The step owns its margins, and says so from a scoped rule so that it outweighs a
   * host stylesheet spacing list items — VitePress puts 8px between every `li + li`,
   * and a CMS theme will have its own. Left alone, that drops every step but the
   * first by eight pixels and the rule between them runs downhill.
   */
  margin: 0;
}

.wx-step__head {
  display: flex;
  gap: var(--wx-space-10);
  min-width: 0;
  padding: 0;
  background: none;
  border: none;
  color: inherit;
  font: inherit;
  text-align: start;
}

.is-interactive .wx-step__head {
  cursor: pointer;
}

.is-interactive .wx-step__head:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
  border-radius: var(--wx-radius-xs);
}

/*
 * The marker carries the state — a number ahead, a tick behind, a cross where it
 * went wrong — and the ring around the current one is what a reader looks for to
 * find their place.
 */
.wx-step__marker {
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  flex: 0 0 auto;
  width: var(--wx-step-marker, 32px);
  height: var(--wx-step-marker, 32px);
  background: var(--wx-bg-fill);
  border: 1px solid transparent;
  border-radius: var(--wx-radius-full);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-semibold);
  transition:
    background var(--wx-duration-fast) var(--wx-easing-standard),
    color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-step--done .wx-step__marker {
  background: var(--wx-color-primary-soft);
  color: var(--wx-color-primary);
}

.wx-step--current .wx-step__marker {
  background: var(--wx-color-primary);
  color: var(--wx-color-primary-contrast);
}

.wx-step--error .wx-step__marker {
  background: var(--wx-color-danger-soft);
  color: var(--wx-color-danger);
}

.wx-step__text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  padding-top: 4px;
}

.wx-step__title {
  color: var(--wx-text-muted);
  font-size: var(--wx-step-title, var(--wx-font-size-md));
  font-weight: var(--wx-font-weight-medium);
  line-height: var(--wx-font-line-height-tight);
}

.wx-step--done .wx-step__title,
.wx-step--current .wx-step__title {
  color: var(--wx-text-strong);
}

.wx-step--error .wx-step__title {
  color: var(--wx-color-danger);
}

.wx-step__description {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  line-height: var(--wx-font-line-height-normal);
}

/*
 * The rule to the next step is a pseudo-element chosen by `:last-child` rather than
 * an element chosen by counting. A step that has to be told how many siblings it has
 * cannot know on its first render — every one of them would think it was the last,
 * and the rules would appear a frame later.
 */
.wx-step:not(:last-child)::after {
  content: '';
  background: var(--wx-border-default);
}

/* A finished step's rule is drawn in the accent: the path taken so far. */
.wx-step--done:not(:last-child)::after {
  background: var(--wx-color-primary);
}

/* --- horizontal --- */

.wx-steps--horizontal .wx-step {
  flex: 1 1 0;
  align-items: flex-start;
  gap: var(--wx-space-8);
}

.wx-steps--horizontal .wx-step__head {
  min-width: 0;
}

/*
 * The step is a flex row, so the rule is simply the flex item after the head: it
 * takes whatever room is left between this step's text and the next step's marker.
 * Placing it absolutely instead would mean knowing where the text ends, which is
 * exactly what a pseudo-element cannot be told — and what drew the rule straight
 * through the titles.
 */
.wx-steps--horizontal .wx-step:not(:last-child)::after {
  flex: 1 1 auto;
  /* Level with the middle of the marker, whatever size the markers are. */
  margin-top: calc(var(--wx-step-marker, 32px) / 2);
  min-width: var(--wx-space-16);
  height: 1px;
}

.wx-steps--horizontal .wx-step:last-child {
  flex: 0 1 auto;
}

/* --- vertical --- */

.wx-steps--vertical .wx-step {
  flex-direction: column;
  padding-bottom: var(--wx-space-16);
}

.wx-steps--vertical .wx-step:last-child {
  padding-bottom: 0;
}

/*
 * Down the page the rule runs beside the text rather than after it, so here it is
 * taken out of the flow and hung from the marker's centre line.
 */
.wx-steps--vertical .wx-step:not(:last-child)::after {
  position: absolute;
  top: calc(var(--wx-step-marker, 32px) + 4px);
  bottom: 4px;
  inset-inline-start: calc(var(--wx-step-marker, 32px) / 2);
  width: 1px;
}

@media (prefers-reduced-motion: reduce) {
  .wx-step__marker {
    transition: none;
  }
}
</style>
