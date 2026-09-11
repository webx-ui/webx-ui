<script setup lang="ts">
import { computed, useSlots } from 'vue'
import type { EntityCardEmits, EntityCardProps } from './types'

defineOptions({ name: 'WxEntityCard' })

const props = withDefaults(defineProps<EntityCardProps>(), {
  title: undefined,
  href: undefined,
  subtitle: undefined,
  image: undefined,
  imageAlt: undefined,
  shape: 'rounded',
  imageSize: undefined,
  meta: () => [],
  size: 'md',
  variant: 'card',
  bordered: false,
  selected: false,
})

const emit = defineEmits<EntityCardEmits>()

const slots = useSlots()

const defaultImageSize: Record<string, string> = { sm: '32px', md: '44px', lg: '64px' }

const imageSize = computed(() => {
  if (props.imageSize === undefined) return defaultImageSize[props.size]
  return typeof props.imageSize === 'number' ? `${props.imageSize}px` : props.imageSize
})

const hasMedia = computed(() => Boolean(props.image || slots.media))

/** With no picture, the first letter stands in — a list of rows stays aligned. */
const initial = computed(() => props.title?.trim().charAt(0).toUpperCase() ?? '')

const hasMeta = computed(() => Boolean(slots.meta || props.meta.length > 0))

const classes = computed(() => [
  'wx-entity-card',
  `wx-entity-card--${props.size}`,
  `wx-entity-card--${props.variant}`,
  {
    'wx-entity-card--bordered': props.bordered,
    'is-selected': props.selected,
  },
])
</script>

<template>
  <div
    :class="classes"
    :style="{ '--wx-entity-card-image': imageSize }"
    @click="emit('click', $event)"
  >
    <div v-if="hasMedia" class="wx-entity-card__media" :class="`wx-entity-card__media--${shape}`">
      <slot name="media">
        <img :src="image" :alt="imageAlt ?? title ?? ''" class="wx-entity-card__image" />
      </slot>
    </div>
    <div
      v-else-if="title"
      class="wx-entity-card__media wx-entity-card__media--empty"
      :class="`wx-entity-card__media--${shape}`"
      aria-hidden="true"
    >
      {{ initial }}
    </div>

    <div class="wx-entity-card__body">
      <component
        :is="href ? 'a' : 'span'"
        v-if="title || $slots.title"
        class="wx-entity-card__title"
        :href="href"
      >
        <slot name="title">{{ title }}</slot>
      </component>

      <span v-if="subtitle || $slots.subtitle" class="wx-entity-card__subtitle">
        <slot name="subtitle">{{ subtitle }}</slot>
      </span>

      <div v-if="hasMeta" class="wx-entity-card__meta">
        <slot name="meta">
          <span v-for="(item, index) in meta" :key="index" class="wx-entity-card__meta-item">
            <span v-if="item.label" class="wx-entity-card__meta-label">{{ item.label }}:</span>
            <span v-if="item.text" class="wx-entity-card__meta-text">{{ item.text }}</span>
          </span>
        </slot>
      </div>

      <div v-if="$slots.default" class="wx-entity-card__content">
        <slot />
      </div>
    </div>

    <div v-if="$slots.actions" class="wx-entity-card__actions" @click.stop>
      <slot name="actions" />
    </div>
  </div>
</template>

<style scoped>
.wx-entity-card {
  display: flex;
  align-items: center;
  /* Wrapping is what makes a narrow card work: the actions drop to their own line
     instead of squeezing the title into two characters. */
  flex-wrap: wrap;
  gap: var(--wx-space-12);
  box-sizing: border-box;
  padding: var(--wx-entity-card-padding, var(--wx-space-12));
  border: 1px solid transparent;
  border-radius: var(--wx-radius-md);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  transition:
    background-color var(--wx-duration-fast) var(--wx-easing-standard),
    border-color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-entity-card--sm {
  --wx-entity-card-padding: var(--wx-space-8);
  gap: var(--wx-space-8);
}

.wx-entity-card--lg {
  --wx-entity-card-padding: var(--wx-space-16);
  gap: var(--wx-space-16);
}

.wx-entity-card--card {
  background: var(--wx-bg-surface);
  box-shadow: var(--wx-shadow-card);
}

.wx-entity-card--plain {
  padding: 0;
  background: transparent;
}

.wx-entity-card--bordered {
  border-color: var(--wx-border-default);
}

.wx-entity-card.is-selected {
  border-color: var(--wx-color-primary);
  background: var(--wx-color-primary-soft);
}

.wx-entity-card__media {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: var(--wx-entity-card-image, 44px);
  height: var(--wx-entity-card-image, 44px);
  overflow: hidden;
  background: var(--wx-bg-fill);
}

.wx-entity-card__media--rounded {
  border-radius: var(--wx-radius-xs);
}

.wx-entity-card__media--square {
  border-radius: 0;
}

.wx-entity-card__media--circle {
  border-radius: var(--wx-radius-full);
}

.wx-entity-card__media--empty {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-lg);
  font-weight: var(--wx-font-weight-semibold);
  user-select: none;
}

.wx-entity-card__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.wx-entity-card__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  /* The basis is the point where the actions wrap; `min-width: 0` is what lets a long
     title be cut rather than stretch the row. */
  flex: 1 1 180px;
  min-width: 0;
}

.wx-entity-card__title {
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-md);
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
  text-decoration: none;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-entity-card--sm .wx-entity-card__title {
  font-size: var(--wx-font-size-sm);
}

a.wx-entity-card__title:hover {
  color: var(--wx-text-link);
}

.wx-entity-card__subtitle {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-entity-card__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-12);
  margin-top: 2px;
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-xs);
  line-height: var(--wx-font-line-height-normal);
}

.wx-entity-card__meta-item {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-entity-card__meta-label {
  color: var(--wx-text-muted);
}

.wx-entity-card__meta-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-entity-card__content {
  margin-top: var(--wx-space-8);
  font-size: var(--wx-font-size-sm);
}

.wx-entity-card__actions {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex: 0 0 auto;
  margin-left: auto;
}
</style>
