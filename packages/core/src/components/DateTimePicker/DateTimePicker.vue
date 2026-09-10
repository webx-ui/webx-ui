<script setup lang="ts">
import WxDatePicker from '../DatePicker/DatePicker.vue'
import type { DatePickerModelValue, DatePickerProps } from '../DatePicker/types'

defineOptions({ name: 'WxDateTimePicker' })

defineProps<Omit<DatePickerProps, 'type'>>()

const model = defineModel<DatePickerModelValue>({ default: null })
</script>

<template>
  <wx-date-picker v-model="model" type="datetime" v-bind="$props">
    <template v-for="(_, name) in $slots" #[name]="slotProps">
      <slot :name="name" v-bind="slotProps ?? {}" />
    </template>
  </wx-date-picker>
</template>
