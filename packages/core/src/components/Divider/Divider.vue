<script setup lang="ts">
import { computed, useSlots } from 'vue'
import type { DividerProps } from './types'

defineOptions({ name: 'WxDivider' })

const props = withDefaults(defineProps<DividerProps>(), {
  direction: 'horizontal',
  variant: 'solid',
  align: 'center',
  spacing: 'md',
  label: undefined,
})

const slots = useSlots()

/*
 * A plain rule is a separator and says so; one carrying a label is a small heading
 * in disguise, and `role="separator"` on an element with text confuses a screen
 * reader more than it helps. The note lives here rather than above the template's
 * root element, where a comment would turn the template into a fragment.
 */
const hasLabel = computed(() => Boolean(props.label || slots.default))

const classes = computed(() => [
  'wx-divider',
  `wx-divider--${props.direction}`,
  `wx-divider--${props.variant}`,
  `wx-divider--spacing-${props.spacing}`,
  {
    'wx-divider--labelled': hasLabel.value,
    [`wx-divider--align-${props.align}`]: hasLabel.value,
  },
])
</script>

<template>
  <div
    :class="classes"
    :role="hasLabel ? undefined : 'separator'"
    :aria-orientation="hasLabel || direction === 'horizontal' ? undefined : 'vertical'"
  >
    <span v-if="hasLabel" class="wx-divider__label">
      <slot>{{ label }}</slot>
    </span>
  </div>
</template>

<style scoped>
.wx-divider {
  --wx-divider-color: var(--wx-border-default);
  --wx-divider-style: solid;

  box-sizing: border-box;
  border: 0;
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-xs);
}

.wx-divider--dashed {
  --wx-divider-style: dashed;
}

.wx-divider--dotted {
  --wx-divider-style: dotted;
}

/* horizontal */
.wx-divider--horizontal {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  width: 100%;
  margin-block: var(--wx-divider-gap, var(--wx-space-16));
}

/*
 * The rule is drawn by the pseudo-elements rather than a border on the box, so a
 * label can sit between two of them without a background of its own — which is what
 * keeps it readable on a card, a table row or a tinted panel alike.
 */
.wx-divider--horizontal::before,
.wx-divider--horizontal::after {
  content: '';
  flex: 1 1 auto;
  border-top: 1px var(--wx-divider-style) var(--wx-divider-color);
}

.wx-divider--horizontal:not(.wx-divider--labelled) {
  /* No label, no gap — the two halves have to meet or the rule shows a notch. */
  gap: 0;
}

.wx-divider--horizontal:not(.wx-divider--labelled)::after {
  display: none;
}

.wx-divider--align-start::before {
  flex: 0 0 var(--wx-space-24);
}

.wx-divider--align-end::after {
  flex: 0 0 var(--wx-space-24);
}

/* vertical */
.wx-divider--vertical {
  display: inline-block;
  width: 0;
  height: 1em;
  margin-inline: var(--wx-divider-gap, var(--wx-space-8));
  border-left: 1px var(--wx-divider-style) var(--wx-divider-color);
  vertical-align: middle;
}

/* A vertical rule never carries a label — there is no room for one. */
.wx-divider--vertical .wx-divider__label {
  display: none;
}

.wx-divider--spacing-none {
  --wx-divider-gap: 0px;
}

.wx-divider--spacing-sm {
  --wx-divider-gap: var(--wx-space-8);
}

.wx-divider--spacing-md {
  --wx-divider-gap: var(--wx-space-16);
}

.wx-divider--spacing-lg {
  --wx-divider-gap: var(--wx-space-24);
}

.wx-divider--vertical.wx-divider--spacing-md {
  --wx-divider-gap: var(--wx-space-8);
}

.wx-divider--vertical.wx-divider--spacing-lg {
  --wx-divider-gap: var(--wx-space-12);
}

.wx-divider__label {
  flex: 0 0 auto;
  white-space: nowrap;
}
</style>
