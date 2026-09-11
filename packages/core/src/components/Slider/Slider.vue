<script setup lang="ts">
import { computed } from 'vue'
import { SliderRange, SliderRoot, SliderThumb, SliderTrack } from 'reka-ui'
import { useFormField } from '../../composables/useFormField'
import type { SliderEmits, SliderModelValue, SliderProps } from './types'
import { useControlAttrs } from '../../composables/useControlAttrs'

defineOptions({ name: 'WxSlider', inheritAttrs: false })

/* `class` and `style` belong to the control; the rest belongs to its input. */
const { rootAttrs, controlAttrs } = useControlAttrs()

const props = withDefaults(defineProps<SliderProps>(), {
  min: 0,
  max: 100,
  step: 1,
  range: false,
  minStepsBetweenThumbs: 0,
  showValue: false,
  marks: undefined,
  disabled: undefined,
  size: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<SliderEmits>()

const model = defineModel<SliderModelValue>({ default: null })

const field = useFormField(props)

/**
 * The underlying slider always works with an array. A single slider keeps a plain
 * number in the model, because that is what a caller wants to store.
 */
const values = computed<number[]>(() => {
  if (props.range) {
    const pair = Array.isArray(model.value) ? model.value : []
    return [pair[0] ?? props.min, pair[1] ?? props.max]
  }
  const single = Array.isArray(model.value) ? model.value[0] : model.value
  return [single ?? props.min]
})

function onUpdate(next: number[] | undefined) {
  if (!next) return
  const value = props.range ? [...next] : next[0]
  model.value = value
  emit('change', value)
}

const marks = computed(() =>
  Object.entries(props.marks ?? {}).map(([value, label]) => ({
    value: Number(value),
    label,
    offset: ((Number(value) - props.min) / (props.max - props.min)) * 100,
  })),
)

const classes = computed(() => [
  'wx-slider',
  `wx-slider--${field.size.value}`,
  { 'is-disabled': field.disabled.value, 'is-range': props.range },
])
</script>

<template>
  <div :class="classes" v-bind="rootAttrs">
    <div class="wx-slider__row">
      <slider-root
        :id="field.id.value"
        v-bind="controlAttrs"
        class="wx-slider__root"
        :model-value="values"
        :min="min"
        :max="max"
        :step="step"
        :disabled="field.disabled.value"
        :min-steps-between-thumbs="minStepsBetweenThumbs"
        :name="name"
        @update:model-value="onUpdate"
      >
        <slider-track class="wx-slider__track">
          <slider-range class="wx-slider__range" />
        </slider-track>

        <slider-thumb
          v-for="index in values.length"
          :key="index"
          class="wx-slider__thumb"
          :aria-label="ariaLabel"
          :aria-describedby="field.describedBy.value"
        />
      </slider-root>

      <span v-if="showValue" class="wx-slider__value">
        {{ range ? `${values[0]} — ${values[1]}` : values[0] }}
      </span>
    </div>

    <div v-if="marks.length" class="wx-slider__marks" aria-hidden="true">
      <span
        v-for="mark in marks"
        :key="mark.value"
        class="wx-slider__mark"
        :style="{ left: `${mark.offset}%` }"
      >
        {{ mark.label }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.wx-slider {
  display: block;
  width: 100%;
}

.wx-slider__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-16);
}

.wx-slider__root {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1 1 auto;
  height: var(--wx-size-control-md);
  touch-action: none;
  user-select: none;
}

.wx-slider--sm .wx-slider__root {
  height: var(--wx-size-control-sm);
}

.wx-slider--lg .wx-slider__root {
  height: var(--wx-size-control-lg);
}

.wx-slider__track {
  position: relative;
  flex: 1 1 auto;
  height: 4px;
  background: var(--wx-bg-fill-hover);
  border-radius: var(--wx-radius-full);
}

.wx-slider__range {
  position: absolute;
  height: 100%;
  background: var(--wx-color-primary);
  border-radius: var(--wx-radius-full);
}

.wx-slider__thumb {
  display: block;
  width: 18px;
  height: 18px;
  background: var(--wx-bg-surface);
  border: 2px solid var(--wx-color-primary);
  border-radius: var(--wx-radius-full);
  box-shadow: var(--wx-shadow-card);
  cursor: grab;
  transition: box-shadow var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-slider__thumb:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-slider__thumb:active {
  cursor: grabbing;
}

.wx-slider.is-disabled .wx-slider__range {
  background: var(--wx-color-primary-disabled);
}

.wx-slider.is-disabled .wx-slider__thumb {
  border-color: var(--wx-color-primary-disabled);
  cursor: not-allowed;
}

.wx-slider__value {
  flex: 0 0 auto;
  min-width: 4ch;
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-sm);
  font-variant-numeric: tabular-nums;
  text-align: right;
}

.wx-slider__marks {
  position: relative;
  height: 18px;
  margin-top: calc(var(--wx-space-4) * -1);
}

.wx-slider__mark {
  position: absolute;
  transform: translateX(-50%);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  white-space: nowrap;
}
</style>
