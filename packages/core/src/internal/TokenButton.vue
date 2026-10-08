<script setup lang="ts">
/**
 * The button that opens every placeholder a plain field offers.
 *
 * It does not take focus on press: the field keeps its caret, and the list then knows where the
 * choice goes. Pressed from the keyboard it is focused, and the field takes the focus back.
 */
defineOptions({ name: 'WxTokenButton' })

defineProps<{ label: string; disabled?: boolean }>()

defineEmits<{ click: [] }>()
</script>

<template>
  <button
    type="button"
    class="wx-token-button"
    :title="label"
    :aria-label="label"
    :disabled="disabled"
    @mousedown.prevent
    @click="$emit('click')"
  >
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path
        d="M9 4H6v16h3M15 4h3v16h-3"
        stroke="currentColor"
        stroke-width="1.7"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
    </svg>
  </button>
</template>

<style>
/* One look for every field that carries it; each field only says where it sits. */
.wx-token-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 22px;
  height: 22px;
  padding: 0;
  background: transparent;
  border: none;
  border-radius: var(--wx-radius-xs);
  color: var(--wx-text-muted);
  cursor: pointer;
  transition: background-color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-token-button svg {
  width: 16px;
  height: 16px;
}

.wx-token-button:hover:not(:disabled) {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}

.wx-token-button:focus-visible {
  outline: 2px solid var(--wx-border-focus);
  outline-offset: 1px;
}

.wx-token-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
</style>
