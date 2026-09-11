<script setup lang="ts">
import { computed } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import type { EmptyProps } from './types'

defineOptions({ name: 'WxEmpty' })

const props = withDefaults(defineProps<EmptyProps>(), {
  icon: 'file',
  title: undefined,
  description: undefined,
  size: 'md',
  plain: false,
})

defineSlots<{
  /** Replaces the glyph — an illustration, a logo, whatever says it better. */
  icon?: () => unknown
  title?: () => unknown
  /** The explanation. Overrides `description`. */
  default?: () => unknown
  /** What to do about it: a button that clears the filter, one that adds a record. */
  actions?: () => unknown
}>()

const classes = computed(() => ['wx-empty', `wx-empty--${props.size}`])
</script>

<template>
  <div :class="classes">
    <div v-if="!plain" class="wx-empty__icon" aria-hidden="true">
      <slot name="icon">
        <wx-icon :name="icon" />
      </slot>
    </div>

    <p v-if="title || $slots.title" class="wx-empty__title">
      <slot name="title">{{ title }}</slot>
    </p>

    <p v-if="description || $slots.default" class="wx-empty__description">
      <slot>{{ description }}</slot>
    </p>

    <div v-if="$slots.actions" class="wx-empty__actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<style scoped>
.wx-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  gap: var(--wx-empty-gap);
  padding: var(--wx-empty-padding);
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-sans);
  text-align: center;
}

.wx-empty--sm {
  --wx-empty-gap: var(--wx-space-6);
  --wx-empty-padding: var(--wx-space-16) var(--wx-space-12);
  --wx-empty-glyph: 24px;
}

.wx-empty--md {
  --wx-empty-gap: var(--wx-space-8);
  --wx-empty-padding: var(--wx-space-32) var(--wx-space-16);
  --wx-empty-glyph: 36px;
}

.wx-empty--lg {
  --wx-empty-gap: var(--wx-space-12);
  --wx-empty-padding: var(--wx-space-64) var(--wx-space-24);
  --wx-empty-glyph: 48px;
}

/*
 * The glyph is decoration, not information — the words below it carry the message,
 * so it is dimmed rather than drawn in a colour that asks to be looked at.
 */
.wx-empty__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--wx-text-placeholder);
}

.wx-empty__icon :deep(.wx-icon) {
  width: var(--wx-empty-glyph);
  height: var(--wx-empty-glyph);
}

.wx-empty__title {
  margin: 0;
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-empty__description {
  max-width: 42ch;
  margin: 0;
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
}

.wx-empty--lg .wx-empty__title {
  font-size: var(--wx-font-size-lg);
}

.wx-empty__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: var(--wx-space-8);
  margin-top: var(--wx-space-4);
}
</style>
