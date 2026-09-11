<script setup lang="ts">
import { computed, provide } from 'vue'
import { timelineKey } from '../../composables/useTimeline'
import type { TimelineProps } from './types'

defineOptions({ name: 'WxTimeline' })

const props = withDefaults(defineProps<TimelineProps>(), {
  size: 'md',
})

provide(timelineKey, { size: computed(() => props.size) })
</script>

<template>
  <ul class="wx-timeline" :class="`wx-timeline--${size}`">
    <slot />
  </ul>
</template>

<style scoped>
.wx-timeline {
  --wx-timeline-dot: 12px;
  --wx-timeline-gap: var(--wx-space-18);

  margin: 0;
  padding: 0;
  list-style: none;
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

.wx-timeline--sm {
  --wx-timeline-dot: 9px;
  --wx-timeline-gap: var(--wx-space-12);
}
</style>
