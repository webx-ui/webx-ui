<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue'
import { adminKey, useTranslate, WxFieldRow, type AdminContext } from '@webx-ui/module-admin'
import {
  WxButton,
  WxCheckbox,
  WxIcon,
  WxInput,
  WxPopover,
  WxText,
  fileIconName,
  type IconName,
  type LocalizedValue,
} from '@webx-ui/core'
import { createMediaApi, type MediaApi } from './api'
import { canBrowseMedia } from './access'
import { nameFromPath, readable, usePanelLocale } from './format'
import { useMediaMessages } from './i18n'
import { openMediaPicker } from './openMediaPicker'
import { useMediaUploads } from './uploading'
import type { MediaFile, MediaKind, MediaValue } from './types'

/**
 * `wx-file`: one file of the library, as a row in the style of the link field — what is chosen,
 * said in two lines — with everything about it in a panel under the row.
 *
 * The value is what it always was — `{ path, alt?, title? }`, plus `download` where the node offers
 * it — so a value saved by the old card opens here as it is. Only the drawing changed: the card with
 * four buttons and a «1 of 1» under it asked an editor to read a list of one, and the link field next
 * to it, which picks the same kind of file for a button, looked like another product.
 *
 * The panel is where the file is chosen from the library, uploaded straight into it, captioned and
 * cleared. Without the right to browse the library the row still shows the key, keeps the captions
 * editable and clears — that is the record's own — but offers no library to choose from.
 */
defineOptions({ name: 'WxFileField', inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    label?: string
    /** Narrow what the library offers. The server checks the same thing on the way in. */
    accept?: MediaKind | null
    /** Off where a caption is noise — a file that is only ever downloaded. */
    captions?: boolean
    /** Offer «Download instead of opening»: the site then gets the name to save the file under. */
    download?: boolean
    /** What the empty row says. */
    placeholder?: string
    disabled?: boolean
  }>(),
  {
    label: undefined,
    accept: null,
    captions: true,
    download: false,
    placeholder: undefined,
    disabled: false,
  },
)

const model = defineModel<MediaValue | null>({ default: null })

useMediaMessages()

const t = useTranslate('webx-media')
const locale = usePanelLocale()

/* A field has to draw itself without a panel — a demo page, a test — so this is `inject`. */
const admin: AdminContext | null = inject(adminKey, null)
const api: MediaApi | null = admin ? createMediaApi(admin) : null

const canBrowse = computed(() => canBrowseMedia(admin))
const canUpload = computed(
  () => admin !== null && (admin.can('media.upload') || admin.can('media.manage')),
)

const open = ref(false)

/** A value that names a file. Anything else — `null`, an old empty string — is an empty field. */
const value = computed<MediaValue | null>(() =>
  model.value && typeof model.value === 'object' && model.value.path ? model.value : null,
)

/* ------------------------------------------------------------------ the library --- */

/**
 * What the library says about the key on screen: `undefined` — not asked (or not allowed to ask),
 * `null` — asked, and the library has no such file.
 */
const file = ref<MediaFile | null | undefined>(undefined)

watch(
  () => value.value?.path ?? null,
  async (path) => {
    if (path === null) {
      file.value = undefined

      return
    }

    if (file.value?.path === path) return

    file.value = undefined

    if (!api || !canBrowse.value) return

    const found = await api.fileByPath(path).catch(() => undefined)

    // An answer to a key nobody is showing any more.
    if (path === (value.value?.path ?? null)) file.value = found
  },
  { immediate: true },
)

const gone = computed(() => value.value !== null && file.value === null)

const fileName = computed(() =>
  value.value ? (file.value?.file_name ?? nameFromPath(value.value.path)) : '',
)

interface Row {
  kind: string
  icon: IconName
  title: string
  detail: string | null
  thumbnail: string | null
  accent: 'danger' | null
}

const row = computed<Row>(() => {
  if (value.value === null) {
    return {
      kind: 'empty',
      icon: 'plus',
      title: props.placeholder ?? t('field.file-choose'),
      detail: null,
      thumbnail: null,
      accent: null,
    }
  }

  if (gone.value) {
    return {
      kind: 'missing',
      icon: 'warning',
      title: fileName.value,
      detail: t('field.missing'),
      thumbnail: null,
      accent: 'danger',
    }
  }

  const known = file.value

  return {
    kind: 'file',
    icon: fileIconName(fileName.value),
    title: fileName.value,
    detail: known
      ? [known.extension ? known.extension.toUpperCase() : null, readable(known.size, locale())]
          .filter(Boolean)
          .join(' · ')
      : value.value.path,
    thumbnail: known?.type === 'image' && api ? api.thumb(known, 72, 72) : null,
    accent: null,
  }
})

/* ------------------------------------------------------------------ choosing --- */

