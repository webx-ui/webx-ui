<script setup lang="ts">
import { computed, provide } from 'vue'
import { breadcrumbKey } from '../../composables/useBreadcrumb'
import type { BreadcrumbProps } from './types'

defineOptions({ name: 'WxBreadcrumb' })

const props = withDefaults(defineProps<BreadcrumbProps>(), {
  separator: '/',
  separatorIcon: undefined,
  size: 'md',
  label: 'Breadcrumb',
})

provide(breadcrumbKey, {
  separator: computed(() => props.separator),
  separatorIcon: computed(() => props.separatorIcon),
  size: computed(() => props.size),
})
</script>

<template>
  <nav class="wx-breadcrumb" :class="`wx-breadcrumb--${size}`" :aria-label="label">
    <!-- An ordered list, because a trail read out of order is not a trail. -->
    <ol class="wx-breadcrumb__list">
      <slot />
    </ol>
  </nav>
</template>

<style scoped>
.wx-breadcrumb {
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-tight);
}

.wx-breadcrumb--sm {
  font-size: var(--wx-font-size-xs);
}

.wx-breadcrumb__list {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-6);
  margin: 0;
  padding: 0;
  list-style: none;
}
</style>
