<script setup lang="ts">
import type { LightboxSource } from '@webx-ui/core'
import { useTranslate } from './i18n'
import { useLightbox } from './lightbox'

/**
 * A thumbnail that opens large: whatever is in the slot becomes a button that opens
 * `WxLightbox` over `items`, on `start` (`useLightbox`).
 *
 * A button of its own, not a click on the picture: it is reached by Tab, it says what it does,
 * and it keeps its click to itself — a thumbnail in a row of a table or a tree opens the
 * picture, not the record. A drag that starts on it is still the list's: a drag is no click.
 */
defineOptions({ name: 'WxPreviewThumb' })

const props = withDefaults(
  defineProps<{
    /** The set the gallery pages through: the full pictures, not the thumbnails. */
    items: LightboxSource[]
    /** Which of them this thumbnail is. */
    start?: number
    /** Accessible name of the button. Default: "View full size" in the panel's language. */
    label?: string
    /** Takes the whole box it stands in — a cover cell of a list, sized by the list. */
    fill?: boolean
  }>(),
  { start: 0, label: undefined, fill: false },
)

const t = useTranslate('webx-admin')
const lightbox = useLightbox()
</script>

<template>
  <button
    type="button"
    class="wx-preview-thumb"
    :class="{ 'is-fill': props.fill }"
    :aria-label="props.label ?? t('lightbox.view')"
    :title="props.label ?? t('lightbox.view')"
    :disabled="props.items.length === 0"
    @click="lightbox.click($event, props.items, props.start)"
    @keydown.enter.stop
    @keydown.space.stop
  >
    <slot />
  </button>
</template>

<style>
/* No look of its own: the thumbnail inside is the look, the button only the way in. */
.wx-preview-thumb {
  display: inline-flex;
  flex: none;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
  border-radius: var(--wx-radius-sm);
  background: none;
  color: inherit;
  font: inherit;
  line-height: 0;
  cursor: zoom-in;
}

.wx-preview-thumb.is-fill {
  width: 100%;
  height: 100%;
}

.wx-preview-thumb:disabled {
  cursor: default;
}

.wx-preview-thumb:focus-visible {
  outline: 2px solid var(--wx-border-focus);
  outline-offset: 2px;
}
</style>