function take(chosen: MediaFile): void {
  file.value = chosen

  // The caption says what the file is for and survives a new version of it; the alt describes
  // one picture and does not.
  const kept: MediaValue = { path: chosen.path }

  if (value.value?.title !== undefined) kept.title = value.value.title
  if (props.download && value.value?.download) kept.download = true

  model.value = kept
}

async function choose(): Promise<void> {
  if (props.disabled || !canBrowse.value) return

  const chosen = await openMediaPicker({ accept: props.accept })

  if (chosen) take(chosen)
}

/*
 * Straight into the library, into its top folder: an editor attaching a price list to a page is
 * not filing it, and the library is where it can be put in order later.
 */
/** Who is waiting for the upload under way: the field takes the first file the queue stored. */
let settled: ((stored: MediaFile[]) => void) | null = null

const uploads =
  api && admin
    ? useMediaUploads({
        admin,
        api,
        concurrency: 1,
        // A stored file leaves the queue at once, so it is handed over here rather than read
        // back from the list of jobs.
        onSettled: (stored) => settled?.(stored),
      })
    : null
const uploading = ref(false)
const uploadFailed = ref(false)
const input = ref<HTMLInputElement | null>(null)

const ACCEPT: Partial<Record<MediaKind, string>> = {
  image: 'image/*',
  video: 'video/*',
  audio: 'audio/*',
}

const acceptAttr = computed(() => (props.accept ? (ACCEPT[props.accept] ?? undefined) : undefined))

function pickUpload(): void {
  input.value?.click()
}

async function upload(event: Event): Promise<void> {
  const target = event.target as HTMLInputElement
  const chosen = target.files?.[0]

  target.value = ''

  if (!chosen || !uploads || !api) return

  uploading.value = true
  uploadFailed.value = false

  try {
    const directories = await api.directories()
    const root = directories.find((directory) => directory.is_root) ?? directories[0]

    if (!root) throw new Error('The library has no folder to upload into.')

    const stored = await new Promise<MediaFile | null>((resolve) => {
      settled = (files) => resolve(files[0] ?? null)

      uploads.add([chosen], root.id)

      // A refused file stays in the queue as failed and never settles as stored.
      const key = uploads.jobs.value.at(-1)?.key
      const stop = watch(
        () => uploads.jobs.value.find((job) => job.key === key)?.stage,
        (stage) => {
          if (stage === 'failed') {
            stop()
            resolve(null)
          }
        },
      )
    })

    settled = null

    if (stored) {
      take(stored)
    } else {
      uploadFailed.value = true
    }
  } catch {
    uploadFailed.value = true
  } finally {
    settled = null
    uploading.value = false
  }
}

/* ------------------------------------------------------------------ editing --- */

function words(key: 'alt' | 'title'): LocalizedValue | string {
  return value.value?.[key] ?? {}
}

function setWords(key: 'alt' | 'title', next: unknown): void {
  if (value.value === null) return

  model.value = { ...value.value, [key]: next as LocalizedValue }
}

function setDownload(on: boolean): void {
  if (value.value === null) return

  const next: MediaValue = { ...value.value }

  if (on) next.download = true
  else delete next.download

  model.value = next
}

/** Out of this record, not out of the library — the file is very likely used elsewhere. */
function clear(): void {
  model.value = null
  file.value = undefined
}

/** An image's alt is asked for; a document's is kept as it was and not shown. */
const asksAlt = computed(() => props.captions && props.accept === 'image')

const panelName = computed(() => props.label ?? t('field.file-panel'))
</script>

