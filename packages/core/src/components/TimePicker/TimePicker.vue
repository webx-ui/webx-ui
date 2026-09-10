<script setup lang="ts">
import WxDatePicker from '../DatePicker/DatePicker.vue'
import type { DatePickerModelValue, DatePickerProps } from '../DatePicker/types'

defineOptions({ name: 'WxTimePicker' })

defineProps<Omit<DatePickerProps, 'type'>>()

const model = defineModel<DatePickerModelValue>({ default: null })
</script>

<template>
  <wx-date-picker v-model="model" type="time" v-bind="$props">
    <template v-for="(_, name) in $slots" #[name]="slotProps">
      <slot :name="name" v-bind="slotProps ?? {}" />
    </template>
  </wx-date-picker>
</template>
