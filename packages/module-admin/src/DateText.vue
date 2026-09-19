<script setup lang="ts">
import { computed } from 'vue'
import { WxText, WxTooltip, type TextSize, type TextTone } from '@webx-ui/core'
import { useDates, type DateLike } from './dates'

defineOptions({ name: 'WxDate' })

/**
 * When something happened, the way the whole panel says it (§21).
 *
 * The short line is what the column holds — "today at 08:10" is read at a glance where
 * "17.09.2026, 08:10" is read twice. The second down to the second stays in the tip, because
 * the question "when exactly" is asked rarely and answered badly by a column.
 *
 * Its own tip rather than `title`: the native one waits a second, cannot be styled, and is
 * invisible to anybody who is not holding a mouse (§16).
 */
const props = withDefaults(
  defineProps<{
    /** What the server sent. `null` is a date that never happened, not a missing one. */
    value: DateLike
    /**
     * The column form: the time for today, a short month for the rest of the year, digits before
     * that. What it leaves out is in the tip, which is the whole point of having one.
     */
    compact?: boolean
    size?: TextSize
    tone?: TextTone
  }>(),
  {
    compact: false,
    // A date is context beside whatever the row is actually about, never the thing itself.
    size: 'sm',
    tone: 'muted',
  },
)

const dates = useDates()

const text = computed(() => (props.compact ? dates.compact(props.value) : dates.short(props.value)))
const exact = computed(() => dates.exact(props.value))
const iso = computed(() => dates.iso(props.value))
</script>

<template>
  <wx-tooltip v-if="exact" :content="exact">
    <wx-text as="time" :datetime="iso" :size="size" :tone="tone">{{ text }}</wx-text>
  </wx-tooltip>
  <wx-text v-else :size="size" :tone="tone">{{ text }}</wx-text>
</template>
