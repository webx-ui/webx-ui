<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { WxButton, WxDatePicker, WxInput, WxSelect } from '@webx-ui/core'
import { useDeliveryWords } from '../i18n'

/**
 * `wx-delivery-schedule`: the opening hours of a point (§7.4) — the week, a row a day, and the
 * dates that differ. A day is «not said», «closed», «around the clock» or hours written as
 * `09:00–13:00, 14:00–18:00`: what a person reads off a sign on the door, typed the same way.
 * The value is the shape `delivery_points.schedule` keeps; the server checks every interval.
 */
defineOptions({ name: 'WxDeliveryScheduleField' })

type Interval = [string, string]

interface ScheduleValue {
  timezone?: string
  week?: Record<string, Interval[] | null>
  exceptions?: { date: string; hours: Interval[] }[]
  text?: Record<string, string>
}

type Mode = 'unknown' | 'closed' | 'hours' | 'around'

const props = withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false })

const value = defineModel<ScheduleValue | null>({ default: null })

const t = useDeliveryWords()

const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as const

const modes = computed(() => [
  { value: 'unknown', label: t('schedule.not-said') },
  { value: 'closed', label: t('schedule.closed') },
  { value: 'hours', label: t('schedule.hours') },
  { value: 'around', label: t('schedule.around') },
])

function written(hours: Interval[]): string {
  return hours.map(([from, to]) => `${from}–${to}`).join(', ')
}

/** «09:00-13:00, 14:00–18:00» into intervals; `null` when a part does not read. */
function read(text: string): Interval[] | null {
  const parts = text
    .split(/[,;]/)
    .map((part) => part.trim())
    .filter((part) => part !== '')
  const intervals: Interval[] = []

  for (const part of parts) {
    const match = /^(\d{1,2})[:.](\d{2})\s*[-–—]\s*(\d{1,2})[:.](\d{2})$/.exec(part)

    if (!match) return null

    const pad = (hour: string) => hour.padStart(2, '0')
    intervals.push([`${pad(match[1]!)}:${match[2]}`, `${pad(match[3]!)}:${match[4]}`])
  }

  return intervals
}

function modeOf(hours: Interval[] | null | undefined): Mode {
  if (hours === null || hours === undefined) return 'unknown'
  if (hours.length === 0) return 'closed'
  if (hours.length === 1 && hours[0]![0] === '00:00' && hours[0]![1] === '24:00') return 'around'

  return 'hours'
}

/* What is typed in each row, ahead of the value: a half-written «09:00–1» is kept on screen. */
const texts = ref<Record<string, string>>({})
const invalid = ref<Record<string, boolean>>({})

watch(
  value,
  (now) => {
    for (const day of DAYS) {
      const hours = now?.week?.[day]

      if (modeOf(hours) === 'hours' && !invalid.value[day]) texts.value[day] = written(hours!)
    }
  },
  { immediate: true },
)

function write(change: (draft: ScheduleValue) => void): void {
  const draft: ScheduleValue = JSON.parse(JSON.stringify(value.value ?? {}))

  change(draft)

  const empty =
    Object.keys(draft.week ?? {}).length === 0 &&
    (draft.exceptions ?? []).length === 0 &&
    Object.keys(draft.text ?? {}).length === 0

  value.value = empty ? null : draft
}

function setMode(day: string, mode: Mode): void {
  write((draft) => {
    draft.week ??= {}

    if (mode === 'unknown') delete draft.week[day]
    else if (mode === 'closed') draft.week[day] = []
    else if (mode === 'around') draft.week[day] = [['00:00', '24:00']]
    else {
      const hours = read(texts.value[day] ?? '')
      draft.week[day] = hours && hours.length > 0 ? hours : [['09:00', '18:00']]
      texts.value[day] = written(draft.week[day]!)
    }
  })
}

function setHours(day: string, text: string): void {
  texts.value[day] = text

  const hours = read(text)

  invalid.value[day] = hours === null || hours.length === 0

  if (!invalid.value[day]) write((draft) => ((draft.week ??= {})[day] = hours))
}

