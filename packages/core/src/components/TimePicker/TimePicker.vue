<script setup lang="ts">
import WxDatePicker from '../DatePicker/DatePicker.vue'
import type { DatePickerModelValue, DatePickerProps } from '../DatePicker/types'

defineOptions({ name: 'WxTimePicker' })

/**
 * Every prop defaults to `undefined` on purpose. Vue casts an absent boolean prop
 * to `false`, and this preset forwards its whole prop object — without these the
 * preset would hand `WxDatePicker` an explicit `false` for `is24`, `clearable`,
 * `autoApply` and `teleport`, overriding that component's own defaults. Passing
 * `undefined` lets each default apply as if the prop had never been written.
 */
withDefaults(defineProps<Omit<DatePickerProps, 'type'>>(), {
  valueFormat: undefined,
  format: undefined,
  placeholder: undefined,
  clearable: undefined,
  minDate: undefined,
  maxDate: undefined,
  seconds: undefined,
  minutesIncrement: undefined,
  is24: undefined,
  weekStart: undefined,
  autoApply: undefined,
  textInput: undefined,
  teleport: undefined,
  disabled: undefined,
  readonly: undefined,
  size: undefined,
  status: undefined,
  id: undefined,
  name: undefined,
  ariaLabel: undefined,
})

/*
 * Every slot is passed straight through to the picker underneath, so this component's
 * slots are whatever the caller hands it. Saying so explicitly is what keeps the
 * declaration honest: left to infer, the slot type is read off a template that
 * enumerates the slots themselves, and TypeScript answers that circle by emitting
 * `any`.
 */
defineSlots<Record<string, (props: Record<string, unknown>) => unknown>>()

const model = defineModel<DatePickerModelValue>({ default: null })
</script>

<template>
  <wx-date-picker v-model="model" type="time" v-bind="$props">
    <template v-for="(_, name) in $slots" #[name]="slotProps">
      <slot :name="name" v-bind="slotProps ?? {}" />
    </template>
  </wx-date-picker>
</template>
