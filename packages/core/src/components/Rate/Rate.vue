<script setup lang="ts">
import { computed, ref } from 'vue'
import { useFormField } from '../../composables/useFormField'
import type { RateEmits, RateProps } from './types'

defineOptions({ name: 'WxRate', inheritAttrs: false })

const props = withDefaults(defineProps<RateProps>(), {
  max: 5,
  allowHalf: false,
  clearable: true,
  showValue: false,
  readonly: false,
  disabled: undefined,
  size: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<RateEmits>()

const model = defineModel<number>({ default: 0 })

const field = useFormField(props)
const hovered = ref<number | null>(null)

const interactive = computed(() => !field.disabled.value && !props.readonly)

/** What the stars show right now: the hovered value while pointing, the model otherwise. */
const shown = computed(() => hovered.value ?? model.value ?? 0)

const stars = computed(() =>
  Array.from({ length: props.max }, (_, index) => {
    const position = index + 1
    const fill = Math.min(Math.max(shown.value - index, 0), 1)
    return { position, full: fill >= 1, half: fill > 0 && fill < 1 }
  }),
)

const classes = computed(() => [
  'wx-rate',
  `wx-rate--${field.size.value}`,
  { 'is-disabled': field.disabled.value, 'is-readonly': props.readonly },
])

/** Halves come from the pointer landing on the left side of a star. */
function valueAt(position: number, event: MouseEvent): number {
  if (!props.allowHalf) return position
  const target = event.currentTarget as HTMLElement
  const { left, width } = target.getBoundingClientRect()
  return event.clientX - left < width / 2 ? position - 0.5 : position
}

/** `toggle` is what makes clicking the current value clear it — the arrow keys must not. */
function set(value: number, toggle = false) {
  const next = toggle && props.clearable && model.value === value ? 0 : value
  if (next === model.value) return
  model.value = next
  emit('change', next)
}

function onClick(position: number, event: MouseEvent) {
  if (!interactive.value) return
  set(valueAt(position, event), true)
}

function onHover(position: number, event: MouseEvent) {
  if (!interactive.value) return
  hovered.value = valueAt(position, event)
}

function onKeydown(event: KeyboardEvent) {
  if (!interactive.value) return
  const step = props.allowHalf ? 0.5 : 1
  const current = model.value ?? 0

  if (event.key === 'ArrowRight' || event.key === 'ArrowUp') {
    event.preventDefault()
    set(Math.min(current + step, props.max))
  } else if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') {
    event.preventDefault()
    set(Math.max(current - step, 0))
  } else if (event.key === 'Home') {
    event.preventDefault()
    set(0)
  } else if (event.key === 'End') {
    event.preventDefault()
    set(props.max)
  }
}
</script>

<template>
  <div
    :id="field.id.value"
    v-bind="$attrs"
    :class="classes"
    role="slider"
    :tabindex="interactive ? 0 : -1"
    :aria-label="ariaLabel"
    :aria-describedby="field.describedBy.value"
    :aria-valuemin="0"
    :aria-valuemax="max"
    :aria-valuenow="model ?? 0"
    :aria-readonly="readonly || undefined"
    :aria-disabled="field.disabled.value || undefined"
    @keydown="onKeydown"
    @mouseleave="hovered = null"
  >
    <span
      v-for="star in stars"
      :key="star.position"
      class="wx-rate__star"
      :class="{ 'is-full': star.full, 'is-half': star.half }"
      @click="onClick(star.position, $event)"
      @mousemove="onHover(star.position, $event)"
    >
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path
          class="wx-rate__outline"
          d="M12 3.5l2.6 5.3 5.9.9-4.2 4.1 1 5.8-5.3-2.8-5.3 2.8 1-5.8-4.2-4.1 5.9-.9z"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linejoin="round"
        />
        <path
          class="wx-rate__fill"
          d="M12 3.5l2.6 5.3 5.9.9-4.2 4.1 1 5.8-5.3-2.8-5.3 2.8 1-5.8-4.2-4.1 5.9-.9z"
          fill="currentColor"
        />
      </svg>
    </span>

    <span v-if="showValue" class="wx-rate__value">{{ model ?? 0 }}</span>

    <input v-if="name" type="hidden" :name="name" :value="model ?? 0" />
  </div>
</template>

<style scoped>
.wx-rate {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-2);
  color: var(--wx-border-strong);
}

.wx-rate:focus-visible {
  outline: none;
  border-radius: var(--wx-radius-xs);
  box-shadow: var(--wx-ring-focus);
}

.wx-rate--sm {
  --wx-rate-size: 18px;
  font-size: var(--wx-font-size-sm);
}

.wx-rate--md {
  --wx-rate-size: 22px;
  font-size: var(--wx-font-size-md);
}

.wx-rate--lg {
  --wx-rate-size: 28px;
  font-size: var(--wx-font-size-lg);
}

.wx-rate__star {
  display: inline-flex;
  width: var(--wx-rate-size, 22px);
  height: var(--wx-rate-size, 22px);
  cursor: pointer;
}

.wx-rate.is-disabled .wx-rate__star,
.wx-rate.is-readonly .wx-rate__star {
  cursor: default;
}

.wx-rate.is-disabled {
  opacity: 0.6;
}

.wx-rate__star svg {
  width: 100%;
  height: 100%;
  overflow: visible;
}

/* The filled star is clipped to nothing, then revealed whole or by half. */
.wx-rate__fill {
  color: var(--wx-color-warning);
  clip-path: inset(0 100% 0 0);
  transition: clip-path var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-rate__star.is-full .wx-rate__fill {
  clip-path: inset(0);
}

.wx-rate__star.is-half .wx-rate__fill {
  clip-path: inset(0 50% 0 0);
}

.wx-rate__star.is-full,
.wx-rate__star.is-half {
  color: var(--wx-color-warning);
}

.wx-rate__value {
  margin-left: var(--wx-space-6);
  color: var(--wx-text-muted);
  font-variant-numeric: tabular-nums;
}

@media (prefers-reduced-motion: reduce) {
  .wx-rate__fill {
    transition: none;
  }
}
</style>
