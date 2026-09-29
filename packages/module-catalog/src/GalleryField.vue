<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useTemplateRef } from 'vue'
import {
  uploadFingerprint,
  useAdmin,
  useErrorText,
  useTranslate,
  WxRowMenu,
  type RowAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  toast,
  useLocales,
  WxAlert,
  WxBadge,
  WxButton,
  WxCard,
  WxInput,
  WxSortableList,
  WxText,
  WxUpload,
  type LocalizedValue,
  type UploadFile,
} from '@webx-ui/core'
import { createCatalogApi } from './api'
import { useProductEditor } from './editor'
import { createGalleryVideo, type GalleryVideo, type UnfinishedVideo } from './galleryVideo'
import { useCatalogMessages } from './i18n'
import type { ProductImage, QueuedVideo } from './types'
import { formatBytes, formatClock, hasPlaceholderPoster, videoRefusal } from './video'
import VideoLinkDialog from './VideoLinkDialog.vue'
import VideoProgress from './VideoProgress.vue'

/**
 * `wx-catalog-gallery`: the pictures of the product being edited (§10.4, §11.1), and the videos
 * attached to them (the video spec, §7).
 *
 * Not a value of the form. A picture is a record of its own with files on the disk, so adding one
 * is a request the moment it is chosen, deleting one deletes its files at once, and the order and
 * the captions are one request for the whole gallery — the editor's "Save" has nothing to do with
 * any of it. That is also why this node has no `name`: there is nothing for the form to carry.
 *
 * The captions save themselves a moment after the last keystroke, like a draft does elsewhere in
 * the panel; a reorder saves on the drop. Both send the whole gallery in its order, which is what
 * the server takes (`PUT …/images`).
 *
 * A video is never a row of its own: it hangs on a picture, which is its poster. A file dropped
 * here becomes a picture of its own frame first, then goes up a piece at a time; the uploads are
 * the editor's (`galleryVideo.ts`), so they carry on while another tab of the form is open.
 */
defineOptions({ name: 'WxCatalogGallery' })

const props = withDefaults(
  defineProps<{
    /** Videos on this site (`webx-catalog.fields.video`); the server's screen patch says. */
    video?: boolean
    /** What a video file may be, checked before a byte of it is sent. */
    videoTypes?: string[]
    /** How large, in bytes. */
    videoMaxBytes?: number
  }>(),
  {
    // Off unless the server says so: a server without videos would refuse the file at the end.
    video: false,
    videoTypes: () => ['video/mp4', 'video/webm'],
    videoMaxBytes: 2048 * 1048576,
  },
)

/** How long after the last keystroke the captions go to the server. */
const PAUSE = 800

const PICTURES = 'image/jpeg,image/png,image/webp,image/gif'

const admin = useAdmin()
const api = createCatalogApi(admin)
const editor = useProductEditor()
const locales = useLocales()
useCatalogMessages()

const t = useTranslate('webx-catalog')
const message = useErrorText()

const uploads = ref<UploadFile[]>([])
const address = ref('')
const fetching = ref(false)

const productId = computed(() => editor?.product.value?.id ?? null)
const locked = computed(() => editor === null || editor.locked.value)

/** Writable, because that is how `WxSortableList` hands a reorder back. */
const images = computed<ProductImage[]>({
  get: () => editor?.images.value ?? [],
  set: (next) => {
    if (editor) editor.images.value = next
  },
})

/*
 * The editor's, so that an upload outlives this tab; a gallery under an editor that keeps none
 * makes its own. Never used while videos are off, so no upload can start then.
 */
const uploader: GalleryVideo | null =
  editor === null ? null : (editor.video ?? createGalleryVideo({ product: productId, images }))

const videos = computed(() => props.video && uploader !== null)
const accept = computed(() => (videos.value ? [PICTURES, ...props.videoTypes].join(',') : PICTURES))

const jobs = computed(() => uploader?.jobs.value ?? [])
/* The ones that have no row yet: the frame is being taken, or the browser could not open the file. */
const pending = computed(() => jobs.value.filter((job) => job.image === null))
const unfinished = computed(() => (videos.value && !locked.value ? uploader!.unfinished.value : []))

const openLinkDialog = createModal<ProductImage | QueuedVideo, { product: number; image: number }>(
  VideoLinkDialog,
)

/* What was sent last, so an answer that arrives after further typing does not undo it. */
let timer: ReturnType<typeof setTimeout> | undefined
let sent = ''

