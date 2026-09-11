<script setup lang="ts">
import { computed } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import { useBreadcrumb } from '../../composables/useBreadcrumb'
import type { BreadcrumbItemEmits, BreadcrumbItemProps } from './types'

defineOptions({ name: 'WxBreadcrumbItem', inheritAttrs: false })

const props = withDefaults(defineProps<BreadcrumbItemProps>(), {
  href: undefined,
  target: undefined,
  as: undefined,
  icon: undefined,
  current: undefined,
})

const emit = defineEmits<BreadcrumbItemEmits>()

const breadcrumb = useBreadcrumb()

const isLink = computed(() => Boolean(props.href || props.as))

/*
 * A crumb that links nowhere is where you already are. Saying so with `aria-current`
 * is the whole reason a screen reader can tell the trail from a row of links.
 */
const isCurrent = computed(() => props.current ?? !isLink.value)

const tag = computed(() => props.as ?? (props.href ? 'a' : 'span'))

const linkAttrs = computed(() => {
  const attrs: Record<string, unknown> = {}
  if (props.href) attrs.href = props.href
  if (props.target) attrs.target = props.target
  if (props.target === '_blank') attrs.rel = 'noopener noreferrer'
  if (isCurrent.value) attrs['aria-current'] = 'page'
  return attrs
})

const separatorIcon = computed(() => breadcrumb?.separatorIcon.value)
const separator = computed(() => breadcrumb?.separator.value ?? '/')
</script>

<template>
  <li class="wx-breadcrumb-item" :class="{ 'is-current': isCurrent }">
    <component
      :is="tag"
      v-bind="{ ...linkAttrs, ...$attrs }"
      class="wx-breadcrumb-item__link"
      @click="emit('click', $event)"
    >
      <wx-icon v-if="icon" class="wx-breadcrumb-item__icon" :name="icon" />
      <slot />
    </component>

    <!--
      The separator belongs to the item before it, and the stylesheet drops it on the
      last one — which is the only way an item can know it is last without the list
      counting its children.
    -->
    <span class="wx-breadcrumb-item__separator" aria-hidden="true">
      <wx-icon v-if="separatorIcon" :name="separatorIcon" />
      <template v-else>{{ separator }}</template>
    </span>
  </li>
</template>

<style scoped>
.wx-breadcrumb-item {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
  /*
   * The crumb owns its margins. A host stylesheet that spaces list items — VitePress
   * puts 8px between every `li + li`, and a CMS theme will have its own — otherwise
   * walks the trail down the page like a staircase.
   */
  margin: 0;
}

.wx-breadcrumb-item__link {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  padding: 0;
  background: none;
  border: 0;
  border-radius: var(--wx-radius-xs);
  color: inherit;
  font: inherit;
  text-decoration: none;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

a.wx-breadcrumb-item__link,
button.wx-breadcrumb-item__link {
  cursor: pointer;
  transition: color var(--wx-duration-fast) var(--wx-easing-standard);
}

a.wx-breadcrumb-item__link:hover,
button.wx-breadcrumb-item__link:hover {
  color: var(--wx-text-link);
}

.wx-breadcrumb-item__link:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-breadcrumb-item.is-current .wx-breadcrumb-item__link {
  color: var(--wx-text-default);
  font-weight: var(--wx-font-weight-medium);
  cursor: default;
}

.wx-breadcrumb-item__icon {
  flex: 0 0 auto;
  opacity: 0.9;
}

.wx-breadcrumb-item__separator {
  display: inline-flex;
  align-items: center;
  color: var(--wx-text-placeholder);
  user-select: none;
}

.wx-breadcrumb-item:last-child .wx-breadcrumb-item__separator {
  display: none;
}
</style>
