<script setup lang="ts">
import { computed, provide, ref } from 'vue'
import { stepsKey } from './context'
import type { StepsEmits, StepsProps, StepState } from './types'

defineOptions({ name: 'WxSteps' })

const props = withDefaults(defineProps<StepsProps>(), {
  current: 0,
  direction: 'horizontal',
  size: 'md',
  error: false,
  clickable: false,
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
    return props.direction
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
  `wx-steps--${props.direction}`,
  `wx-steps--${props.size}`,
])
</script>

<template>
  <ol :class="classes" :aria-label="ariaLabel">
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

.wx-steps--horizontal {
  flex-direction: row;
  align-items: flex-start;
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
