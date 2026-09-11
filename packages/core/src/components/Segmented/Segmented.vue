<script setup lang="ts">
import { computed } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import type { SegmentedEmits, SegmentedOption, SegmentedProps, SegmentedValue } from './types'

defineOptions({ name: 'WxSegmented' })

const props = withDefaults(defineProps<SegmentedProps>(), {
  options: () => [],
  size: 'md',
  block: false,
  disabled: false,
  ariaLabel: undefined,
})

const emit = defineEmits<SegmentedEmits>()

defineSlots<{
  /** A segment's contents, when a label and an icon are not enough. */
  option?: (props: { option: SegmentedOption; selected: boolean }) => unknown
}>()

const model = defineModel<SegmentedValue | undefined>({ default: undefined })

function choose(option: SegmentedOption) {
  if (props.disabled || option.disabled || option.value === model.value) return
  model.value = option.value
  emit('change', option.value)
}

/*
 * A row of radios, not a row of buttons. Choosing one of a few is what a radio group
 * is, and saying so out loud is what gets a screen reader to announce "2 of 4" and
 * the arrow keys to move between them — neither of which a row of buttons gets.
 */
const classes = computed(() => [
  'wx-segmented',
  `wx-segmented--${props.size}`,
  {
    'wx-segmented--block': props.block,
    'is-disabled': props.disabled,
  },
])
</script>

<template>
  <div :class="classes" role="radiogroup" :aria-label="ariaLabel">
    <button
      v-for="option in options"
      :key="option.value"
      type="button"
      role="radio"
      class="wx-segmented__option"
      :class="{ 'is-selected': option.value === model }"
      :aria-checked="option.value === model"
      :aria-label="option.ariaLabel"
      :disabled="disabled || option.disabled"
      :tabindex="option.value === model ? 0 : -1"
      @click="choose(option)"
    >
      <slot name="option" :option="option" :selected="option.value === model">
        <wx-icon v-if="option.icon" :name="option.icon" class="wx-segmented__icon" />
        <span v-if="option.label" class="wx-segmented__label">{{ option.label }}</span>
      </slot>
    </button>
  </div>
</template>

<style scoped>
.wx-segmented {
  display: inline-flex;
  align-items: center;
  box-sizing: border-box;
  gap: 2px;
  max-width: 100%;
  padding: 2px;
  background: var(--wx-bg-fill);
  border-radius: var(--wx-radius-control);
  font-family: var(--wx-font-family-sans);
}

.wx-segmented--block {
  display: flex;
  width: 100%;
}

.wx-segmented--block .wx-segmented__option {
  flex: 1 1 0;
}

.wx-segmented__option {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  gap: var(--wx-space-6);
  min-width: 0;
  height: var(--wx-segmented-height);
  padding-inline: var(--wx-segmented-padding);
  background: transparent;
  border: none;
  /* Two pixels inside the track's own radius, so the corners stay concentric. */
  border-radius: calc(var(--wx-radius-control) - 2px);
  color: var(--wx-text-muted);
  font-family: inherit;
  font-size: var(--wx-segmented-text);
  font-weight: var(--wx-font-weight-medium);
  line-height: 1;
  white-space: nowrap;
  cursor: pointer;
  transition:
    background var(--wx-duration-fast) var(--wx-easing-standard),
    color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-segmented--sm {
  --wx-segmented-height: calc(var(--wx-size-control-sm) - 4px);
  --wx-segmented-padding: var(--wx-space-10);
  --wx-segmented-text: var(--wx-font-size-control-sm);
}

.wx-segmented--md {
  --wx-segmented-height: calc(var(--wx-size-control-md) - 4px);
  --wx-segmented-padding: var(--wx-space-12);
  --wx-segmented-text: var(--wx-font-size-control-md);
}

.wx-segmented--lg {
  --wx-segmented-height: calc(var(--wx-size-control-lg) - 4px);
  --wx-segmented-padding: var(--wx-space-16);
  --wx-segmented-text: var(--wx-font-size-control-lg);
}

.wx-segmented__option:hover:not(:disabled):not(.is-selected) {
  color: var(--wx-text-default);
}

.wx-segmented__option:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

/* The chosen segment is the one lifted out of the track. */
.wx-segmented__option.is-selected {
  background: var(--wx-bg-surface);
  box-shadow: var(--wx-shadow-card);
  color: var(--wx-text-strong);
}

.wx-segmented__option:disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-segmented__label {
  overflow: hidden;
  text-overflow: ellipsis;
}

.wx-segmented__icon {
  flex: 0 0 auto;
}

@media (prefers-reduced-motion: reduce) {
  .wx-segmented__option {
    transition: none;
  }
}
</style>
