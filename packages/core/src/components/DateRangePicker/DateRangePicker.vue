<script setup lang="ts">
import { computed } from 'vue'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import { useFormField } from '../../composables/useFormField'
import '../../styles/datepicker.css'
import type { DateRangePickerEmits, DateRangePickerModelValue, DateRangePickerProps } from './types'
import { useControlAttrs } from '../../composables/useControlAttrs'

defineOptions({ name: 'WxDateRangePicker', inheritAttrs: false })

/* `class` and `style` belong to the control; the rest belongs to its input. */
const { rootAttrs, controlAttrs } = useControlAttrs()

const props = withDefaults(defineProps<DateRangePickerProps>(), {
  valueFormat: undefined,
  format: undefined,
  placeholder: undefined,
  clearable: true,
  minDate: undefined,
  maxDate: undefined,
  months: 2,
  weekStart: 1,
  autoApply: true,
  textInput: false,
  teleport: true,
  disabled: undefined,
  readonly: false,
  size: undefined,
  status: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

const emit = defineEmits<DateRangePickerEmits>()

/*
 * Every slot is passed straight through to the picker underneath, so this component's
 * slots are whatever the caller hands it. Saying so explicitly is what keeps the
 * declaration honest: left to infer, the slot type is read off a template that
 * enumerates the slots themselves, and TypeScript answers that circle by emitting
 * `any`.
 */
defineSlots<Record<string, (props: Record<string, unknown>) => unknown>>()

const model = defineModel<DateRangePickerModelValue>({ default: null })

const field = useFormField(props)

const modelType = computed(() =>
  props.valueFormat === 'date' ? undefined : (props.valueFormat ?? 'yyyy-MM-dd'),
)
const displayFormat = computed(() => props.format ?? 'dd.MM.yyyy')

const inputAttrs = computed(() => ({
  id: field.id.value,
  name: props.name,
  clearable: props.clearable,
  state: field.status.value === 'error' ? false : undefined,
  'aria-label': props.ariaLabel,
  'aria-describedby': field.describedBy.value,
}))

const classes = computed(() => [
  'wx-datepicker',
  'wx-datepicker--range',
  `wx-datepicker--${field.size.value}`,
  {
    [`wx-datepicker--${field.status.value}`]: field.status.value !== 'default',
    'is-disabled': field.disabled.value,
  },
])

/** The library hands back `null` while only the first date has been picked. */
function onUpdate(value: DateRangePickerModelValue) {
  model.value = value
  emit('change', value)
}

function onCleared() {
  model.value = null
  emit('change', null)
  emit('clear')
}
</script>

<template>
  <div :class="classes" v-bind="rootAttrs">
    <vue-date-picker
      range
      :model-value="model"
      :model-type="modelType"
      :formats="{ input: displayFormat }"
      :multi-calendars="months"
      :input-attrs="inputAttrs"
      :ui="{ input: 'wx-datepicker__input', menu: 'wx-datepicker__menu' }"
      :placeholder="placeholder"
      :min-date="minDate"
      :max-date="maxDate"
      :week-start="weekStart"
      :auto-apply="autoApply"
      :text-input="textInput"
      :teleport="teleport"
      :disabled="field.disabled.value"
      :readonly="readonly"
      v-bind="controlAttrs"
      @update:model-value="onUpdate"
      @cleared="onCleared"
      @open="emit('open')"
      @closed="emit('close')"
    >
      <template v-for="(_, name) in $slots" #[name]="slotProps">
        <slot :name="name" v-bind="slotProps ?? {}" />
      </template>
    </vue-date-picker>
  </div>
</template>
