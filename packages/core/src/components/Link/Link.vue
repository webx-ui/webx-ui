<script setup lang="ts">
import { computed } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import type { LinkEmits, LinkProps } from './types'

defineOptions({ name: 'WxLink', inheritAttrs: false })

const props = withDefaults(defineProps<LinkProps>(), {
  href: undefined,
  target: undefined,
  rel: undefined,
  type: 'default',
  underline: 'hover',
  size: undefined,
  weight: undefined,
  disabled: false,
  external: false,
  as: undefined,
})

const emit = defineEmits<LinkEmits>()

const tag = computed(() => props.as ?? 'a')

/** `noopener` is not optional on a `_blank` link — the opened page can steal the tab. */
const linkAttrs = computed(() => {
  const target = props.target ?? (props.external ? '_blank' : undefined)
  const rel = props.rel ?? (target === '_blank' ? 'noopener noreferrer' : undefined)
  const href = props.disabled ? undefined : props.href

  /*
   * Only the attributes we actually have: rendering through `RouterLink`, a stray
   * `href: undefined` in the fallthrough would wipe out the href the router writes.
   */
  const attrs: Record<string, unknown> = {}
  if (href !== undefined) attrs.href = href
  if (target !== undefined) attrs.target = target
  if (rel !== undefined) attrs.rel = rel
  if (props.disabled) {
    attrs['aria-disabled'] = 'true'
    attrs.tabindex = -1
  }
  return attrs
})

const classes = computed(() => [
  'wx-link',
  `wx-link--${props.type}`,
  `wx-link--underline-${props.underline}`,
  {
    [`wx-link--${props.size}`]: Boolean(props.size),
    [`wx-link--weight-${props.weight}`]: Boolean(props.weight),
    'is-disabled': props.disabled,
  },
])

function onClick(event: MouseEvent) {
  if (props.disabled) {
    event.preventDefault()
    event.stopPropagation()
    return
  }
  emit('click', event)
}
</script>

<template>
  <component :is="tag" v-bind="{ ...linkAttrs, ...$attrs }" :class="classes" @click="onClick">
    <span v-if="$slots.icon" class="wx-link__icon">
      <slot name="icon" />
    </span>
    <slot />
    <wx-icon v-if="external" class="wx-link__external" name="external-link" size="0.85em" />
  </component>
</template>

<style scoped>
.wx-link {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-4);
  color: var(--wx-link-color, var(--wx-text-link));
  font-family: inherit;
  font-size: inherit;
  text-decoration: none;
  cursor: pointer;
  transition: color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-link:hover {
  color: var(--wx-link-hover, var(--wx-link-color));
}

.wx-link:focus-visible {
  outline: none;
  border-radius: var(--wx-radius-xs);
  box-shadow: var(--wx-ring-focus);
}

/* types */
.wx-link--default {
  --wx-link-color: var(--wx-text-link);
  --wx-link-hover: var(--wx-color-primary-hover);
}

.wx-link--primary {
  --wx-link-color: var(--wx-color-primary);
  --wx-link-hover: var(--wx-color-primary-hover);
}

.wx-link--success {
  --wx-link-color: var(--wx-color-success-active);
  --wx-link-hover: var(--wx-color-success);
}

.wx-link--warning {
  --wx-link-color: var(--wx-color-warning-active);
  --wx-link-hover: var(--wx-color-warning);
}

.wx-link--danger {
  --wx-link-color: var(--wx-color-danger);
  --wx-link-hover: var(--wx-color-danger-hover);
}

.wx-link--info {
  --wx-link-color: var(--wx-color-info-active);
  --wx-link-hover: var(--wx-color-info);
}

.wx-link--muted {
  --wx-link-color: var(--wx-text-muted);
  --wx-link-hover: var(--wx-text-default);
}

/* underline */
.wx-link--underline-always {
  text-decoration: underline;
  text-underline-offset: 0.2em;
}

.wx-link--underline-hover:hover {
  text-decoration: underline;
  text-underline-offset: 0.2em;
}

.wx-link--underline-never,
.wx-link--underline-never:hover {
  text-decoration: none;
}

/* sizes */
.wx-link--xs {
  font-size: var(--wx-font-size-xs);
}

.wx-link--sm {
  font-size: var(--wx-font-size-sm);
}

.wx-link--md {
  font-size: var(--wx-font-size-md);
}

.wx-link--lg {
  font-size: var(--wx-font-size-lg);
}

.wx-link--xl {
  font-size: var(--wx-font-size-xl);
}

.wx-link--weight-regular {
  font-weight: var(--wx-font-weight-regular);
}

.wx-link--weight-medium {
  font-weight: var(--wx-font-weight-medium);
}

.wx-link--weight-semibold {
  font-weight: var(--wx-font-weight-semibold);
}

.wx-link--weight-bold {
  font-weight: var(--wx-font-weight-bold);
}

.wx-link.is-disabled {
  color: var(--wx-text-disabled);
  text-decoration: none;
  cursor: not-allowed;
}

.wx-link__icon {
  display: inline-flex;
  align-items: center;
}

.wx-link__external {
  opacity: 0.75;
}
</style>
