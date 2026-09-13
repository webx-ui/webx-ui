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
  /*
   * The host may set these; the defaults live in the `var()` fallbacks rather than as
   * declarations here, because a property declared on this element would override the one
   * inherited from the host and the host could never say anything.
   *
   * A single-line field centres the chip on its middle line; a textarea is tall, so it pins the
   * chip near the top and makes it a size smaller.
   */
  position: absolute;
  top: var(--wx-locale-picker-top, 50%);
  right: var(--wx-space-4);
  /*
   * Unrolled it hangs over whatever is below — including the next field's own chip, which sits
   * at this same corner one control down. Two pickers at the same depth overlap into an
   * unreadable stack, so the one being pointed at leaves the others well beneath it.
   */
  z-index: 2;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 2px;
  border: 1px solid transparent;
  border-radius: var(--wx-radius-sm);
  background: transparent;
  /* Folded up it is one chip, and one chip belongs on the middle line of the control. */
  transform: translateY(var(--wx-locale-picker-shift, -50%));
  transition:
    background-color var(--wx-duration-fast) var(--wx-easing-standard),
    border-color var(--wx-duration-fast) var(--wx-easing-standard),
    box-shadow var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-locale-picker:hover,
.wx-locale-picker:focus-within {
  z-index: 40;
  border-color: var(--wx-border-default);
  background: var(--wx-bg-surface);
  box-shadow: var(--wx-shadow-sm);
}

.wx-locale-picker__code {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  height: var(--wx-locale-picker-height, 24px);
  padding: 0 var(--wx-space-6);
  border: none;
  border-radius: var(--wx-radius-xs);
  background: transparent;
  color: var(--wx-text-muted);
  font-family: inherit;
  font-size: var(--wx-font-size-sm);
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
