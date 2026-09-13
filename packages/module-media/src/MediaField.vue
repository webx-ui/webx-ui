<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/admin'
import {
  WxAction,
  WxActions,
  WxButton,
  WxImage,
  WxInput,
  WxPopconfirm,
  WxPopover,
  type LocalizedValue,
} from '@webx-ui/core'
import { createMediaApi } from './api'
import { openMediaPicker } from './openMediaPicker'
import { useMediaMessages } from './i18n'
import type { MediaKind, MediaValue } from './types'

/**
 * A picture on a form, with the words this entity uses for it.
 *
 * Two states and nothing in between: an empty frame that opens the library, and the picture
 * with what can be done to it. `alt` and `title` live here rather than on the file, because one
 * picture used by two articles needs two captions — and they are localized for the same reason
 * everything else on a form is.
 *
 * What the field stores is the key and those words. The address is worked out when the page is
 * drawn, so moving the library to another disk changes nothing written before.
 */
const props = withDefaults(
  defineProps<{
    label?: string
    accept?: MediaKind | null
    /** Off for a decorative picture, where a caption is noise. */
    captions?: boolean
    /** Height of the frame — a number in pixels, or any CSS length. */
    height?: number | string
    disabled?: boolean
  }>(),
  {
    label: undefined,
    accept: 'image',
    captions: true,
    height: 220,
    disabled: false,
  },
)

const value = defineModel<MediaValue | null>({ default: null })

useMediaMessages()

const t = useTranslate('webx-media')

const api = createMediaApi(useAdmin())

const editing = ref(false)

/*
 * What a record stores is the key. The address beside it is only there when the picture was
 * chosen in this session, so a form opened on something saved last week has a key and nothing
 * to draw — and the library is the only thing that knows where that key currently lives.
 */
const resolved = ref<Record<string, string>>({})

watch(
  () => value.value?.path,
  async (path) => {
    if (!path || value.value?.url || resolved.value[path]) return

    const file = await api.fileByPath(path)

    if (file) resolved.value = { ...resolved.value, [path]: file.url }
  },
  { immediate: true },
)

const preview = computed(() => {
  const current = value.value

  if (!current) return null

  return current.url ?? resolved.value[current.path] ?? null
})

const frameStyle = computed(() => ({
  height: typeof props.height === 'number' ? `${props.height}px` : props.height,
}))

/**
 * `alt` and `title` are edited through the field, so they have to be readable as a record even
 * when what was stored is the plain string a column held before the site had a second language.
 */
function words(key: 'alt' | 'title'): LocalizedValue | string {
  return value.value?.[key] ?? {}
}

function setWords(key: 'alt' | 'title', next: unknown): void {
  if (!value.value) return

  value.value = { ...value.value, [key]: next as LocalizedValue }
}

async function choose(): Promise<void> {
  if (props.disabled) return

  const file = await openMediaPicker({ accept: props.accept })

  if (!file) return

  // The captions belong to the entity, so picking a different picture keeps them: usually the
  // words are still right and retyping them in three languages is the punishment for a swap.
  value.value = { ...value.value, path: file.path, url: file.url }
}

/** Clears the field. The file stays in the library — it is very likely used somewhere else. */
function clear(): void {
  value.value = null
  editing.value = false
}
</script>

