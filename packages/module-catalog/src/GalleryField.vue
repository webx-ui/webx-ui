<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  confirm,
  toast,
  WxAction,
  WxAlert,
  WxBadge,
  WxButton,
  WxFormItem,
  WxInput,
  WxSortableList,
  WxText,
  WxUpload,
  type LocalizedValue,
  type UploadFile,
} from '@webx-ui/core'
import { createCatalogApi } from './api'
import { useProductEditor } from './editor'
import { useCatalogMessages } from './i18n'
import type { ProductImage } from './types'

/**
 * `wx-catalog-gallery`: the pictures of the product being edited (§10.4, §11.1).
 *
 * Not a value of the form. A picture is a record of its own with files on the disk, so adding one
 * is a request the moment it is chosen, deleting one deletes its files at once, and the order and
 * the captions are one request for the whole gallery — the editor's "Save" has nothing to do with
 * any of it. That is also why this node has no `name`: there is nothing for the form to carry.
 *
 * The captions save themselves a moment after the last keystroke, like a draft does elsewhere in
 * the panel; a reorder saves on the drop. Both send the whole gallery in its order, which is what
 * the server takes (`PUT …/images`).
 */
defineOptions({ name: 'WxCatalogGallery' })

/** How long after the last keystroke the captions go to the server. */
const PAUSE = 800

const admin = useAdmin()
const api = createCatalogApi(admin)
const editor = useProductEditor()
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

/**
 * One file after another rather than all at once: the server numbers them in the order they
 * arrive, and a gallery whose order is whichever upload finished first is not the order anybody
 * chose them in.
 */
async function add(files: UploadFile[]): Promise<void> {
  const id = productId.value

  if (id === null) return

  for (const file of files) {
    if (!file.raw) continue

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

async function fetchAddress(): Promise<void> {
  const id = productId.value
  const url = address.value.trim()

  if (id === null || url === '') return

  fetching.value = true

  try {
    images.value = [...images.value, await api.addImage(id, url)]
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

  try {
    await api.removeImage(id, image.id)
    images.value = images.value.filter((one) => one.id !== image.id)
    toast.success(t('panel.gallery-removed'))
  } catch (error) {
    toast.danger(message(error))
  }
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
            <a
              class="wx-catalog-gallery__thumb"
              :href="(item as ProductImage).url"
              target="_blank"
              rel="noopener"
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
            </a>

            <div class="wx-catalog-gallery__words">
              <wx-form-item :label="t('panel.gallery-alt')" :help="t('panel.gallery-alt-help')">
                <wx-input
                  :model-value="(item as ProductImage).alt"
                  localized
                  size="sm"
                  :disabled="locked"
                  @update:model-value="(text) => caption(item as ProductImage, 'alt', text)"
                />
              </wx-form-item>

              <wx-form-item :label="t('panel.gallery-title')">
                <wx-input
                  :model-value="(item as ProductImage).title"
                  localized
                  size="sm"
                  :disabled="locked"
                  @update:model-value="(text) => caption(item as ProductImage, 'title', text)"
                />
              </wx-form-item>

              <wx-text
                v-if="(item as ProductImage).width && (item as ProductImage).height"
                size="sm"
                tone="muted"
              >
                {{ (item as ProductImage).width }} × {{ (item as ProductImage).height }}
              </wx-text>
            </div>
          </div>
        </template>

        <template #actions="{ item }">
          <wx-action
            v-if="!locked"
            type="remove"
            size="sm"
            :title="t('panel.delete')"
            @click="remove(item as ProductImage)"
          />
        </template>
      </wx-sortable-list>

      <wx-text v-else-if="!locked" tone="muted">{{ t('panel.gallery-empty') }}</wx-text>

      <template v-if="!locked">
        <wx-upload
          v-model="uploads"
          accept="image/jpeg,image/png,image/webp,image/gif"
          multiple
          :button-text="t('panel.gallery-add')"
          :hint="t('panel.gallery-hint')"
          @add="add"
        />

        <!-- Not a <form>: the gallery stands inside the screen's own, and Enter here must fetch
             the picture rather than submit the product. -->
        <wx-form-item :label="t('panel.gallery-url')">
          <div class="wx-catalog-gallery__address">
            <wx-input
              v-model="address"
              type="url"
              size="sm"
              placeholder="https://"
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
        </wx-form-item>
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
  grid-template-columns: 96px minmax(0, 1fr);
  gap: var(--wx-space-16);
  align-items: start;
  padding-block: var(--wx-space-8);
}

.wx-catalog-gallery__thumb {
  position: relative;
  display: block;
  width: 96px;
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
  inset-inline-start: var(--wx-space-4);
  inset-block-start: var(--wx-space-4);
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

  .wx-catalog-gallery__words > .wx-text {
    grid-column: 1 / -1;
  }
}

/* A phone: the picture above its words rather than a narrow column beside them. */
@container (max-width: 420px) {
  .wx-catalog-gallery__item {
    grid-template-columns: minmax(0, 1fr);
  }
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
</style>
