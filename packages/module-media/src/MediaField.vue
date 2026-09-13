<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/admin'
import { WxButton, WxImage, WxInput, WxSpace } from '@webx-ui/core'
import { openMediaPicker } from './openMediaPicker'
import { useMediaMessages } from './i18n'
import type { MediaKind, MediaValue } from './types'

/**
 * A picture on a form, with the words this entity uses for it.
 *
 * `alt` and `title` live here rather than on the file because one picture is used by two
 * articles and needs two captions. What the field stores is the key and those words; the
 * address is worked out when the page is drawn, so moving the library to another disk changes
 * nothing here.
 */
withDefaults(
  defineProps<{
    label?: string
    accept?: MediaKind | null
    /** Off for a decorative picture, where a caption is noise. */
    captions?: boolean
  }>(),
  { label: undefined, accept: 'image', captions: true },
)

const value = defineModel<MediaValue | null>({ default: null })

useMediaMessages()

const t = useTranslate('webx-media')

const preview = computed(() => value.value?.url ?? null)

async function choose(): Promise<void> {
  const file = await openMediaPicker({ accept: 'image' })

  if (file) {
    value.value = { ...value.value, path: file.path, url: file.url }
  }
}

function clear(): void {
  value.value = null
}
</script>

<template>
  <div class="wx-media-field">
    <span v-if="label" class="wx-media-field__label">{{ label }}</span>

    <div class="wx-media-field__body">
      <wx-image v-if="preview" class="wx-media-field__preview" :src="preview" fit="cover" />

      <wx-space direction="vertical" size="sm" class="wx-media-field__controls">
        <wx-space size="xs">
          <wx-button size="sm" icon="image" @click="choose">{{ t('manager.select') }}</wx-button>
          <wx-button v-if="value" size="sm" variant="text" type="danger" @click="clear">
            {{ t('manager.delete') }}
          </wx-button>
        </wx-space>

        <template v-if="captions && value">
          <wx-input
            :model-value="value.alt ?? ''"
            size="sm"
            placeholder="alt"
            @update:model-value="(alt) => (value = { ...value!, alt: String(alt) })"
          />
          <wx-input
            :model-value="value.title ?? ''"
            size="sm"
            placeholder="title"
            @update:model-value="(title) => (value = { ...value!, title: String(title) })"
          />
        </template>
      </wx-space>
    </div>
  </div>
</template>

<style>
.wx-media-field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}

.wx-media-field__label {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-color-text-muted);
}

.wx-media-field__body {
  display: flex;
  gap: var(--wx-space-12);
  align-items: flex-start;
}

.wx-media-field__preview {
  width: 96px;
  height: 96px;
  border-radius: var(--wx-radius-md);
  overflow: hidden;
  flex: none;
}

.wx-media-field__controls {
  min-width: 0;
  flex: 1;
}
</style>
