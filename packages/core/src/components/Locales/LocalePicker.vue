<script setup lang="ts">
import { localeLabel, type LocaleOption } from '../../composables/useLocalized'

/**
 * The chip in the corner of a localized field.
 *
 * Folded up it shows the language being edited; pointed at, every language unrolls beneath it
 * in the site's own order — the current one included and marked, so the list never reshuffles
 * under the pointer. Not exported: a field gets this by asking for `localized`, and a section
 * gets it through `WxLocales`.
 */
defineOptions({ name: 'WxLocalePicker' })

const props = defineProps<{
  locales: LocaleOption[]
  active: string
}>()

const emit = defineEmits<{ choose: [code: string] }>()

const current = () => props.locales.find((locale) => locale.code === props.active)
</script>

<template>
  <div class="wx-locale-picker">
    <button
      type="button"
      class="wx-locale-picker__current"
      :aria-label="active"
      @click="emit('choose', active)"
    >
      {{ current() ? localeLabel(current() as LocaleOption) : active }}
    </button>

    <button
      v-for="locale in locales"
      :key="locale.code"
      type="button"
      :class="['wx-locale-picker__code', { 'is-active': locale.code === active }]"
      :aria-label="locale.code"
      tabindex="-1"
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
  /*
   * The chip belongs on the middle line of the control, so the box is lifted by half the chip
   * — not by half of itself: unrolled, the box is four chips tall, and lifting by half of that
   * would drag the chip up as the list appears. The 3px is the border and the padding above it.
   */
  transform: translateY(
    var(--wx-locale-picker-shift, calc(-1 * (var(--wx-locale-picker-height, 24px) / 2 + 3px)))
  );
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

.wx-locale-picker__current,
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

/* The chip: always there, and the one thing a keyboard reaches. */
.wx-locale-picker__current,
.wx-locale-picker__code.is-active {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}

/* The list unrolls under the chip, separated by a hairline so the two do not read as one. */
.wx-locale-picker__code {
  display: none;
}

.wx-locale-picker:hover .wx-locale-picker__code,
.wx-locale-picker:focus-within .wx-locale-picker__code {
  display: flex;
}

.wx-locale-picker:hover .wx-locale-picker__code:first-of-type,
.wx-locale-picker:focus-within .wx-locale-picker__code:first-of-type {
  margin-top: 2px;
  border-top: 1px solid var(--wx-border-muted);
  border-radius: 0 0 var(--wx-radius-xs) var(--wx-radius-xs);
  padding-top: 2px;
}

.wx-locale-picker__code:not(.is-active):hover {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}
</style>
