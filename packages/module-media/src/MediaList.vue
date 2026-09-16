<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue'
import { adminKey, useTranslate, type AdminContext } from '@webx-ui/module-admin'
import {
  WxAction,
  WxActions,
  WxButton,
  WxFileCard,
  WxInput,
  WxPopconfirm,
  WxPopover,
  WxSortableList,
  type LocalizedValue,
} from '@webx-ui/core'
import { createMediaApi, type MediaApi } from './api'
import { nameFromPath, readable } from './format'
import { openMediaFiles, openMediaLibrary } from './openMediaPicker'
import { useMediaMessages } from './i18n'
import type { MediaAspect, MediaFile, MediaKind, MediaValue } from './types'

/**
 * Several files on a form, in an order somebody chose.
 *
 * One component under `wx-gallery`, `wx-file` and `wx-files`: what differs between them is a
 * layout and a limit, and everything else — picking, ordering, captions, the card of a file
 * that is no longer there — is the same work. The three names exist for the person choosing a
 * field type, not for the code.
 *
 * What is stored is `{ path, alt?, title? }` per item and the order they are in. Everything
 * else on screen — the preview, the name, the size — is asked of the library on every read,
 * because the address of a file is not a thing worth writing into a thousand records.
 */
const props = withDefaults(
  defineProps<{
    label?: string
    /** Cards side by side for pictures, a line each for documents. */
    layout?: 'grid' | 'rows'
    /** Narrow what the library offers. The server checks the same thing on the way in. */
    accept?: MediaKind | null
    /** Off where a caption is noise — a row of logos, a set of downloads. */
    captions?: boolean
    max?: number | null
    min?: number | null
    /** A fixed number of columns instead of as many as fit. */
    columns?: number | null
    /** Shape of a thumbnail in the grid: a preset, or anything CSS reads as a ratio. */
    aspect?: MediaAspect | null
    /** One file, kept as a list of one — what `wx-file` is. */
    single?: boolean
    disabled?: boolean
  }>(),
  {
    label: undefined,
    layout: 'grid',
    accept: null,
    captions: true,
    max: null,
    min: null,
    columns: null,
    aspect: null,
    single: false,
    disabled: false,
  },
)

const model = defineModel<MediaValue[]>({ default: () => [] })

useMediaMessages()

const t = useTranslate('webx-media')

/*
 * The panel, when there is one. A field has to draw itself without it — a demo page or a test
 * has no library to ask — so this is `inject` rather than `useAdmin()`, which throws.
 */
const admin: AdminContext | null = inject(adminKey, null)
const api: MediaApi | null = admin ? createMediaApi(admin) : null

/** The presets, spelled the way CSS wants them. Anything else is passed through as given. */
const RATIOS: Record<string, string> = {
  '16/9': '16 / 9',
  '4/3': '4 / 3',
  '1/1': '1 / 1',
}

const items = computed<MediaValue[]>({
  get: () => (Array.isArray(model.value) ? model.value : []),
  set: (next) => {
    model.value = next
  },
})

const limit = computed(() => (props.single ? 1 : (props.max ?? null)))

const full = computed(() => limit.value !== null && items.value.length >= limit.value)

/* ------------------------------------------------------------------ the library --- */

/*
 * What the library says about each key on screen.
 *
 * A key that is not in here yet is a key nothing has been asked about — the field draws what
 * it can from the key itself. A key mapped to `null` is one the library has answered about:
 * the file is gone, and that is what the card says.
 */
const files = ref<Record<string, MediaFile | null>>({})

const asking = new Set<string>()

async function resolve(paths: string[]): Promise<void> {
  if (!api) return

  const wanted = paths.filter((path) => !(path in files.value) && !asking.has(path))

  if (wanted.length === 0) return

  wanted.forEach((path) => asking.add(path))

  const found = await Promise.all(
    wanted.map(async (path) => [path, await api.fileByPath(path)] as const),
  )

  wanted.forEach((path) => asking.delete(path))
  files.value = { ...files.value, ...Object.fromEntries(found) }
}

