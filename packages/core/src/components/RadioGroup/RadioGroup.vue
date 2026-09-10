<script setup lang="ts">
import { computed, provide } from 'vue'
import {
  radioGroupKey,
  useFormField,
  useId,
  type ChoiceValue,
} from '../../composables/useFormField'
import WxRadio from '../Radio/Radio.vue'
import type { RadioGroupEmits, RadioGroupProps } from './types'

defineOptions({ name: 'WxRadioGroup' })

const props = withDefaults(defineProps<RadioGroupProps>(), {
  options: undefined,
  disabled: undefined,
  size: undefined,
  name: undefined,
  inline: false,
})

const emit = defineEmits<RadioGroupEmits>()

const model = defineModel<ChoiceValue>({ default: null })

const field = useFormField(props)
const fallbackName = useId('wx-radio-group')

/** A group cannot be the target of a `for`, so the item label becomes a span. */
field.item?.registerLabelTarget(undefined)

const disabled = computed(() => props.disabled ?? field.disabled.value)
const size = computed(() => props.size ?? field.size.value)

/**
 * Radios sharing a `name` are one roving-tabindex group in the browser, which is
 * what gives arrow-key navigation for free — so a name is always set.
 */
const name = computed(() => props.name ?? fallbackName)

function toggle(value: ChoiceValue) {
  if (model.value === value) return
  model.value = value
  emit('change', value)
}

provide(radioGroupKey, {
  name,
  modelValue: computed(() => model.value),
  disabled,
  size,
  toggle,
})
</script>

<template>
  <div
    :class="['wx-radio-group', { 'wx-radio-group--inline': inline }]"
    role="radiogroup"
    :aria-labelledby="field.item ? `${field.item.id.value}-label` : undefined"
    :aria-describedby="field.describedBy.value"
  >
    <slot>
      <wx-radio
        v-for="option in options"
        :key="String(option.value)"
        :value="option.value"
        :label="option.label"
        :disabled="option.disabled"
      />
    </slot>
  </div>
</template>

<style scoped>
.wx-radio-group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-radio-group--inline {
  flex-direction: row;
  flex-wrap: wrap;
  gap: var(--wx-space-16);
}
</style>
