<script setup lang="ts">
import { computed, inject } from 'vue'
import { radioGroupKey, useFormField, type ChoiceValue } from '../../composables/useFormField'
import type { RadioEmits, RadioProps } from './types'

defineOptions({ name: 'WxRadio', inheritAttrs: false })

const props = withDefaults(defineProps<RadioProps>(), {
  label: undefined,
  disabled: undefined,
  size: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<RadioEmits>()

const model = defineModel<ChoiceValue>({ default: null })

const group = inject(radioGroupKey, null)
const field = useFormField(props)

const checked = computed(() =>
  group ? group.modelValue.value === props.value : model.value === props.value,
)

const disabled = computed(() => props.disabled || group?.disabled.value || field.disabled.value)
const size = computed(() => props.size ?? group?.size.value ?? field.size.value)

const classes = computed(() => [
  'wx-radio',
  `wx-radio--${size.value}`,
  { 'is-checked': checked.value, 'is-disabled': disabled.value },
])

function onChange() {
  if (group) group.toggle(props.value, true)
  else model.value = props.value
  emit('change', props.value)
}
</script>

<template>
  <label :class="classes">
    <input
      :id="field.id.value"
      v-bind="$attrs"
      class="wx-radio__native"
      type="radio"
      :name="name ?? group?.name.value"
      :checked="checked"
      :disabled="disabled"
      :aria-label="ariaLabel"
      :aria-describedby="field.describedBy.value"
      @change="onChange"
    />

    <span class="wx-radio__box" aria-hidden="true">
      <svg class="wx-radio__mark" viewBox="0 0 20 20">
        <circle class="wx-radio__ring" cx="10" cy="10" r="9.5" />
        <circle class="wx-radio__dot" cx="10" cy="10" r="4" />
      </svg>
    </span>

    <span v-if="label || $slots.default" class="wx-radio__label">
      <slot>{{ label }}</slot>
    </span>
  </label>
</template>

<style scoped>
.wx-radio {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-8);
  cursor: pointer;
  user-select: none;
  color: var(--wx-text-default);
}

.wx-radio.is-disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-radio__native {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
  border: 0;
}

/*
 * The box only holds the size and the focus ring; the circles are drawn inside it. A CSS
 * border plus a centred child cannot stay concentric at a fractional device pixel ratio —
 * Windows at 125% or 150% rounds the 1px border to a whole device pixel on each side
 * independently, which moves the content box off the centre of the border box and takes
 * the dot with it. Ring and dot as two circles on one origin cannot drift apart: the
 * renderer resolves both against real geometry and antialiases, instead of snapping boxes.
 */
.wx-radio__box {
  display: block;
  flex: 0 0 auto;
  box-sizing: border-box;
  width: var(--wx-radio-size, 20px);
  height: var(--wx-radio-size, 20px);
  border-radius: var(--wx-radius-full);
  transition: box-shadow var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-radio--sm {
  --wx-radio-size: 16px;
  font-size: var(--wx-font-size-sm);
}

.wx-radio--md {
  --wx-radio-size: 20px;
  font-size: var(--wx-font-size-md);
}

.wx-radio--lg {
  --wx-radio-size: 24px;
  font-size: var(--wx-font-size-lg);
}

/* The ring's outer half of stroke sits on the viewport edge, so it must not be clipped. */
.wx-radio__mark {
  display: block;
  width: 100%;
  height: 100%;
  overflow: visible;
}

/*
 * `non-scaling-stroke` keeps the ring one pixel wide at every size, the way the checkbox
 * border is, instead of thinning to 0.8px on `sm` and thickening to 1.2px on `lg`.
 */
.wx-radio__ring {
  fill: var(--wx-bg-surface);
  stroke: var(--wx-border-strong);
  stroke-width: 1;
  vector-effect: non-scaling-stroke;
  transition: stroke var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-radio__dot {
  fill: var(--wx-color-primary);
  transform: scale(0);
  transform-box: fill-box;
  transform-origin: center;
  transition: transform var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-radio:hover:not(.is-disabled) .wx-radio__ring {
  stroke: var(--wx-color-primary);
}

.wx-radio.is-checked .wx-radio__ring {
  stroke: var(--wx-color-primary);
}

.wx-radio.is-checked .wx-radio__dot {
  transform: scale(1);
}

.wx-radio.is-disabled .wx-radio__ring {
  fill: var(--wx-bg-disabled);
  stroke: var(--wx-border-default);
}

.wx-radio.is-disabled .wx-radio__dot {
  fill: var(--wx-color-primary-disabled);
}

.wx-radio__native:focus-visible + .wx-radio__box {
  box-shadow: var(--wx-ring-focus);
}

.wx-radio__native:focus-visible + .wx-radio__box .wx-radio__ring {
  stroke: var(--wx-color-primary);
}

.wx-radio__label {
  line-height: var(--wx-font-line-height-normal);
}

@media (prefers-reduced-motion: reduce) {
  .wx-radio__box,
  .wx-radio__ring,
  .wx-radio__dot {
    transition: none;
  }
}
</style>
