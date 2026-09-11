<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import WxStatistic from '../Statistic/Statistic.vue'
import { formatCountdown, toTimestamp } from './format'
import type { CountdownEmits, CountdownProps } from './types'

defineOptions({ name: 'WxCountdown' })

const props = withDefaults(defineProps<CountdownProps>(), {
  format: 'HH:mm:ss',
  interval: 1000,
  title: undefined,
  prefix: undefined,
  suffix: undefined,
  size: 'md',
  tone: 'strong',
  align: undefined,
})

const emit = defineEmits<CountdownEmits>()

const target = computed(() => toTimestamp(props.value))
const remaining = ref(Math.max(0, target.value - Date.now()))

let timer: ReturnType<typeof setInterval> | undefined

function stop() {
  if (timer === undefined) return
  clearInterval(timer)
  timer = undefined
}

function tick() {
  const left = Math.max(0, target.value - Date.now())
  remaining.value = left
  emit('change', left)

  /* `finish` fires once: the timer stops before the next tick can repeat it. */
  if (left === 0) {
    stop()
    emit('finish')
  }
}

function start() {
  stop()
  remaining.value = Math.max(0, target.value - Date.now())
  if (remaining.value === 0) return
  timer = setInterval(tick, Math.max(16, props.interval))
}

/* A new target — or a new tick rate — restarts the clock rather than drifting. */
watch([target, () => props.interval], start, { immediate: true })

onBeforeUnmount(stop)

const text = computed(() => formatCountdown(remaining.value, props.format))

defineExpose({ remaining })
</script>

<template>
  <wx-statistic
    class="wx-countdown"
    :value="text"
    :title="title"
    :prefix="prefix"
    :suffix="suffix"
    :size="size"
    :tone="tone"
    :align="align"
  >
    <template v-if="$slots.title" #title><slot name="title" /></template>
    <template v-if="$slots.prefix" #prefix><slot name="prefix" /></template>
    <template v-if="$slots.suffix" #suffix><slot name="suffix" /></template>
    <template v-if="$slots.value" #value
      ><slot name="value" :remaining="remaining" :text="text"
    /></template>
    <template v-if="$slots.default" #default><slot /></template>
  </wx-statistic>
</template>