const exceptions = computed(() => value.value?.exceptions ?? [])

function addException(): void {
  write((draft) => {
    draft.exceptions = [
      ...(draft.exceptions ?? []),
      { date: new Date().toISOString().slice(0, 10), hours: [] },
    ]
  })
}

function setException(index: number, change: Partial<{ date: string; text: string }>): void {
  write((draft) => {
    const one = draft.exceptions?.[index]

    if (!one) return
    if (change.date !== undefined) one.date = change.date
    if (change.text !== undefined) one.hours = read(change.text) ?? one.hours
  })
}

function removeException(index: number): void {
  write((draft) => {
    draft.exceptions = (draft.exceptions ?? []).filter((_, at) => at !== index)
  })
}
</script>

<template>
  <div class="wx-delivery-schedule">
    <div v-for="day in DAYS" :key="day" class="wx-delivery-schedule__day">
      <span class="wx-delivery-schedule__name">{{ t(`schedule.days.${day}`) }}</span>
      <wx-select
        class="wx-delivery-schedule__mode"
        size="sm"
        :model-value="modeOf(value?.week?.[day])"
        :options="modes"
        :aria-label="t(`schedule.days.${day}`)"
        :disabled="props.disabled"
        @update:model-value="setMode(day, $event as Mode)"
      />
      <wx-input
        v-if="modeOf(value?.week?.[day]) === 'hours'"
        class="wx-delivery-schedule__hours"
        size="sm"
        :model-value="texts[day] ?? ''"
        :status="invalid[day] ? 'error' : undefined"
        :aria-label="t('schedule.hours')"
        placeholder="09:00–18:00"
        :disabled="props.disabled"
        @update:model-value="setHours(day, String($event ?? ''))"
      />
    </div>

    <div class="wx-delivery-schedule__exceptions">
      <span class="wx-delivery-schedule__title">{{ t('schedule.exceptions') }}</span>

      <div v-for="(one, index) in exceptions" :key="index" class="wx-delivery-schedule__day">
        <wx-date-picker
          class="wx-delivery-schedule__mode"
          size="sm"
          :model-value="one.date"
          :aria-label="t('schedule.date')"
          :disabled="props.disabled"
          @update:model-value="setException(index, { date: String($event ?? '') })"
        />
        <wx-input
          class="wx-delivery-schedule__hours"
          size="sm"
          :model-value="one.hours.length === 0 ? '' : written(one.hours)"
          :placeholder="t('schedule.closed')"
          :aria-label="t('schedule.hours')"
          :disabled="props.disabled"
          @change="setException(index, { text: String($event ?? '') })"
        />
        <wx-button
          size="sm"
          variant="text"
          icon="trash"
          :aria-label="t('schedule.remove')"
          :disabled="props.disabled"
          @click="removeException(index)"
        />
      </div>

      <wx-button
        v-if="!props.disabled"
        size="sm"
        variant="outline"
        icon="plus"
        class="wx-delivery-schedule__add"
        @click="addException"
      >
        {{ t('schedule.add-exception') }}
      </wx-button>
    </div>
  </div>
</template>

<style scoped>
.wx-delivery-schedule {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  width: 100%;
  min-width: 0;
  container-type: inline-size;
}

.wx-delivery-schedule__day {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-delivery-schedule__name {
  flex: 0 0 96px;
  font-size: var(--wx-font-size-sm);
}

.wx-delivery-schedule__day > :deep(.wx-select),
.wx-delivery-schedule__mode {
  flex: 0 1 180px;
  min-width: 0;
}

.wx-delivery-schedule__hours {
  flex: 1 1 160px;
  min-width: 0;
}

.wx-delivery-schedule__exceptions {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  margin-top: var(--wx-space-8);
}

.wx-delivery-schedule__title {
  font-weight: var(--wx-font-weight-medium);
}

.wx-delivery-schedule__add {
  align-self: flex-start;
}

@container (max-width: 360px) {
  .wx-delivery-schedule__name {
    flex-basis: 100%;
  }
}
</style>
