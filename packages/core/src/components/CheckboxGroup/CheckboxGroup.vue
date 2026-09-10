<script setup lang="ts">
import { computed, provide } from 'vue'
import { checkboxGroupKey, useFormField, type ChoiceValue } from '../../composables/useFormField'
import WxCheckbox from '../Checkbox/Checkbox.vue'
import type { CheckboxGroupEmits, CheckboxGroupProps } from './types'

defineOptions({ name: 'WxCheckboxGroup' })

const props = withDefaults(defineProps<CheckboxGroupProps>(), {
  options: undefined,
  disabled: undefined,
  size: undefined,
  name: undefined,
  inline: false,
  min: undefined,
  max: undefined,
})

const emit = defineEmits<CheckboxGroupEmits>()

const model = defineModel<ChoiceValue[]>({ default: () => [] })

const field = useFormField(props)

/** A group cannot be the target of a `for`, so the item label becomes a span. */
field.item?.registerLabelTarget(undefined)

const disabled = computed(() => props.disabled ?? field.disabled.value)
const size = computed(() => props.size ?? field.size.value)

function toggle(value: ChoiceValue, checked: boolean) {
  const current = model.value ?? []
  if (checked) {
    if (current.includes(value)) return
    if (props.max !== undefined && current.length >= props.max) return
    model.value = [...current, value]
  } else {
    if (props.min !== undefined && current.length <= props.min) return
    model.value = current.filter((item) => item !== value)
  }
  emit('change', model.value)
}

provide(checkboxGroupKey, {
  name: computed(() => props.name),
  modelValue: computed(() => model.value ?? []),
  disabled,
  size,
  toggle,
})
</script>

<template>
  <div
    :class="['wx-checkbox-group', { 'wx-checkbox-group--inline': inline }]"
    role="group"
    :aria-labelledby="field.item ? `${field.item.id.value}-label` : undefined"
    :aria-describedby="field.describedBy.value"
  >
    <slot>
      <wx-checkbox
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
.wx-checkbox-group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-checkbox-group--inline {
  flex-direction: row;
  flex-wrap: wrap;
  gap: var(--wx-space-16);
}
</style>
