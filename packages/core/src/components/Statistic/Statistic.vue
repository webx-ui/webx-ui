<script setup lang="ts">
import { computed } from 'vue'
import type { StatisticProps } from './types'

defineOptions({ name: 'WxStatistic' })

const props = withDefaults(defineProps<StatisticProps>(), {
  value: undefined,
  title: undefined,
  precision: undefined,
  locale: undefined,
  grouping: true,
  prefix: undefined,
  suffix: undefined,
  formatter: undefined,
  size: 'md',
  tone: 'strong',
  align: undefined,
})

const display = computed(() => {
  if (props.formatter) return props.formatter(props.value)
  if (typeof props.value !== 'number') return props.value ?? ''

  /*
   * `Intl` rather than a hand-rolled separator: 268 500 in one locale is 268,500 in
   * another, and an admin panel that ships in several languages should not decide.
   */
  return new Intl.NumberFormat(props.locale, {
    useGrouping: props.grouping,
    minimumFractionDigits: props.precision,
    maximumFractionDigits: props.precision,
  }).format(props.value)
})

const classes = computed(() => [
  'wx-statistic',
  `wx-statistic--${props.size}`,
  `wx-statistic--tone-${props.tone}`,
  { [`wx-statistic--align-${props.align}`]: Boolean(props.align) },
])
</script>

<template>
  <div :class="classes">
    <div v-if="title || $slots.title" class="wx-statistic__title">
      <slot name="title">{{ title }}</slot>
    </div>

    <div class="wx-statistic__value">
      <span v-if="prefix || $slots.prefix" class="wx-statistic__affix">
        <slot name="prefix">{{ prefix }}</slot>
      </span>

      <span class="wx-statistic__number">
        <slot name="value">{{ display }}</slot>
      </span>

      <span v-if="suffix || $slots.suffix" class="wx-statistic__affix">
        <slot name="suffix">{{ suffix }}</slot>
      </span>
    </div>

    <div v-if="$slots.default" class="wx-statistic__footer">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.wx-statistic {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

.wx-statistic--align-center {
  text-align: center;
  align-items: center;
}

.wx-statistic--align-end {
  text-align: end;
  align-items: flex-end;
}

.wx-statistic--align-start {
  text-align: start;
  align-items: flex-start;
}

.wx-statistic--align-justify {
  text-align: justify;
}

.wx-statistic__title {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
}

.wx-statistic--sm .wx-statistic__title {
  font-size: var(--wx-font-size-xs);
}

.wx-statistic__value {
  display: flex;
  align-items: baseline;
  gap: var(--wx-space-4);
  color: var(--wx-statistic-color, var(--wx-text-strong));
  /* Digits of equal width, so a ticking number does not jitter. */
  font-variant-numeric: tabular-nums;
  font-weight: var(--wx-font-weight-semibold);
  line-height: var(--wx-font-line-height-tight);
}

.wx-statistic--sm .wx-statistic__value {
  font-size: var(--wx-font-size-lg);
}

.wx-statistic--md .wx-statistic__value {
  font-size: var(--wx-font-size-2xl);
}

.wx-statistic--lg .wx-statistic__value {
  font-size: var(--wx-font-size-3xl);
}

.wx-statistic__number {
  min-width: 0;
}

.wx-statistic__affix {
  color: var(--wx-text-muted);
  font-size: 0.65em;
  font-weight: var(--wx-font-weight-medium);
}

.wx-statistic__footer {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

/* tones */
.wx-statistic--tone-default {
  --wx-statistic-color: var(--wx-text-default);
}

.wx-statistic--tone-strong {
  --wx-statistic-color: var(--wx-text-strong);
}

.wx-statistic--tone-muted {
  --wx-statistic-color: var(--wx-text-muted);
}

.wx-statistic--tone-placeholder {
  --wx-statistic-color: var(--wx-text-placeholder);
}

.wx-statistic--tone-primary {
  --wx-statistic-color: var(--wx-color-primary);
}

.wx-statistic--tone-success {
  --wx-statistic-color: var(--wx-color-success-active);
}

.wx-statistic--tone-warning {
  --wx-statistic-color: var(--wx-color-warning-active);
}

.wx-statistic--tone-danger {
  --wx-statistic-color: var(--wx-color-danger);
}

.wx-statistic--tone-info {
  --wx-statistic-color: var(--wx-color-info-active);
}

.wx-statistic--tone-inverse {
  --wx-statistic-color: var(--wx-text-inverse);
}
</style>
