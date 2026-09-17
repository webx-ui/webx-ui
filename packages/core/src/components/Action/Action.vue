<script setup lang="ts">
import { computed } from 'vue'
import WxIcon from '../Icon/Icon.vue'
import WxTooltip from '../Tooltip/Tooltip.vue'
import { useActions } from '../../composables/useActions'
import type { ActionEmits, ActionProps, ActionTone, ActionType } from './types'
import type { IconName } from '../Icon/types'

defineOptions({ name: 'WxAction', inheritAttrs: false })

const props = withDefaults(defineProps<ActionProps>(), {
  type: 'edit',
  icon: undefined,
  tone: undefined,
  title: undefined,
  label: undefined,
  href: undefined,
  target: undefined,
  as: undefined,
  disabled: false,
  hidden: false,
  size: undefined,
  tooltipSide: 'top',
})

const emit = defineEmits<ActionEmits>()

/** Icon, colour and English fallback name for each kind of action. */
const presets: Record<ActionType, { icon: IconName; tone: ActionTone; label: string }> = {
  add: { icon: 'plus', tone: 'primary', label: 'Add' },
  edit: { icon: 'edit', tone: 'primary', label: 'Edit' },
  remove: { icon: 'trash', tone: 'danger', label: 'Delete' },
  copy: { icon: 'copy', tone: 'primary', label: 'Copy' },
  link: { icon: 'link', tone: 'primary', label: 'Link' },
  goto: { icon: 'external-link', tone: 'primary', label: 'Open' },
  details: { icon: 'info', tone: 'primary', label: 'Details' },
  view: { icon: 'eye', tone: 'primary', label: 'View' },
  hide: { icon: 'eye-off', tone: 'neutral', label: 'Hide' },
  upload: { icon: 'upload', tone: 'primary', label: 'Upload' },
  download: { icon: 'download', tone: 'primary', label: 'Download' },
  sort: { icon: 'drag', tone: 'neutral', label: 'Reorder' },
  search: { icon: 'search', tone: 'primary', label: 'Search' },
  restore: { icon: 'refresh', tone: 'success', label: 'Restore' },
  settings: { icon: 'settings', tone: 'neutral', label: 'Settings' },
  send: { icon: 'mail', tone: 'primary', label: 'Send' },
  more: { icon: 'more-horizontal', tone: 'neutral', label: 'More' },
}

const group = useActions()

const preset = computed(() => presets[props.type] ?? presets.edit)
const icon = computed(() => props.icon ?? preset.value.icon)
const tone = computed(() => props.tone ?? preset.value.tone)
const size = computed(() => props.size ?? group?.size.value ?? 'md')
const accessibleName = computed(() => props.label ?? props.title ?? preset.value.label)

const tag = computed(() => props.as ?? (props.href ? 'a' : 'button'))

/*
 * About the tooltip in the template below.
 *
 * It is ours rather than the browser's `title`: one shape for the whole panel, a delay we
 * choose and a side that can be pointed away from the edge of a dialog. It gives no
 * accessible name of its own, which is why `aria-label` stays on the control either way.
 * It is always mounted and switched off rather than added only when there is something to
 * say, so that an action with a tip and one without are the same markup.
 *
 * What it costs: the action is no longer a single root node. The tooltip draws nothing of its
 * own — the DOM is the same button in the same place — but the tip lives beside the control in
 * the component tree, so Vue's root here is a fragment and `$el` is its anchor rather than the
 * button. Nothing in a template notices; `wrapper.element` in a test does, and asks for
 * `wrapper.get('button')` instead.
 */

/*
 * A disabled button says so with `aria-disabled`, the way the link and the `as` forms here
 * already do, and leaves the tab order with `tabindex` instead of with the attribute. The
 * attribute does both — and a third thing: the browser stops dispatching pointer events
 * anywhere near a disabled control, and a button nobody can hover is a button whose tooltip
 * never comes. An icon is at its least readable exactly when it is greyed out, so that tip is
 * the one worth keeping. Clicks and keys are turned away in `onClick`.
 */
const nativeAttrs = computed(() => {
  if (props.href) {
    return {
      href: props.disabled ? undefined : props.href,
      target: props.target,
      'aria-disabled': props.disabled ? 'true' : undefined,
      tabindex: props.disabled ? -1 : undefined,
    }
  }
  if (props.as) return { 'aria-disabled': props.disabled ? 'true' : undefined }
  return {
    type: 'button' as const,
    'aria-disabled': props.disabled ? 'true' : undefined,
    tabindex: props.disabled ? -1 : undefined,
  }
})

const classes = computed(() => [
  'wx-action',
  `wx-action--${size.value}`,
  `wx-action--${tone.value}`,
  { 'is-disabled': props.disabled },
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
  <span
    v-if="hidden"
    class="wx-action wx-action--placeholder"
    :class="`wx-action--${size}`"
    aria-hidden="true"
  />

  <wx-tooltip v-else :content="title" :disabled="!title" :side="tooltipSide">
    <component
      :is="tag"
      v-bind="{ ...nativeAttrs, ...$attrs }"
      :class="classes"
      :aria-label="accessibleName"
      @click="onClick"
    >
      <slot>
        <wx-icon :name="icon" />
      </slot>
    </component>
  </wx-tooltip>
</template>

<style scoped>
.wx-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  box-sizing: border-box;
  width: var(--wx-action-size, 36px);
  height: var(--wx-action-size, 36px);
  padding: 0;
  background: var(--wx-action-bg, var(--wx-bg-fill));
  border: 1px solid transparent;
  border-radius: var(--wx-radius-control);
  color: var(--wx-action-color, var(--wx-color-primary));
  font-size: var(--wx-action-icon, 18px);
  line-height: 1;
  text-decoration: none;
  cursor: pointer;
  transition:
    background-color var(--wx-duration-fast) var(--wx-easing-standard),
    color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-action:hover:not(.is-disabled) {
  background: var(--wx-bg-fill-hover);
}

.wx-action:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-action.is-disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

/* An empty square: no background, no pointer, just the space. */
.wx-action--placeholder {
  background: transparent;
  cursor: default;
  pointer-events: none;
}

/* sizes */
.wx-action--sm {
  --wx-action-size: 30px;
  --wx-action-icon: 16px;
}

.wx-action--md {
  --wx-action-size: 36px;
  --wx-action-icon: 18px;
}

.wx-action--lg {
  --wx-action-size: 42px;
  --wx-action-icon: 20px;
}

/* tones */
.wx-action--primary {
  --wx-action-color: var(--wx-color-primary);
}

.wx-action--danger {
  --wx-action-color: var(--wx-color-danger);
}

.wx-action--success {
  --wx-action-color: var(--wx-color-success-active);
}

.wx-action--warning {
  --wx-action-color: var(--wx-color-warning-active);
}

.wx-action--neutral {
  --wx-action-color: var(--wx-text-muted);
}
</style>
