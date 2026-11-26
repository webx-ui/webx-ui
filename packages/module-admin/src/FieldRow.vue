<script setup lang="ts">
import { WxIcon, type IconName } from '@webx-ui/core'

/**
 * The one row a field that is chosen rather than typed shows: a mark, what was chosen in two
 * lines, and a chevron that says there is a panel under it.
 *
 * Shared by the link field and the file field so that the two read as one kind of control on a
 * form — a button that downloads a brochure and the brochure field next to it should not look
 * like two different design systems. The row is only a picture of the value: it knows nothing
 * about links or files, and the field that draws it decides every word and icon.
 *
 * The root is the `<button>` itself, so a popover can take it as its trigger (`as-child`) and put
 * its attributes — `aria-expanded`, the click — straight on it.
 */
defineOptions({ name: 'WxFieldRow' })

const props = withDefaults(
  defineProps<{
    /** What is chosen, said as the first line: a page's title, a file's name. */
    title: string
    /** The second line — an address, «PDF · 61 kB». Nothing for an empty row. */
    detail?: string | null
    icon?: IconName
    /** A picture instead of the icon — the file is one. */
    thumbnail?: string | null
    /**
     * The state the row is in, as a class: `empty` draws the dashed placeholder, `none` the
     * greyed one; anything else is a value.
     */
    kind?: string
    /** The mark in the colour of what is chosen: `primary` for a page, `danger` for a gone file. */
    accent?: 'primary' | 'danger' | null
    /** The second line in the monospace face — an address, a key — rather than in words. */
    mono?: boolean
    open?: boolean
    disabled?: boolean
  }>(),
  {
    detail: null,
    icon: 'plus',
    thumbnail: null,
    kind: 'value',
    accent: null,
    mono: true,
    open: false,
    disabled: false,
  },
)
</script>

<template>
  <button
    type="button"
    class="wx-field-row"
    :class="[
      `is-${props.kind}`,
      { 'is-open': props.open, [`is-accent-${props.accent}`]: props.accent },
    ]"
    :disabled="props.disabled"
  >
    <span class="wx-field-row__icon" aria-hidden="true">
      <img v-if="props.thumbnail" class="wx-field-row__thumb" :src="props.thumbnail" alt="" />
      <wx-icon v-else :name="props.icon" />
    </span>

    <span class="wx-field-row__text">
      <span class="wx-field-row__title">{{ props.title }}</span>
      <span v-if="props.detail" class="wx-field-row__detail" :class="{ 'is-mono': props.mono }">{{
        props.detail
      }}</span>
    </span>

    <slot name="flags" />

    <wx-icon class="wx-field-row__chevron" name="chevron-down" aria-hidden="true" />
  </button>
</template>

<style scoped>
.wx-field-row {
  box-sizing: border-box;
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  width: 100%;
  min-width: 0;
  min-height: 56px;
  margin: 0;
  padding: var(--wx-space-8) var(--wx-space-12) var(--wx-space-8) var(--wx-space-10);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-default);
  font: inherit;
  text-align: start;
  cursor: pointer;
  transition:
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}

.wx-field-row:hover:not(:disabled) {
  border-color: var(--wx-border-strong);
}

.wx-field-row:focus-visible {
  outline: none;
  border-color: var(--wx-border-focus);
  box-shadow: var(--wx-ring-focus);
}

/* Open, the row is the field being edited — it stays marked while focus is in the panel. */
.wx-field-row.is-open {
  border-color: var(--wx-border-focus);
  box-shadow: var(--wx-ring-focus);
}

.wx-field-row:disabled {
  background: var(--wx-bg-disabled);
  color: var(--wx-text-disabled);
  cursor: not-allowed;
}

.wx-field-row.is-empty {
  border-style: dashed;
}

.wx-field-row.is-none {
  background: var(--wx-bg-subtle);
}

.wx-field-row__icon {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  overflow: hidden;
  background: var(--wx-bg-fill);
  border-radius: var(--wx-radius-sm);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-md);
}

.wx-field-row.is-accent-primary .wx-field-row__icon {
  background: var(--wx-color-primary-soft);
  color: var(--wx-color-primary);
}

.wx-field-row.is-accent-danger .wx-field-row__icon {
  background: var(--wx-color-danger-soft);
  color: var(--wx-color-danger);
}

.wx-field-row__thumb {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.wx-field-row__text {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-field-row__title,
.wx-field-row__detail {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-field-row__title {
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
  line-height: var(--wx-font-line-height-tight);
}

.wx-field-row.is-empty .wx-field-row__title {
  color: var(--wx-text-placeholder);
  font-weight: var(--wx-font-weight-regular);
}

.wx-field-row.is-none .wx-field-row__title {
  color: var(--wx-text-muted);
}

.wx-field-row__detail {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  line-height: var(--wx-font-line-height-tight);
}

.wx-field-row__detail.is-mono {
  font-family: var(--wx-font-family-mono);
}

.wx-field-row.is-accent-danger .wx-field-row__detail {
  color: var(--wx-color-danger);
}

.wx-field-row__chevron {
  flex: 0 0 auto;
  color: var(--wx-text-muted);
  transition: transform 0.15s ease;
}

.wx-field-row.is-open .wx-field-row__chevron {
  transform: rotate(180deg);
}
</style>
