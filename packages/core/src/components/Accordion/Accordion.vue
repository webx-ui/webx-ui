<script setup lang="ts">
import { computed, provide, watch } from 'vue'
import { AccordionRoot } from 'reka-ui'
import { accordionKey } from '../../composables/useAccordion'
import type { AccordionEmits, AccordionModelValue, AccordionProps } from './types'

defineOptions({ name: 'WxAccordion' })

const props = withDefaults(defineProps<AccordionProps>(), {
  multiple: false,
  collapsible: true,
  variant: 'bordered',
  size: 'md',
  iconPosition: 'end',
  headingTag: 'h3',
  disabled: false,
})

const emit = defineEmits<AccordionEmits>()

defineSlots<{
  /** The `WxAccordionItem`s. */
  default?: () => unknown
}>()

/**
 * One value or a list of them, depending on `multiple` — the same shape Reka uses, and
 * the same shape a backend would store it in.
 */
const model = defineModel<AccordionModelValue>({ default: undefined })

provide(accordionKey, {
  size: computed(() => props.size),
  variant: computed(() => props.variant),
  iconPosition: computed(() => props.iconPosition),
  headingTag: computed(() => props.headingTag),
})

watch(model, (value) => emit('change', value))

const classes = computed(() => [
  'wx-accordion',
  `wx-accordion--${props.variant}`,
  `wx-accordion--${props.size}`,
])
</script>

<template>
  <accordion-root
    v-model="model"
    :class="classes"
    :type="multiple ? 'multiple' : 'single'"
    :collapsible="collapsible"
    :disabled="disabled"
  >
    <slot />
  </accordion-root>
</template>

<style scoped>
.wx-accordion {
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

.wx-accordion--bordered {
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  /* The items square off their own corners; the box rounds what sticks out. */
  overflow: hidden;
}

/* Each item is its own card, with air between them. */
.wx-accordion--separated {
  gap: var(--wx-space-8);
}
</style>