function captions(list: ProductImage[]): string {
  return JSON.stringify(list.map((image) => [image.id, image.alt, image.title]))
}

async function saveGallery(failure: string): Promise<void> {
  const id = productId.value

  if (id === null) return

  clearTimeout(timer)
  const carrying = captions(images.value)
  sent = carrying

  try {
    const answer = await api.saveImages(id, images.value)

    // Typed on while the request was out: keep what is on screen; the next pause sends it.
    // The videos of rows still uploading are not the server's to overwrite either way.
    if (captions(images.value) === carrying) images.value = answer
  } catch (error) {
    toast.danger(`${failure} ${message(error)}`)
  }
}

function caption(image: ProductImage, field: 'alt' | 'title', text: unknown): void {
  images.value = images.value.map((one) =>
    one.id === image.id ? { ...one, [field]: (text ?? {}) as LocalizedValue } : one,
  )

  clearTimeout(timer)
  timer = setTimeout(() => void saveGallery(t('panel.gallery-captions-failed')), PAUSE)
}

function moved(): void {
  void saveGallery(t('panel.gallery-reorder-failed'))
}

/** A video file the screen's limits allow, said in words when they do not. */
function acceptable(file: File): boolean {
  const refusal = videoRefusal(file, props.videoTypes, props.videoMaxBytes)

  if (refusal === 'type') toast.danger(t('panel.video-wrong-type'))
  if (refusal === 'size') {
    toast.danger(
      t('panel.video-too-large', { max: formatBytes(props.videoMaxBytes, locales.active.value) }),
    )
  }

  return refusal === null
}

function isVideo(file: File): boolean {
  return file.type.startsWith('video/')
}

/**
 * One file after another rather than all at once: the server numbers them in the order they
 * arrive, and a gallery whose order is whichever upload finished first is not the order anybody
 * chose them in. Videos go their own way — to the editor's queue, which works one at a time.
 */
async function add(files: UploadFile[]): Promise<void> {
  const id = productId.value

  if (id === null) return

  for (const file of files) {
    if (!file.raw) continue

    if (isVideo(file.raw)) {
      uploads.value = uploads.value.filter((one) => one.id !== file.id)

      if (videos.value && acceptable(file.raw)) uploader!.add(file.raw)

      continue
    }

    const mark = (patch: Partial<UploadFile>) => {
      uploads.value = uploads.value.map((one) => (one.id === file.id ? { ...one, ...patch } : one))
    }

    mark({ status: 'uploading', progress: 0 })

    try {
      const image = await api.addImage(id, file.raw, (progress) => mark({ progress }))

      images.value = [...images.value, image]
      uploads.value = uploads.value.filter((one) => one.id !== file.id)
    } catch (error) {
      mark({ status: 'error', error: message(error) })
    }
  }
}

function rejected(_file: File, reason: string): void {
  if (reason === 'type') {
    toast.danger(videos.value ? t('panel.gallery-wrong-type-video') : t('panel.gallery-wrong-type'))
  }
}

function queued(): void {
  toast.info(t('panel.video-queued'))
}

async function fetchAddress(): Promise<void> {
  const id = productId.value
  const url = address.value.trim()

  if (id === null || url === '') return

  fetching.value = true

  try {
    const answer = await api.fetchImage(id, url)

    if ('queued' in answer) {
      queued()
    } else {
      images.value = [...images.value, answer]
    }

    address.value = ''
  } catch (error) {
    toast.danger(`${t('panel.gallery-failed')} ${message(error)}`)
  } finally {
    fetching.value = false
  }
}