<template>
  <div class="wx-file-field" :class="{ 'is-disabled': props.disabled }" v-bind="$attrs">
    <wx-popover
      v-model:open="open"
      class="wx-file-field__panel"
      side="bottom"
      align="start"
      :offset="8"
      :arrow="false"
      width="var(--reka-popover-trigger-width)"
      :disabled="props.disabled"
      :aria-label="panelName"
    >
      <template #trigger>
        <wx-field-row
          class="wx-file-field__row"
          :kind="row.kind"
          :icon="row.icon"
          :title="row.title"
          :detail="row.detail"
          :thumbnail="row.thumbnail"
          :accent="row.accent"
          :mono="row.detail === value?.path"
          :open="open"
          :disabled="props.disabled"
        />
      </template>

      <div class="wx-file-field__body">
        <div v-if="value" class="wx-file-field__current">
          <wx-icon :name="row.icon" class="wx-file-field__current-icon" aria-hidden="true" />
          <div class="wx-file-field__current-text">
            <span class="wx-file-field__current-name">{{ fileName }}</span>
            <span class="wx-file-field__current-meta" :class="{ 'is-danger': gone }">
              {{ row.detail }}
            </span>
          </div>
          <a
            v-if="file?.url"
            class="wx-file-field__open"
            :href="file.url"
            target="_blank"
            rel="noopener noreferrer"
          >
            {{ t('field.file-open') }}
            <wx-icon name="external-link" size="sm" aria-hidden="true" />
          </a>
        </div>

        <div class="wx-file-field__sources">
          <wx-button
            v-if="canBrowse"
            size="sm"
            :disabled="props.disabled || uploading"
            @click="choose"
          >
            {{ value ? t('field.file-replace') : t('field.file-library') }}
          </wx-button>
          <wx-button
            v-if="canUpload && uploads"
            size="sm"
            variant="outline"
            :loading="uploading"
            :disabled="props.disabled"
            @click="pickUpload"
          >
            {{ uploading ? t('field.file-uploading') : t('field.file-upload') }}
          </wx-button>
          <input
            ref="input"
            class="wx-file-field__input"
            type="file"
            tabindex="-1"
            aria-hidden="true"
            :accept="acceptAttr"
            @change="upload"
          />
          <wx-text v-if="!canBrowse" size="sm" tone="muted">{{ t('field.locked') }}</wx-text>
        </div>

        <wx-text v-if="uploadFailed" size="sm" tone="danger">
          {{ t('field.file-upload-failed') }}
        </wx-text>

        <template v-if="value">
          <label v-if="props.captions" class="wx-file-field__caption">
            <span class="wx-file-field__caption-label">{{ t('field.file-title') }}</span>
            <wx-input
              localized
              :model-value="words('title')"
              :placeholder="t('field.title-hint')"
              :disabled="props.disabled"
              @update:model-value="(next: unknown) => setWords('title', next)"
            />
          </label>

          <label v-if="asksAlt" class="wx-file-field__caption">
            <span class="wx-file-field__caption-label">alt</span>
            <wx-input
              localized
              :model-value="words('alt')"
              :placeholder="t('field.alt-hint')"
              :disabled="props.disabled"
              @update:model-value="(next: unknown) => setWords('alt', next)"
            />
          </label>

          <wx-checkbox
            v-if="props.download"
            :model-value="value.download === true"
            :disabled="props.disabled"
            @update:model-value="(on: boolean) => setDownload(on)"
          >
            {{ t('field.file-download') }}
          </wx-checkbox>
        </template>
      </div>

      <template #footer="{ close }">
        <wx-button
          v-if="value && !props.disabled"
          size="sm"
          variant="text"
          type="danger"
          @click="
            () => {
              clear()
              close()
            }
          "
        >
          {{ t('field.remove-confirm') }}
        </wx-button>
        <span v-else />
        <wx-button size="sm" type="primary" @click="close">{{ t('field.file-done') }}</wx-button>
      </template>
    </wx-popover>

    <button
      v-if="value && !props.disabled"
      type="button"
      class="wx-file-field__clear"
      :title="t('field.file-clear')"
      :aria-label="t('field.file-clear')"
      @click="clear"
    >
      <wx-icon name="close" aria-hidden="true" />
    </button>
  </div>
</template>

<style>
.wx-file-field {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-file-field__row {
  flex: 1 1 auto;
}

.wx-file-field__clear {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  background: transparent;
  border: 1px solid transparent;
  border-radius: var(--wx-radius-control);
  color: var(--wx-text-muted);
  cursor: pointer;
}

.wx-file-field__clear:hover {
  background: var(--wx-bg-fill);
  color: var(--wx-text-default);
}

.wx-file-field__clear:focus-visible {
  outline: none;
  border-color: var(--wx-border-focus);
  box-shadow: var(--wx-ring-focus);
}

/* The panel is teleported, so these cannot be scoped. As wide as the row, as the link's is. */
.wx-popover.wx-file-field__panel {
  min-width: 280px;
  max-width: calc(100vw - var(--wx-space-32));
}

.wx-file-field__panel .wx-popover__footer {
  justify-content: space-between;
  padding-top: var(--wx-space-10);
  border-top: 1px solid var(--wx-border-muted);
}

.wx-file-field__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-file-field__current {
  display: flex;
  align-items: center;
  gap: var(--wx-space-10);
  padding: var(--wx-space-8) var(--wx-space-10);
  background: var(--wx-bg-subtle);
  border-radius: var(--wx-radius-sm);
}

.wx-file-field__current-icon {
  flex: 0 0 auto;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-lg);
}

.wx-file-field__current-text {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-file-field__current-name {
  color: var(--wx-text-strong);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
  overflow-wrap: anywhere;
}

.wx-file-field__current-meta {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  overflow-wrap: anywhere;
}

.wx-file-field__current-meta.is-danger {
  color: var(--wx-color-danger);
}

.wx-file-field__open {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  gap: var(--wx-space-4);
  color: var(--wx-color-primary);
  font-size: var(--wx-font-size-sm);
  text-decoration: none;
}

.wx-file-field__open:hover {
  text-decoration: underline;
}

.wx-file-field__sources {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-file-field__input {
  display: none;
}

.wx-file-field__caption {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
}

.wx-file-field__caption-label {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}
</style>
