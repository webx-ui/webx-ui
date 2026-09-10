<script setup lang="ts">
import { ref } from 'vue'
import { WxDatePicker, WxDateTimePicker, WxFormItem, WxTimePicker } from '@webx-ui/core'

const publishedAt = ref('2026-03-14')
const meetingAt = ref('2026-03-14 09:30')
const opensAt = ref('09:00')
const deadline = ref<string | null>(null)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Date</span>
      <wx-date-picker v-model="publishedAt" placeholder="Pick a date" />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ publishedAt ?? 'null' }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Date and time</span>
      <wx-date-time-picker v-model="meetingAt" placeholder="Pick a moment" />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ meetingAt ?? 'null' }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Time</span>
      <wx-time-picker v-model="opensAt" :minutes-increment="15" placeholder="Opening time" />
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Model: <code>{{ opensAt ?? 'null' }}</code>
      </p>
    </div>

    <div>
      <span class="wx-demo__label">In a form item, with an error and a bound range</span>
      <wx-form-item
        label="Deadline"
        error="The deadline field is required."
        help="Cannot be in the past"
      >
        <wx-date-picker v-model="deadline" min-date="2026-01-01" placeholder="Pick a date" />
      </wx-form-item>
    </div>

    <div>
      <span class="wx-demo__label">Disabled</span>
      <wx-date-picker model-value="2026-03-14" disabled />
    </div>
  </div>
</template>
