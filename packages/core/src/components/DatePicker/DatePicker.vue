<script setup lang="ts">
import { computed } from 'vue'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import { useFormField } from '../../composables/useFormField'
import '../../styles/datepicker.css'
import type { DatePickerEmits, DatePickerModelValue, DatePickerProps } from './types'

defineOptions({ name: 'WxDatePicker', inheritAttrs: false })

const props = withDefaults(defineProps<DatePickerProps>(), {
  type: 'date',
  valueFormat: undefined,
  format: undefined,
  placeholder: undefined,
  clearable: true,
  minDate: undefined,
  maxDate: undefined,
  seconds: false,
  minutesIncrement: 1,
  is24: true,
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

const emit = defineEmits<DatePickerEmits>()

const model = defineModel<DatePickerModelValue>({ default: null })

const field = useFormField(props)

const hasTime = computed(() => props.type !== 'date')

/**
 * What the model holds. Defaults match the column types a Laravel migration
 * produces, so the value can go to the API untouched; `'date'` opts out and keeps
 * `Date` objects.
 */
const modelType = computed(() => {
  if (props.valueFormat === 'date') return undefined
  if (props.valueFormat) return props.valueFormat
  if (props.type === 'time') return props.seconds ? 'HH:mm:ss' : 'HH:mm'
  if (props.type === 'datetime') return props.seconds ? 'yyyy-MM-dd HH:mm:ss' : 'yyyy-MM-dd HH:mm'
  return 'yyyy-MM-dd'
})

/** What the field shows. Day-first, because this kit is aimed at European admins. */
const displayFormat = computed(() => {
  if (props.format) return props.format
  const time = props.seconds ? 'HH:mm:ss' : 'HH:mm'
  if (props.type === 'time') return time
  if (props.type === 'datetime') return `dd.MM.yyyy ${time}`
  return 'dd.MM.yyyy'
})

const timeConfig = computed(() => ({
  enableTimePicker: hasTime.value,
  enableSeconds: props.seconds,
  is24: props.is24,
  minutesIncrement: props.minutesIncrement,
}))

const inputAttrs = computed(() => ({
  id: field.id.value,
  name: props.name,
  clearable: props.clearable,
  // The library reads `state: false` as invalid; leaving it undefined means neutral.
  state: field.status.value === 'error' ? false : undefined,
  'aria-label': props.ariaLabel,
  'aria-describedby': field.describedBy.value,
}))

const classes = computed(() => [
  'wx-datepicker',
  `wx-datepicker--${field.size.value}`,
  {
    [`wx-datepicker--${field.status.value}`]: field.status.value !== 'default',
    'is-disabled': field.disabled.value,
  },
])

function onUpdate(value: DatePickerModelValue) {
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
  <div :class="classes">
    <vue-date-picker
      :model-value="model"
      :model-type="modelType"
      :formats="{ input: displayFormat }"
      :time-picker="type === 'time'"
      :time-config="timeConfig"
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
      v-bind="$attrs"
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
