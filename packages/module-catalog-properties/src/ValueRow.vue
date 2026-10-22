<script lang="ts">
import type { LightboxItem } from '@webx-ui/core'

/** A value's picture in the gallery of the tree: the full one, and the value it belongs to. */
export interface ValuePicture extends LightboxItem {
  id: number
}
</script>

<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate, WxPreviewThumb } from '@webx-ui/module-admin'
import { NAMESPACE } from './i18n'
import type { ValueNode } from './types'

/**
 * What a value of the book looks like on its row: its picture or its colour — the picture wins,
 * as it does on the storefront's swatch — its name, its slug in the panel's language, and how
 * many products hold it: the number a refusal to delete names, said before anybody asks.
 */
defineOptions({ name: 'WxCatalogPropertyValueRow' })

const props = withDefaults(
  defineProps<{
    node: ValueNode
    locale: string
    /** The pictures of the tree, which a click on this one pages through. */
    pictures?: ValuePicture[]
  }>(),
  { pictures: () => [] },
)

/* This value's own picture alone, when the tree has not handed over the rest. */
const gallery = computed(() => {
  const found = props.pictures.findIndex((one) => one.id === props.node.id)

  if (found >= 0) return { items: props.pictures, start: found }

  const image = props.node.image

  return { items: image ? [{ src: image.url, thumb: image.thumb ?? undefined }] : [], start: 0 }
})

const t = useTranslate(NAMESPACE)

const slug = computed(() => {
  const map = props.node.slug

  return map && !Array.isArray(map) ? (map[props.locale] ?? '') : ''
})
</script>

<template>
  <span class="wx-catalog-value-row">
    <wx-preview-thumb
      v-if="props.node.image"
      class="wx-catalog-value-row__open"
      :items="gallery.items"
      :start="gallery.start"
    >
      <img
        class="wx-catalog-value-row__image"
        :src="props.node.image.thumb ?? props.node.image.url"
        alt=""
      />
    </wx-preview-thumb>
    <span
      v-else-if="props.node.color"
      class="wx-catalog-value-row__swatch"
      :style="{ background: props.node.color }"
      aria-hidden="true"
    />
    <span class="wx-catalog-value-row__title">{{ props.node.label }}</span>
    <span v-if="slug" class="wx-catalog-value-row__slug">{{ slug }}</span>
    <span class="wx-catalog-value-row__count">
      {{ t('panel.count', { count: props.node.products_count }) }}
    </span>
  </span>
</template>

<style scoped>
.wx-catalog-value-row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  width: 100%;
  min-width: 0;
}

.wx-catalog-value-row__swatch,
.wx-catalog-value-row__image {
  flex: none;
  width: var(--wx-space-16);
  height: var(--wx-space-16);
  border-radius: var(--wx-radius-full);
  /* A white value on a white panel is still a circle. */
  box-shadow: inset 0 0 0 1px var(--wx-border-default);
  object-fit: cover;
}

.wx-catalog-value-row__title {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-catalog-value-row__slug,
.wx-catalog-value-row__count {
  flex: none;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

/* At the end of the row, where the eye runs down a column of numbers. */
.wx-catalog-value-row__count {
  margin-inline-start: auto;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
</style>