watch(
  () => items.value.map((item) => item.path),
  (paths) => void resolve(paths.filter(Boolean)),
  { immediate: true },
)

/** What was just chosen is already known; asking the library about it again is a round trip. */
function remember(chosen: MediaFile[]): void {
  files.value = { ...files.value, ...Object.fromEntries(chosen.map((file) => [file.path, file])) }
}

function fileOf(item: MediaValue): MediaFile | null | undefined {
  return files.value[item.path]
}

/** Only an answered question counts as broken: unasked is not the same as missing. */
function broken(item: MediaValue): boolean {
  return fileOf(item) === null
}

function nameOf(item: MediaValue): string {
  return fileOf(item)?.name ?? nameFromPath(item.path)
}

/*
 * Previews come from the thumbnail the server already made, never from the file itself: a
 * gallery of forty photographs drawn from the originals is forty originals.
 */
function thumbOf(item: MediaValue): string | undefined {
  const file = fileOf(item)

  return file && api ? (api.thumb(file, 320, 320) ?? undefined) : undefined
}

function metaOf(item: MediaValue): string {
  const file = fileOf(item)

  if (!file) return ''

  const size = readable(file.size)

  return file.width && file.height ? `${file.width}×${file.height} · ${size}` : size
}

/* ------------------------------------------------------------------ editing --- */

const editing = ref<number | null>(null)

function keyFor(item: MediaValue, index: number): string {
  // The same file twice in one list is unusual but not forbidden, and two rows under one key
  // is a list Vue cannot tell apart.
  const first = items.value.findIndex((other) => other.path === item.path)

  return first === index ? item.path : `${item.path}#${index}`
}

/**
 * The captions have to be readable as a record even when what was stored is the plain string a
 * column held before the site had a second language.
 */
function words(index: number, key: 'alt' | 'title'): LocalizedValue | string {
  return items.value[index]?.[key] ?? {}
}

function setWords(index: number, key: 'alt' | 'title', next: unknown): void {
  const current = items.value[index]

  if (!current) return

  const list = [...items.value]
  list[index] = { ...current, [key]: next as LocalizedValue }
  items.value = list
}

async function add(): Promise<void> {
  if (props.disabled) return

  const room = limit.value === null ? null : limit.value - items.value.length
  const chosen = await openMediaFiles({ accept: props.accept, max: room })

  if (!chosen || chosen.length === 0) return

  remember(chosen)

  const known = new Set(items.value.map((item) => item.path))
  const added = chosen
    .filter((file) => !known.has(file.path))
    .map<MediaValue>((file) => ({ path: file.path }))

  // Only the key and the order are kept: the address is worked out on every read, and writing
  // it into the value would be a copy that goes stale the moment the library moves.
  items.value = props.single ? added.slice(0, 1) : [...items.value, ...added]
}

/** Out of this record, not out of the library — the file is very likely used elsewhere. */
function remove(index: number): void {
  items.value = items.value.filter((_, at) => at !== index)
  editing.value = null
}

/**
 * The library, over the form.
 *
 * Cropping a file from here would crop it everywhere it is used, so the field does not offer
 * it — it offers the place where those consequences are visible. What comes back from that
 * dialog is ignored; what matters is that the picture may have changed, so it is asked about
 * again.
 */
async function openLibrary(item: MediaValue): Promise<void> {
  await openMediaLibrary({})

  const rest = { ...files.value }
  delete rest[item.path]
  files.value = rest

  void resolve([item.path])
}

/* ------------------------------------------------------------------ the frame --- */

const style = computed(() => {
  const columns = props.columns
    ? `repeat(${props.columns}, minmax(0, 1fr))`
    : 'repeat(auto-fill, minmax(140px, 1fr))'

  return props.aspect
    ? {
        '--wx-media-list-columns': columns,
        '--wx-media-list-aspect': RATIOS[props.aspect] ?? props.aspect,
      }
    : { '--wx-media-list-columns': columns }
})

