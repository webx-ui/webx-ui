<script setup lang="ts">
import { computed, provide, ref } from 'vue'
import { useElementWidth } from '../../composables/useElementWidth'
import { stepsKey } from './context'
import type { StepsEmits, StepsProps, StepState } from './types'

defineOptions({ name: 'WxSteps' })

const props = withDefaults(defineProps<StepsProps>(), {
  current: 0,
  direction: 'horizontal',
  size: 'md',
  error: false,
  clickable: false,
  minStepWidth: 132,
  ariaLabel: undefined,
})

const emit = defineEmits<StepsEmits>()

defineSlots<{ default?: () => unknown }>()

/*
 * The steps report themselves in mount order rather than being declared as data.
 * A step's content is markup — a title, a description, sometimes a form — and the
 * shape that holds markup is a slot, so the numbering has to come from the DOM.
 */
const ids = ref<symbol[]>([])

const list = ref<HTMLElement | null>(null)
const width = useElementWidth(list)

/*
 * Across the page a step is a marker, a title and a line of explanation side by side, and
 * on a phone there is room for about one of those. Rather than let the titles wrap to a
 * letter a line, the sequence turns down the page — which is the same sequence, read the
 * way a narrow screen reads everything else.
 *
 * How many steps there are is part of the question, so this is a measurement rather than a
 * media or container query: four steps need four times the room two do. A width of `0` is
 * "not measured yet" — on the server and before the first frame — and the roomy answer is
 * the safe one there.
 */
const direction = computed(() => {
  if (props.direction === 'vertical' || props.minStepWidth <= 0) return props.direction
  if (width.value === 0 || ids.value.length === 0) return props.direction
  return width.value < ids.value.length * props.minStepWidth ? 'vertical' : 'horizontal'
})

provide(stepsKey, {
  register: (id) => {
    if (!ids.value.includes(id)) ids.value.push(id)
  },
  unregister: (id) => {
    ids.value = ids.value.filter((known) => known !== id)
  },
  indexOf: (id) => ids.value.indexOf(id),
  stateOf: (index): StepState => {
    if (index < props.current) return 'done'
    if (index > props.current) return 'todo'
    return props.error ? 'error' : 'current'
  },
  choose: (index) => {
    /* Only backwards: the steps ahead are the ones not filled in yet. */
    if (!props.clickable || index >= props.current) return
    emit('change', index)
  },
  get direction() {
    return direction.value
  },
  get size() {
    return props.size
  },
  get clickable() {
    return props.clickable
  },
  get total() {
    return ids.value.length
  },
})

const classes = computed(() => [
  'wx-steps',
  `wx-steps--${direction.value}`,
  `wx-steps--${props.size}`,
  { 'is-folded': direction.value !== props.direction },
])
</script>

<template>
  <ol ref="list" :class="classes" :aria-label="ariaLabel">
    <slot />
  </ol>
</template>

<style scoped>
.wx-steps {
  display: flex;
  box-sizing: border-box;
  margin: 0;
  padding: 0;
  list-style: none;
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

/*
 * The gap between steps is the other half of the one inside them. Each step already
 * holds its rule off its own text; without this the rule then ran straight into the
 * next step's marker, and the spacing either side of it did not match.
 */
.wx-steps--horizontal {
  flex-direction: row;
  align-items: flex-start;
  gap: var(--wx-space-8);
}

.wx-steps--vertical {
  flex-direction: column;
}

.wx-steps--sm {
  --wx-step-marker: 24px;
  --wx-step-title: var(--wx-font-size-sm);
}

.wx-steps--md {
  --wx-step-marker: 32px;
  --wx-step-title: var(--wx-font-size-md);
}
</style>
