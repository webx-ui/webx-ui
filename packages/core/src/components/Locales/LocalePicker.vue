<script setup lang="ts">
import { computed } from 'vue'
import { localeLabel, type LocaleOption } from '../../composables/useLocalized'

/**
 * The chip in the corner of a localized field.
 *
 * Folded up it shows the language being edited; pointed at, the rest unroll beneath it. Not
 * exported: a field gets this by asking for `localized`, and a section gets it through
 * `WxLocales`.
 */
defineOptions({ name: 'WxLocalePicker' })

const props = defineProps<{
  locales: LocaleOption[]
  active: string
}>()

const emit = defineEmits<{ choose: [code: string] }>()

/** The current one first, so the folded-up picker shows it and the rest unroll beneath. */
const ordered = computed<LocaleOption[]>(() => [
  ...props.locales.filter((locale) => locale.code === props.active),
  ...props.locales.filter((locale) => locale.code !== props.active),
])
</script>

<template>
  <div class="wx-locale-picker">
    <button
      v-for="locale in ordered"
      :key="locale.code"
      type="button"
      :class="['wx-locale-picker__code', { 'is-active': locale.code === active }]"
      :aria-label="locale.code"
      :tabindex="locale.code === active ? 0 : -1"
      @click="emit('choose', locale.code)"
    >
      {{ localeLabel(locale) }}
    </button>
  </div>
</template>

<style scoped>
/*
 * It floats rather than taking space, so turning a field localized does not move the form
 * around it, and unrolls over whatever is below rather than pushing it down.
 */
.wx-locale-picker {
  position: absolute;
  top: var(--wx-space-4);
  right: var(--wx-space-4);
  z-index: 2;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 2px;
  border: 1px solid transparent;
  border-radius: var(--wx-radius-sm);
  background: transparent;
  transition:
    background-color var(--wx-duration-fast) var(--wx-easing-standard),
    border-color var(--wx-duration-fast) var(--wx-easing-standard),
    box-shadow var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-locale-picker:hover,
.wx-locale-picker:focus-within {
  border-color: var(--wx-border-default);
  background: var(--wx-bg-surface);
  box-shadow: var(--wx-shadow-sm);
}

.wx-locale-picker__code {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 30px;
  height: 22px;
  padding: 0 var(--wx-space-6);
  border: none;
  border-radius: var(--wx-radius-xs);
  background: transparent;
  color: var(--wx-text-muted);
  font-family: inherit;
  font-size: var(--wx-font-size-xs);
  font-weight: 600;
  line-height: 1;
  text-transform: uppercase;
  cursor: pointer;
}

.wx-locale-picker__code.is-active {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}

.wx-locale-picker__code:not(.is-active) {
  display: none;
}

.wx-locale-picker:hover .wx-locale-picker__code:not(.is-active),
.wx-locale-picker:focus-within .wx-locale-picker__code:not(.is-active) {
  display: flex;
}

.wx-locale-picker__code:not(.is-active):hover {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}
</style>