const hint = computed(() => {
  const count = items.value.length

  if (props.min && count < props.min) return t('field.at-least', { count: props.min })

  return limit.value === null ? '' : t('field.count', { count, max: limit.value })
})
</script>

<template>
  <div
    class="wx-media-list"
    :class="[`wx-media-list--${layout}`, { 'is-disabled': disabled }]"
    :style="style"
  >
    <span v-if="label" class="wx-media-list__label">{{ label }}</span>

    <wx-sortable-list
      v-model="items"
      plain
      :disabled="disabled"
      :handle="layout === 'grid' ? 'row' : 'grip'"
      :item-key="keyFor"
      :item-label="nameOf"
      :drag-label="t('field.reorder')"
      :empty-text="t('field.empty')"
    >
      <template #default="{ item, index }">
        <div
          class="wx-media-list__cell"
          :class="{ 'is-broken': broken(item), 'is-busy': editing === index }"
        >
          <wx-file-card
            :name="nameOf(item)"
            :thumbnail="thumbOf(item)"
            :type="fileOf(item)?.mime"
            :size="layout === 'rows' ? 'sm' : 'md'"
          >
            <template #meta>
              <span v-if="broken(item)" class="wx-media-list__missing">
                {{ t('field.missing') }}
              </span>
              <span v-else>{{ metaOf(item) }}</span>
            </template>
          </wx-file-card>

          <div v-if="!disabled" class="wx-media-list__tools">
            <wx-actions size="sm">
              <!--
                The captions are asked for beside the picture they describe, the same way the
                single field asks for them: what they are about has to stay in sight while
                they are being written.
              -->
              <wx-popover
                v-if="captions"
                :open="editing === index"
                :title="t('field.captions')"
                :width="360"
                side="top"
                teleport
                @update:open="(open: boolean) => (editing = open ? index : null)"
              >
                <template #trigger>
                  <wx-action type="edit" :title="t('field.captions')" />
                </template>

                <div class="wx-media-list__captions">
                  <div class="wx-media-list__caption">
                    <span class="wx-media-list__caption-label">alt</span>
                    <wx-input
                      localized
                      :model-value="words(index, 'alt')"
                      :placeholder="t('field.alt-hint')"
                      @update:model-value="(next: unknown) => setWords(index, 'alt', next)"
                    />
                  </div>

                  <div class="wx-media-list__caption">
                    <span class="wx-media-list__caption-label">title</span>
                    <wx-input
                      localized
                      :model-value="words(index, 'title')"
                      :placeholder="t('field.title-hint')"
                      @update:model-value="(next: unknown) => setWords(index, 'title', next)"
                    />
                  </div>

                  <div class="wx-media-list__captions-footer">
                    <wx-button size="sm" type="primary" @click="editing = null">
                      {{ t('manager.save') }}
                    </wx-button>
                  </div>
                </div>
              </wx-popover>

              <wx-action
                v-if="!broken(item)"
                type="goto"
                :title="t('field.open')"
                @click="openLibrary(item)"
              />

              <wx-popconfirm
                :title="t('field.remove-title')"
                :description="t('field.remove-text')"
                :confirm-text="t('field.remove-confirm')"
                :cancel-text="t('manager.cancel')"
                confirm-type="danger"
                side="top"
                @confirm="remove(index)"
              >
                <template #trigger>
                  <wx-action type="remove" :title="t('field.remove-confirm')" />
                </template>
              </wx-popconfirm>
            </wx-actions>
          </div>
        </div>
      </template>
    </wx-sortable-list>

    <div class="wx-media-list__footer">
      <wx-button size="sm" variant="outline" :disabled="disabled || full" @click="add">
        {{ t('field.add') }}
      </wx-button>

      <span v-if="hint" class="wx-media-list__hint">{{ hint }}</span>
    </div>
  </div>
</template>

