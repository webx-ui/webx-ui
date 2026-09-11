<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import WxSkeletonItem from '../SkeletonItem/SkeletonItem.vue'
import type { SkeletonProps } from './types'

defineOptions({ name: 'WxSkeleton' })

const props = withDefaults(defineProps<SkeletonProps>(), {
  loading: true,
  rows: 3,
  avatar: false,
  title: false,
  animated: true,
  delay: 0,
})

defineSlots<{
  /** The real thing, once it has arrived. */
  default?: () => unknown
  /** A placeholder shaped like the real thing, instead of the rows. */
  template?: () => unknown
}>()

/*
 * A request that answers quickly should not flash a skeleton on the way past. The
 * delay is on showing it, never on hiding it: content that has arrived is shown at
 * once, and a placeholder that has appeared stays long enough to be read as one.
 */
const showing = ref(props.delay === 0 && props.loading)

let timer: ReturnType<typeof setTimeout> | undefined

watch(
  [() => props.loading, () => props.delay],
  ([loading, delay]) => {
    clearTimeout(timer)

    if (!loading) {
      showing.value = false
      return
    }

    if (delay === 0) {
      showing.value = true
      return
    }

    timer = setTimeout(() => {
      showing.value = true
    }, delay)
  },
  { immediate: true },
)

onBeforeUnmount(() => clearTimeout(timer))

const lines = computed(() => Math.max(0, props.rows))
</script>

<template>
  <div v-if="showing" class="wx-skeleton" aria-busy="true" aria-live="polite">
    <slot name="template">
      <div class="wx-skeleton__row">
        <wx-skeleton-item v-if="avatar" variant="circle" :animated="animated" />

        <div class="wx-skeleton__lines">
          <wx-skeleton-item v-if="title" variant="title" :animated="animated" />
          <wx-skeleton-item
            v-for="line in lines"
            :key="line"
            variant="text"
            :animated="animated"
            :class="{ 'wx-skeleton__last': line === lines && lines > 1 }"
          />
        </div>
      </div>
    </slot>
  </div>

  <slot v-else />
</template>

<style scoped>
.wx-skeleton {
  width: 100%;
}

.wx-skeleton__row {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-12);
}

.wx-skeleton__lines {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

/* The last line stops short, the way a paragraph does. */
.wx-skeleton__last {
  width: 62%;
}
</style>
