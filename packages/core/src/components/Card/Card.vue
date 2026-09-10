<script setup lang="ts">
import { computed, useSlots } from 'vue'
import type { CardProps } from './types'

defineOptions({ name: 'WxCard' })

const props = withDefaults(defineProps<CardProps>(), {
  title: undefined,
  shadow: 'always',
  padding: 'md',
  bordered: false,
})

const slots = useSlots()

const hasHeader = computed(() => Boolean(props.title || slots.header || slots.extra))

const classes = computed(() => [
  'wx-card',
  `wx-card--shadow-${props.shadow}`,
  `wx-card--padding-${props.padding}`,
  { 'wx-card--bordered': props.bordered },
])
</script>

<template>
  <section :class="classes">
    <header v-if="hasHeader" class="wx-card__header">
      <div class="wx-card__title">
        <slot name="header">{{ title }}</slot>
      </div>
      <div v-if="$slots.extra" class="wx-card__extra">
        <slot name="extra" />
      </div>
    </header>

    <div class="wx-card__body">
      <slot />
    </div>

    <footer v-if="$slots.footer" class="wx-card__footer">
      <slot name="footer" />
    </footer>
  </section>
</template>

<style scoped>
.wx-card {
  display: flex;
  flex-direction: column;
  box-sizing: border-box;
  background: var(--wx-bg-surface);
  border: 1px solid transparent;
  border-radius: var(--wx-radius-md);
  color: var(--wx-text-default);
  transition: box-shadow var(--wx-duration-normal) var(--wx-easing-standard);
}

.wx-card--bordered {
  border-color: var(--wx-border-default);
}

.wx-card--shadow-always {
  box-shadow: var(--wx-shadow-card);
}

.wx-card--shadow-hover:hover {
  box-shadow: var(--wx-shadow-card);
}

.wx-card__header,
.wx-card__body,
.wx-card__footer {
  padding: var(--wx-card-padding, var(--wx-space-16));
}

.wx-card--padding-none {
  --wx-card-padding: 0px;
}

.wx-card--padding-sm {
  --wx-card-padding: var(--wx-space-12);
}

.wx-card--padding-md {
  --wx-card-padding: var(--wx-space-16);
}

.wx-card--padding-lg {
  --wx-card-padding: var(--wx-space-24);
}

.wx-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-16);
  padding-bottom: 0;
}

.wx-card__title {
  min-width: 0;
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-lg);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
}

.wx-card__extra {
  flex: 0 0 auto;
  color: var(--wx-text-muted);
}

.wx-card__body {
  flex: 1 1 auto;
}

.wx-card__footer {
  padding-top: 0;
}
</style>