<style>
.wx-media-list {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-media-list__label {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-media-list__footer {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
}

.wx-media-list__hint {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

/*
 * The list brings its own rules with it, and they are written from here through three classes
 * rather than one: a component of the design system carries scoped styles, and a scoped
 * selector is a class plus an attribute — a single class from outside ties with it and loses to
 * whichever stylesheet happens to come last.
 */
.wx-media-list--grid .wx-sortable-list .wx-sortable-list__body {
  display: grid;
  grid-template-columns: var(--wx-media-list-columns);
  gap: var(--wx-space-8);
}

.wx-media-list--grid .wx-sortable-list .wx-sortable-list__row {
  display: block;
  padding: 0;
  border-top: none;
}

.wx-media-list--grid .wx-sortable-list .wx-sortable-list__empty {
  grid-column: 1 / -1;
  padding-inline: 0;
}

.wx-media-list__cell {
  position: relative;
  min-width: 0;
}

/* The shape of the frame is the field's to choose; the card's own 4/3 is the default. */
.wx-media-list--grid .wx-media-list__cell > .wx-file-card > .wx-file-card__preview {
  aspect-ratio: var(--wx-media-list-aspect, 4 / 3);
}

/*
 * A document in a list is a line, not a card standing on end: the picture shrinks to a mark at
 * the start of the row and the name runs along it.
 */
.wx-media-list--rows .wx-media-list__cell {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-media-list--rows .wx-media-list__cell > .wx-file-card {
  flex: 1 1 auto;
  flex-direction: row;
  align-items: center;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-media-list--rows .wx-media-list__cell > .wx-file-card > .wx-file-card__preview {
  flex: 0 0 auto;
  width: 48px;
  aspect-ratio: 1 / 1;
}

.wx-media-list--rows .wx-media-list__cell > .wx-file-card > .wx-file-card__body {
  flex: 1 1 auto;
  min-width: 0;
}

/* The card centres its name under the picture; beside one it belongs at the start of the line. */
.wx-media-list--rows .wx-media-list__cell .wx-file-card__name,
.wx-media-list--rows .wx-media-list__cell .wx-file-card__meta {
  text-align: start;
}

/* A key the library no longer answers about: visible, and with the button to take it out. */
.wx-media-list__cell.is-broken > .wx-file-card > .wx-file-card__preview {
  border-color: var(--wx-color-danger);
  background: var(--wx-color-danger-soft);
}

.wx-media-list__missing {
  color: var(--wx-color-danger);
}

.wx-media-list__tools {
  display: flex;
  padding: 2px;
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-sm);
  background: color-mix(in srgb, var(--wx-bg-surface) 88%, transparent);
  backdrop-filter: blur(2px);
}

.wx-media-list--grid .wx-media-list__tools {
  position: absolute;
  top: var(--wx-space-8);
  right: var(--wx-space-8);
}

/*
 * Out of the way until the pointer arrives — but only where one can arrive. A touch screen
 * never hovers, and buttons waiting for a hover that cannot happen are buttons nobody reaches.
 */
@media (hover: hover) {
  .wx-media-list--grid .wx-media-list__tools {
    opacity: 0;
    transition: opacity var(--wx-duration-fast) var(--wx-easing-standard);
  }

  /*
   * `is-busy` among them because the captions panel is teleported: while it is up neither the
   * pointer nor the focus is inside the cell, and the buttons would fade out from under it.
   */
  .wx-media-list--grid .wx-media-list__cell:hover .wx-media-list__tools,
  .wx-media-list--grid .wx-media-list__cell:focus-within .wx-media-list__tools,
  .wx-media-list--grid .wx-media-list__cell.is-busy .wx-media-list__tools {
    opacity: 1;
  }
}

.wx-media-list__captions {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  width: 100%;
}

.wx-media-list__caption {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
}

.wx-media-list__caption-label {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

.wx-media-list__captions-footer {
  display: flex;
  justify-content: flex-end;
  gap: var(--wx-space-8);
}
</style>
