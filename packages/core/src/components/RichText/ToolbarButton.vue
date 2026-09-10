<script setup lang="ts">
import { richTextIcons } from './icons'

defineOptions({ name: 'WxRichTextToolbarButton' })

withDefaults(
  defineProps<{
    /** Key into the icon set. Omit it and the `text` shows instead. */
    icon?: string
    /** Short label used for headings, where a glyph reads worse than "H2". */
    text?: string
    label: string
    active?: boolean
    disabled?: boolean
  }>(),
  { icon: undefined, text: undefined, active: false, disabled: false },
)

defineEmits<{ click: [] }>()
</script>

<template>
  <button
    type="button"
    class="wx-rich-text__tool"
    :class="{ 'is-active': active }"
    :title="label"
    :aria-label="label"
    :aria-pressed="active"
    :disabled="disabled"
    @click="$emit('click')"
  >
    <svg v-if="icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path
        :d="richTextIcons[icon]"
        stroke="currentColor"
        stroke-width="1.7"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
    </svg>
    <span v-else class="wx-rich-text__tool-text">{{ text }}</span>
  </button>
</template>

<style scoped>
.wx-rich-text__tool {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 30px;
  height: 30px;
  padding: 0;
  background: transparent;
  border: 1px solid transparent;
  border-radius: var(--wx-radius-xs);
  color: var(--wx-text-default);
  cursor: pointer;
  transition:
    background-color var(--wx-duration-fast) var(--wx-easing-standard),
    color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-rich-text__tool svg {
  width: 18px;
  height: 18px;
}

.wx-rich-text__tool-text {
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-rich-text__tool:hover:not(:disabled) {
  background: var(--wx-bg-fill);
}

.wx-rich-text__tool.is-active {
  background: var(--wx-color-primary-soft);
  color: var(--wx-color-primary);
}

.wx-rich-text__tool:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-rich-text__tool:disabled {
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}
</style>
