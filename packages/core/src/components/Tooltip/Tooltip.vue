<script setup lang="ts">
import { computed } from 'vue'
import {
  TooltipArrow,
  TooltipContent,
  TooltipPortal,
  TooltipProvider,
  TooltipRoot,
  TooltipTrigger,
} from 'reka-ui'
import type { TooltipProps } from './types'

defineOptions({ name: 'WxTooltip', inheritAttrs: false })

/*
 * A tip, not a panel. It says what a control is and nothing more: it cannot be
 * clicked into, it never holds a button, and it is gone the moment the pointer
 * leaves. Anything a reader has to reach for is a `WxPopover`.
 *
 * Its own provider rather than one at the root of the app, so a tooltip works
 * wherever it is dropped. Reka's providers nest without fighting, and the grouping
 * that skips the delay for the second tip is per provider — which is the right
 * scope anyway: a toolbar's tips are a group, the page's tips are not.
 */
const props = withDefaults(defineProps<TooltipProps>(), {
  content: undefined,
  side: 'top',
  align: 'center',
  offset: 6,
  delay: 400,
  arrow: true,
  maxWidth: 260,
  disabled: false,
  teleport: true,
})

defineSlots<{
  /** The control the tip belongs to. Exactly one element. */
  default?: () => unknown
  /** The tip itself, when the text is not enough. */
  content?: () => unknown
}>()

/* Left alone, the tip opens itself on hover and on focus; the model is for the
 * rare case of showing one from somewhere else — a hint after a failed save. */
const open = defineModel<boolean | undefined>('open', { default: undefined })

const width = computed(() =>
  typeof props.maxWidth === 'number' ? `${props.maxWidth}px` : props.maxWidth,
)
</script>

<template>
  <tooltip-provider
    :delay-duration="delay"
    :skip-delay-duration="300"
    :disable-hoverable-content="true"
  >
    <tooltip-root v-model:open="open" :disabled="disabled">
      <!--
        `as-child`: the trigger is the control that was passed in. Wrapping it in a
        span of our own would put a box in the layout that nobody asked for, and
        would take the tip off the thing that is actually focused.
      -->
      <tooltip-trigger as-child>
        <slot />
      </tooltip-trigger>

      <tooltip-portal :disabled="!teleport">
        <tooltip-content
          v-bind="$attrs"
          class="wx-tooltip"
          :side="side"
          :align="align"
          :side-offset="offset"
          :style="{ '--wx-tooltip-max-width': width }"
        >
          <slot name="content">{{ content }}</slot>
          <tooltip-arrow v-if="arrow" class="wx-tooltip__arrow" :width="10" :height="5" />
        </tooltip-content>
      </tooltip-portal>
    </tooltip-root>
  </tooltip-provider>
</template>

<style>
/* Teleported, so the styles cannot be scoped to the component. */
.wx-tooltip {
  z-index: var(--wx-z-index-tooltip);
  box-sizing: border-box;
  max-width: var(--wx-tooltip-max-width, 260px);
  padding: var(--wx-space-6) var(--wx-space-8);
  background: var(--wx-bg-inverse);
  border-radius: var(--wx-radius-xs);
  color: var(--wx-text-inverse);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-xs);
  line-height: var(--wx-font-line-height-normal);
  /* The tip belongs to whatever is under the pointer, never to the pointer. */
  pointer-events: none;
}

.wx-tooltip__arrow {
  fill: var(--wx-bg-inverse);
}

.wx-tooltip[data-state='delayed-open'] {
  animation: wx-tooltip-in var(--wx-duration-fast) var(--wx-easing-standard);
}

@keyframes wx-tooltip-in {
  from {
    opacity: 0;
    transform: scale(0.96);
  }

  to {
    opacity: 1;
    transform: scale(1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .wx-tooltip[data-state='delayed-open'] {
    animation: none;
  }
}
</style>
