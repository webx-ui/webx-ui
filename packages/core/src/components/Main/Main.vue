<script setup lang="ts">
import { computed } from 'vue'
import type { MainProps } from './types'

defineOptions({ name: 'WxMain' })

const props = withDefaults(defineProps<MainProps>(), {
  padding: 'md',
  scroll: false,
  maxWidth: undefined,
})

const maxWidth = computed(() => {
  if (props.maxWidth === undefined) return undefined
  return typeof props.maxWidth === 'number' ? `${props.maxWidth}px` : props.maxWidth
})

const classes = computed(() => [
  'wx-main',
  `wx-main--padding-${props.padding}`,
  { 'wx-main--scroll': props.scroll },
])
</script>

<template>
  <main :class="classes" :style="maxWidth ? { '--wx-main-max-width': maxWidth } : undefined">
    <!--
      The inner element is what the cap applies to, so the padding and the background
      still run the full width of the column.
    -->
    <div class="wx-main__inner">
      <slot />
    </div>
  </main>
</template>

<style scoped>
.wx-main {
  box-sizing: border-box;
  flex: 1 1 auto;
  min-width: 0;
  min-height: 0;
  padding: var(--wx-main-padding, var(--wx-space-24));
  background: var(--wx-bg-body);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
}

.wx-main--padding-none {
  --wx-main-padding: 0px;
}

.wx-main--padding-sm {
  --wx-main-padding: var(--wx-space-12);
}

.wx-main--padding-md {
  --wx-main-padding: var(--wx-space-24);
}

.wx-main--padding-lg {
  --wx-main-padding: var(--wx-space-40);
}

.wx-main--scroll {
  overflow-y: auto;
}

.wx-main__inner {
  max-width: var(--wx-main-max-width, none);
  /* The cap is centred, but only once there is a cap to centre. */
  margin-inline: auto;
}

/*
 * Padding is the first thing to give way on a small screen: 24px of margin around a
 * form is air on a desktop and a third of the line on a phone.
 */
@media (max-width: 640px) {
  .wx-main--padding-lg {
    --wx-main-padding: var(--wx-space-16);
  }

  .wx-main--padding-md {
    --wx-main-padding: var(--wx-space-16);
  }

  .wx-main--padding-sm {
    --wx-main-padding: var(--wx-space-8);
  }
}

@media (max-width: 420px) {
  .wx-main--padding-lg,
  .wx-main--padding-md {
    --wx-main-padding: var(--wx-space-12);
  }
}
</style>