async function remove(image: ProductImage): Promise<void> {
  const id = productId.value

  if (id === null) return

  const agreed = await confirm({
    title: t('panel.gallery-remove-title'),
    message: t('panel.gallery-remove-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  // A video still going onto it stops first; one that made this picture takes it away itself.
  const job = jobs.value.find((one) => one.image === image.id)

  if (job !== undefined) await uploader!.cancel(job)
  if (!images.value.some((one) => one.id === image.id)) return

  try {
    await api.removeImage(id, image.id)
    images.value = images.value.filter((one) => one.id !== image.id)
    toast.success(t('panel.gallery-removed'))
  } catch (error) {
    toast.danger(message(error))
  }
}

/* ------------------------------------------------------------------------ videos ----- */

const picker = useTemplateRef<HTMLInputElement>('picker')
/* The row «Attach a video file…» was chosen on; `null` for continuing an unfinished upload. */
let pickFor: { image: number | null; resume?: UnfinishedVideo } | null = null

function pickFile(image: number | null, resume?: UnfinishedVideo): void {
  pickFor = { image, resume }

  if (picker.value) {
    picker.value.value = ''
    picker.value.click()
  }
}

function picked(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0]
  const target = pickFor
  pickFor = null

  if (!file || target === null || uploader === null) return

  if (target.resume !== undefined) {
    if (uploadFingerprint(file) !== target.resume.fingerprint) {
      toast.danger(
        t('panel.video-other-file', {
          name: target.resume.name,
          size: formatBytes(target.resume.size, locales.active.value),
        }),
      )

      return
    }

    // Onto the picture it was for, while that is still here; a row of its own otherwise.
    const image = images.value.some((one) => one.id === target.resume!.image)
      ? target.resume.image
      : null

    if (acceptable(file)) uploader.add(file, image)

    return
  }

  if (acceptable(file)) uploader.add(file, target.image)
}

async function attachLink(image: ProductImage): Promise<void> {
  const id = productId.value

  if (id === null) return

  const answer = await openLinkDialog({ product: id, image: image.id })

  if (answer === undefined) return

  if ('queued' in answer) {
    queued()
  } else {
    images.value = images.value.map((one) => (one.id === answer.id ? answer : one))
    toast.success(t('panel.video-attached'))
  }
}

async function detach(image: ProductImage): Promise<void> {
  const id = productId.value

  if (id === null) return

  const agreed = await confirm({
    title: t('panel.video-remove-title'),
    message:
      image.video?.provider === 'file'
        ? t('panel.video-remove-file-text')
        : t('panel.video-remove-link-text'),
    confirmText: t('panel.video-remove'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    const answer = await api.detachVideo(id, image.id)

    images.value = images.value.map((one) => (one.id === answer.id ? answer : one))
    toast.success(t('panel.video-removed'))
  } catch (error) {
    toast.danger(message(error))
  }
}

function actions(image: ProductImage): RowAction[] {
  const list: RowAction[] = []
  const going = jobs.value.some((job) => job.image === image.id)

  if (videos.value && !going) {
    if (image.video) {
      list.push({
        key: 'detach',
        label: t('panel.video-remove'),
        icon: 'close',
        // Not red: the picture stays, and the question that follows says what goes.
        run: () => void detach(image),
      })
    } else {
      list.push(
        {
          key: 'file',
          label: t('panel.video-attach-file'),
          icon: 'upload',
          run: () => pickFile(image.id),
        },
        {
          key: 'link',
          label: t('panel.video-attach-link'),
          icon: 'link',
          run: () => void attachLink(image),
        },
      )
    }
  }

  list.push({
    key: 'delete',
    label: t('panel.delete'),
    icon: 'trash',
    danger: true,
    run: () => void remove(image),
  })

  return list
}

/* The text form of the triangle: Windows draws the bare character as a coloured emoji. */
const PLAY = '\u25B6\uFE0E'

function videoLabel(image: ProductImage): string {
  const duration = image.video?.duration

  return duration ? `${PLAY} ${formatClock(duration)}` : PLAY
}

function jobOf(image: ProductImage) {
  return jobs.value.find((job) => job.image === image.id)
}

function unfinishedText(entry: UnfinishedVideo): string {
  const locale = locales.active.value

  return t('panel.video-unfinished', {
    name: entry.name,
    sent: formatBytes(entry.offset, locale),
    total: formatBytes(entry.size, locale),
  })
}

/* Leaving with a pause still running: send it rather than drop the last words typed. */
onBeforeUnmount(() => {
  if (timer !== undefined && captions(images.value) !== sent) void saveGallery('')
  clearTimeout(timer)
})
</script>

<template>
  <div class="wx-catalog-gallery">
    <wx-alert v-if="!editor" type="info" variant="soft" :description="t('panel.gallery-empty')" />

    <template v-else>
      <wx-alert
        v-if="locked && images.length === 0"
        type="info"
        variant="soft"
        :description="t('panel.gallery-locked')"
      />

      <wx-alert
        v-for="entry in unfinished"
        :key="entry.fingerprint"
        type="warning"
        variant="soft"
        :description="unfinishedText(entry)"
      >
        <template #actions>
          <wx-button size="sm" variant="outline" @click="pickFile(null, entry)">
            {{ t('panel.video-choose-again') }}
          </wx-button>
          <wx-button size="sm" variant="text" @click="uploader!.forget(entry)">
            {{ t('panel.video-forget') }}
          </wx-button>
        </template>
      </wx-alert>

      <wx-sortable-list
        v-if="images.length > 0"
        v-model="images"
        class="wx-catalog-gallery__list"
        item-key="id"
        :item-label="(image: ProductImage) => image.path.split('/').pop() ?? String(image.id)"
        :disabled="locked"
        :title="t('panel.gallery-order')"
        @move="moved"
      >
        <template #default="{ item, index }">
          <div class="wx-catalog-gallery__item">
            <!-- A row with a video opens the video: the picture is only its poster. -->
            <a
              class="wx-catalog-gallery__thumb"
              :href="(item as ProductImage).video?.url ?? (item as ProductImage).url"
              target="_blank"
              rel="noopener"
              :title="(item as ProductImage).video ? t('panel.video-open') : undefined"
            >
              <img :src="(item as ProductImage).thumb ?? (item as ProductImage).url" alt="" />
              <wx-badge
                v-if="index === 0"
                class="wx-catalog-gallery__main"
                type="primary"
                size="sm"
              >
                {{ t('panel.gallery-main') }}
              </wx-badge>
              <span
                v-if="(item as ProductImage).video"
                class="wx-catalog-gallery__play"
                :aria-label="t('panel.video-badge')"
              >
                {{ videoLabel(item as ProductImage) }}
              </span>
              <!-- On the picture rather than under the fields: a line of its own made every row
                   taller and left the fields hanging off-centre. -->
              <span
                v-if="(item as ProductImage).width && (item as ProductImage).height"
                class="wx-catalog-gallery__size"
              >
                {{ (item as ProductImage).width }}×{{ (item as ProductImage).height }}
              </span>
            </a>

            <div class="wx-catalog-gallery__body">
              <!-- No label above each field: the placeholder says which is which, and a row a
                   label taller is a list where three pictures fill the screen. -->
              <div class="wx-catalog-gallery__words">
                <wx-input
                  :model-value="(item as ProductImage).alt"
                  localized
                  size="sm"
                  :placeholder="t('panel.gallery-alt')"
                  :aria-label="t('panel.gallery-alt')"
                  :title="t('panel.gallery-alt-help')"
                  :disabled="locked"
                  @update:model-value="(text) => caption(item as ProductImage, 'alt', text)"
                />
                <wx-input
                  :model-value="(item as ProductImage).title"
                  localized
                  size="sm"
                  :placeholder="t('panel.gallery-title')"
                  :aria-label="t('panel.gallery-title')"
                  :disabled="locked"
                  @update:model-value="(text) => caption(item as ProductImage, 'title', text)"
                />
              </div>

              <video-progress
                v-if="uploader && jobOf(item as ProductImage)"
                :job="jobOf(item as ProductImage)!"
                :video="uploader"
              />

              <wx-text
                v-else-if="videos && hasPlaceholderPoster(item as ProductImage)"
                size="sm"
                tone="muted"
              >
                {{ t('panel.video-placeholder') }}
              </wx-text>
            </div>
          </div>
        </template>

        <template #actions="{ item }">
          <wx-row-menu
            v-if="!locked"
            :actions="actions(item as ProductImage)"
            :label="(item as ProductImage).path.split('/').pop()"
          />
        </template>
      </wx-sortable-list>

      <wx-text v-else-if="!locked && pending.length === 0" tone="muted">
        {{ t('panel.gallery-empty') }}
      </wx-text>

      <!-- A video dropped without a picture, before its frame is a row of the gallery. -->
      <ul v-if="uploader && pending.length > 0" class="wx-catalog-gallery__pending">
        <li v-for="job in pending" :key="job.key" class="wx-catalog-gallery__item">
          <span class="wx-catalog-gallery__thumb">
            <img v-if="job.poster" :src="job.poster" alt="" />
            <span class="wx-catalog-gallery__play">{{ PLAY }}</span>
          </span>
          <div class="wx-catalog-gallery__body">
            <wx-text size="sm" class="wx-catalog-gallery__name">{{ job.file.name }}</wx-text>
            <video-progress :job="job" :video="uploader" />
          </div>
        </li>
      </ul>

      <template v-if="!locked">
        <wx-upload
          v-model="uploads"
          :accept="accept"
          multiple
          :button-text="videos ? t('panel.gallery-add-video') : t('panel.gallery-add')"
          :hint="videos ? t('panel.gallery-hint-video') : t('panel.gallery-hint')"
          @add="add"
          @reject="rejected"
        />

        <input
          v-if="videos"
          ref="picker"
          type="file"
          class="wx-catalog-gallery__picker"
          :accept="videoTypes.join(',')"
          tabindex="-1"
          aria-hidden="true"
          @change="picked"
        />

        <!-- A card of its own: a way in that is rarely taken, kept out of the way of the one that is.
             Not a <form>: the gallery stands inside the screen's own, and Enter here must fetch
             the picture rather than submit the product. -->
        <wx-card :title="t('panel.gallery-url')">
          <div class="wx-catalog-gallery__address">
            <wx-input
              v-model="address"
              type="url"
              size="sm"
              placeholder="https://"
              :aria-label="t('panel.gallery-url')"
              @keydown.enter.prevent="fetchAddress"
            />
            <wx-button
              size="sm"
              variant="outline"
              :loading="fetching"
              :disabled="address.trim() === ''"
              @click="fetchAddress"
            >
              {{ t('panel.gallery-url-add') }}
            </wx-button>
          </div>
          <wx-text size="sm" tone="muted" class="wx-catalog-gallery__address-hint">
            {{ videos ? t('panel.gallery-url-hint-video') : t('panel.gallery-url-hint') }}
          </wx-text>
        </wx-card>
      </template>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-gallery {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  container-type: inline-size;
}

.wx-catalog-gallery :deep(.wx-sortable-list__content) {
  min-width: 0;
}

.wx-catalog-gallery__item {
  display: grid;
  grid-template-columns: 72px minmax(0, 1fr);
  gap: var(--wx-space-12);
  align-items: center;
}

.wx-catalog-gallery__thumb {
  position: relative;
  display: block;
  width: 72px;
  aspect-ratio: 1;
  overflow: clip;
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-subtle);
  border: 1px solid var(--wx-border-default);
}

.wx-catalog-gallery__thumb img {
  width: 100%;
  height: 100%;
  min-width: 0;
  object-fit: contain;
  display: block;
}

.wx-catalog-gallery__main {
  position: absolute;
  inset-inline-start: var(--wx-space-2);
  inset-block-start: var(--wx-space-2);
}

/*
 * The dim behind a dialog and a fixed white, not the inverse text: that one turns dark in the dark
 * theme, and the strip is dark in both — it has to read on whatever the picture is.
 */
.wx-catalog-gallery__size,
.wx-catalog-gallery__play {
  position: absolute;
  background: var(--wx-bg-overlay);
  color: var(--wx-color-white);
  font-size: var(--wx-font-size-xs);
  line-height: 1.2;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}

.wx-catalog-gallery__size {
  inset-inline: 0;
  inset-block-end: 0;
  padding-block: var(--wx-space-2);
  text-align: center;
}

/*
 * In the middle, where every video's poster has it: a corner beside «Main» has no room left on a
 * 72-pixel picture once the word is in a longer language.
 */
.wx-catalog-gallery__play {
  inset-block-start: 50%;
  inset-inline-start: 50%;
  translate: -50% -50%;
  padding: var(--wx-space-2) var(--wx-space-4);
  border-radius: var(--wx-radius-xs);
}

.wx-catalog-gallery__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

/* Two fields side by side while there is room for two readable lines of text, stacked below. */
.wx-catalog-gallery__words {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--wx-space-8);
}

@container (min-width: 640px) {
  .wx-catalog-gallery__words {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

/* A phone: the picture above its words rather than a narrow column beside them. */
@container (max-width: 420px) {
  .wx-catalog-gallery__item {
    grid-template-columns: minmax(0, 1fr);
  }
}

.wx-catalog-gallery__pending {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  margin: 0;
  padding: var(--wx-space-8) var(--wx-space-12);
  list-style: none;
  border: 1px dashed var(--wx-border-default);
  border-radius: var(--wx-radius-md);
}

.wx-catalog-gallery__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-catalog-gallery__picker {
  display: none;
}

.wx-catalog-gallery__address {
  display: flex;
  gap: var(--wx-space-8);
  align-items: center;
  max-width: 560px;
}

.wx-catalog-gallery__address > :first-child {
  flex: 1;
  min-width: 0;
}

.wx-catalog-gallery__address-hint {
  display: block;
  margin-block-start: var(--wx-space-8);
}
</style>
