<script setup lang="ts">
import { computed } from 'vue'
import type { ColBreakpoint, ColProps } from './types'

defineOptions({ name: 'WxCol' })

const props = withDefaults(defineProps<ColProps>(), {
  span: 24,
  offset: undefined,
  sm: undefined,
  md: undefined,
  lg: undefined,
  xl: undefined,
  order: undefined,
  as: 'div',
})

const breakpoints = ['sm', 'md', 'lg', 'xl'] as const

function normalize(value: ColBreakpoint) {
  return typeof value === 'number' ? { span: value } : value
}

/**
 * Every width is published as a variable and the media queries in the stylesheet
 * pick which one wins, each falling back to the breakpoint below it. That keeps the
 * component down to one rule per breakpoint instead of the 24 spans × 4 widths a
 * class-per-span grid needs.
 */
const style = computed(() => {
  const vars: Record<string, string> = { '--wx-col-span': String(props.span) }
  if (props.offset !== undefined) vars['--wx-col-offset'] = String(props.offset)
  if (props.order !== undefined) vars['--wx-col-order'] = String(props.order)

  for (const name of breakpoints) {
    const value = props[name]
    if (value === undefined) continue
    const { span, offset } = normalize(value)
    if (span !== undefined) vars[`--wx-col-span-${name}`] = String(span)
    if (offset !== undefined) vars[`--wx-col-offset-${name}`] = String(offset)
  }

  return vars
})
</script>

<template>
  <component :is="as" class="wx-col" :style="style">
    <slot />
  </component>
</template>

<style scoped>
.wx-col {
  box-sizing: border-box;
  /* Half the gutter on each side; the row pulls the outer halves back. */
  flex: 0 0 auto;
  min-width: 0;
  padding-inline: calc(var(--wx-row-gutter, 0px) / 2);
  width: calc(var(--wx-col-span, 24) / 24 * 100%);
  margin-inline-start: calc(var(--wx-col-offset, 0) / 24 * 100%);
  order: var(--wx-col-order, 0);
}

@media (min-width: 640px) {
  .wx-col {
    width: calc(var(--wx-col-span-sm, var(--wx-col-span, 24)) / 24 * 100%);
    margin-inline-start: calc(var(--wx-col-offset-sm, var(--wx-col-offset, 0)) / 24 * 100%);
  }
}

@media (min-width: 768px) {
  .wx-col {
    width: calc(var(--wx-col-span-md, var(--wx-col-span-sm, var(--wx-col-span, 24))) / 24 * 100%);
    margin-inline-start: calc(
      var(--wx-col-offset-md, var(--wx-col-offset-sm, var(--wx-col-offset, 0))) / 24 * 100%
    );
  }
}

@media (min-width: 1024px) {
  .wx-col {
    width: calc(
      var(--wx-col-span-lg, var(--wx-col-span-md, var(--wx-col-span-sm, var(--wx-col-span, 24)))) /
        24 * 100%
    );
    margin-inline-start: calc(
      var(
          --wx-col-offset-lg,
          var(--wx-col-offset-md, var(--wx-col-offset-sm, var(--wx-col-offset, 0)))
        ) /
        24 * 100%
    );
  }
}

@media (min-width: 1280px) {
  .wx-col {
    width: calc(
      var(
          --wx-col-span-xl,
          var(
            --wx-col-span-lg,
            var(--wx-col-span-md, var(--wx-col-span-sm, var(--wx-col-span, 24)))
          )
        ) /
        24 * 100%
    );
    margin-inline-start: calc(
      var(
          --wx-col-offset-xl,
          var(
            --wx-col-offset-lg,
            var(--wx-col-offset-md, var(--wx-col-offset-sm, var(--wx-col-offset, 0)))
          )
        ) /
        24 * 100%
    );
  }
}
</style>