<template>
  <div class="wx-media-field">
    <span v-if="label" class="wx-media-field__label">{{ label }}</span>

    <div v-if="!value" class="wx-media-field__frame is-empty" :style="frameStyle">
      <button type="button" class="wx-media-field__pick" :disabled="disabled" @click="choose">
        {{ t('field.select') }}
      </button>
    </div>

    <div v-else class="wx-media-field__frame" :style="frameStyle">
      <!--
        The picture and the buttons are wrapped in elements of our own rather than styled
        through their class: a component of the design system carries scoped rules, and a scoped
        rule is a class plus an attribute, so a single class from outside loses to it — silently,
        and only in a browser.

        The picture is also the way to swap it. A button labelled "replace" inside the captions
        popover is a second place to look for something the picture itself already offers.
      -->
      <button
        type="button"
        class="wx-media-field__picture"
        :disabled="disabled"
        :title="t('field.replace')"
        @click="choose"
      >
        <wx-image v-if="preview" :src="preview" fit="contain" width="100%" height="100%" />
        <span v-else class="wx-media-field__missing">{{ t('field.no-preview') }}</span>
      </button>

      <div v-if="!disabled" class="wx-media-field__bar">
        <wx-actions size="sm">
          <!--
          The captions are asked for where the picture is, not in a dialog over the whole form:
          what they describe has to stay in sight while they are being written.
        -->
          <wx-popover
            v-if="captions"
            v-model:open="editing"
            :title="t('field.captions')"
            :width="360"
            side="top"
            teleport
          >
            <template #trigger>
              <wx-action type="edit" :title="t('field.edit')" />
            </template>

            <div class="wx-media-field__captions">
              <div class="wx-media-field__caption">
                <label class="wx-media-field__caption-label" for="wx-media-alt">alt</label>
                <wx-input
                  id="wx-media-alt"
                  localized
                  :model-value="words('alt')"
                  :placeholder="t('field.alt-hint')"
                  @update:model-value="(next) => setWords('alt', next)"
                />
              </div>

              <div class="wx-media-field__caption">
                <label class="wx-media-field__caption-label" for="wx-media-title">title</label>
                <wx-input
                  id="wx-media-title"
                  localized
                  :model-value="words('title')"
                  :placeholder="t('field.title-hint')"
                  @update:model-value="(next) => setWords('title', next)"
                />
              </div>

              <div class="wx-media-field__captions-footer">
                <wx-button size="sm" type="primary" @click="editing = false">
                  {{ t('manager.save') }}
                </wx-button>
              </div>
            </div>
          </wx-popover>

          <!--
          Clearing the field is not deleting the file: the same picture is very likely used by
          another record, and this is the one place somebody would expect otherwise.
        -->
          <wx-popconfirm
            :title="t('field.clear-title')"
            :description="t('field.clear-text')"
            :confirm-text="t('field.clear-confirm')"
            :cancel-text="t('manager.cancel')"
            confirm-type="danger"
            side="top"
            @confirm="clear"
          >
            <template #trigger>
              <wx-action type="remove" :title="t('field.clear-confirm')" />
            </template>
          </wx-popconfirm>
        </wx-actions>
      </div>
    </div>
  </div>
</template>

<style>
.wx-media-field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
}

.wx-media-field__label {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-media-field__frame {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  padding: var(--wx-space-8);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  overflow: hidden;
}

.wx-media-field__frame.is-empty {
  border-style: dashed;
  padding: 0;
}

.wx-media-field__pick {
  width: 100%;
  height: 100%;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--wx-text-placeholder);
  font-family: inherit;
  font-size: var(--wx-font-size-md);
  cursor: pointer;
  transition: color var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-media-field__pick:hover:not(:disabled) {
  color: var(--wx-text-muted);
}

.wx-media-field__pick:disabled {
  cursor: not-allowed;
}

.wx-media-field__picture {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  min-width: 0;
  padding: 0;
  border: none;
  /* Inscribed in the frame, so rounded a step less than it — concentric, not parallel. */
  border-radius: var(--wx-radius-xs);
  overflow: hidden;
  background: transparent;
  cursor: pointer;
}

.wx-media-field__picture:disabled {
  cursor: not-allowed;
}

.wx-media-field__missing {
  color: var(--wx-text-placeholder);
  font-size: var(--wx-font-size-sm);
}

/* On the picture, out of the way of it: the frame is the preview, not a toolbar. */
.wx-media-field__bar {
  position: absolute;
  /* Clear of the frame's inner edge rather than sitting on it. */
  bottom: var(--wx-space-16);
  left: 50%;
  transform: translateX(-50%);
  padding: var(--wx-space-4);
  border-radius: var(--wx-radius-control);
  background: var(--wx-bg-surface);
  box-shadow: var(--wx-shadow-sm);
}

/* A label belongs to the field under it, so it sits closer to that than to the next one. */
.wx-media-field__captions {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  width: 100%;
}

.wx-media-field__caption {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
}

.wx-media-field__caption-label {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

.wx-media-field__captions-footer {
  display: flex;
  justify-content: flex-end;
  gap: var(--wx-space-8);
}
</style>
