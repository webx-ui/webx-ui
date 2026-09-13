<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/admin'
import {
  confirm,
  openImageEditor,
  toast,
  WxButton,
  WxInput,
  WxPagination,
  WxSelect,
  WxSpace,
  WxUpload,
  type UploadFile,
} from '@webx-ui/core'
import DirectoryTree from './DirectoryTree.vue'
import FileGrid from './FileGrid.vue'
import { createMediaApi } from './api'
import { useMediaMessages } from './i18n'
import type { MediaDirectory, MediaFile, MediaKind, MediaPage } from './types'

/**
 * The library: folders on the left, files on the right, and a toolbar over both.
 *
 * Everything the screen does is one request and a reload of the part that changed — there is no
 * cache here on purpose. A library is edited by several people, and a panel that believes its
 * own copy shows a folder that was emptied an hour ago.
 */
const props = withDefaults(
  defineProps<{
    /** A picker hands one file back instead of managing the library. */
    picking?: boolean
    /** Only these kinds can be chosen, when picking. */
    accept?: MediaKind | null
  }>(),
  { picking: false, accept: null },
)

const emit = defineEmits<{ pick: [file: MediaFile] }>()

const admin = useAdmin()
const api = createMediaApi(admin)
useMediaMessages()

const t = useTranslate('webx-media')

const directories = ref<MediaDirectory[]>([])
const current = ref<number | null>(null)
const page = ref<MediaPage | null>(null)
const selected = ref<number[]>([])
const search = ref('')
const type = ref<MediaKind | null>(props.accept)
const sort = ref('-created_at')
const busy = ref(false)

const files = computed(() => page.value?.data ?? [])
const canManage = computed(() => admin.can('media.manage'))
const canUpload = computed(() => admin.can('media.upload') || canManage.value)

const types = computed(() => [
  { value: '', label: t('manager.all-types') },
  ...(['image', 'video', 'audio', 'document', 'other'] as MediaKind[]).map((kind) => ({
    value: kind,
    label: t(`manager.${kind}`),
  })),
])

const sorts = computed(() => [
  { value: '-created_at', label: t('manager.sort-newest') },
  { value: 'created_at', label: t('manager.sort-oldest') },
  { value: 'name', label: t('manager.sort-name') },
  { value: '-size', label: t('manager.sort-size') },
])

onMounted(load)

watch([current, type, sort], () => loadFiles(1))
watch(
  search,
  debounce(() => loadFiles(1), 300),
)

async function load(): Promise<void> {
  directories.value = await api.directories()
  current.value ??= directories.value[0]?.id ?? null
  await loadFiles(1)
}

async function loadFiles(to = page.value?.meta.current_page ?? 1): Promise<void> {
  busy.value = true

  try {
    page.value = await api.files({
      directory_id: search.value ? null : current.value,
      q: search.value,
      type: type.value,
      sort: sort.value,
      page: to,
    })
    selected.value = []
  } finally {
    busy.value = false
  }
}

async function upload(added: UploadFile[]): Promise<void> {
  const raw = added.map((file) => file.raw).filter((file): file is File => Boolean(file))

  if (raw.length === 0 || current.value === null) {
    return
  }

  try {
    const stored = await api.upload(current.value, raw)
    const duplicates = stored.filter((file) => file.duplicate).length

    toast.success(t('manager.uploaded', { count: stored.length - duplicates }))

    if (duplicates > 0) {
      toast.info(t('manager.duplicate-added'))
    }

    await Promise.all([load(), loadFiles(1)])
  } catch (error) {
    toast.danger(messageOf(error) ?? t('errors.upload'))
  }
}

async function createFolder(): Promise<void> {
  const title = window.prompt(t('manager.folder-name'))

  if (!title || current.value === null) {
    return
  }

  await api.createDirectory(current.value, title)
  await load()
}

async function renameFolder(): Promise<void> {
  const folder = find(current.value)

  if (!folder || folder.is_root) {
    return
  }

  const title = window.prompt(t('manager.rename'), folder.title)

  if (title) {
    await api.renameDirectory(folder.id, title)
    await load()
  }
}

async function deleteFolder(): Promise<void> {
  const folder = find(current.value)

  if (!folder || folder.is_root) {
    return
  }

  // Asked twice on purpose, and the second question carries the counts the server sent back:
  // what goes with the folder is somebody's article illustrations.
  try {
    await api.deleteDirectory(folder.id)
  } catch (error) {
    const counts = countsOf(error)

    if (!counts) {
      throw error
    }

    const agreed = await confirm({
      title: t('dialogs.delete-folder-title', { title: folder.title }),
      message: `${t('dialogs.delete-folder-contents', counts)} ${t('dialogs.delete-folder-warning')}`,
      confirmText: t('dialogs.confirm'),
      cancelText: t('manager.cancel'),
      tone: 'danger',
    })

    if (!agreed) {
      return
    }

    await api.deleteDirectory(folder.id, true)
  }

  current.value = null
  await load()
}

async function moveFolder(id: number, parentId: number): Promise<void> {
  try {
    await api.moveDirectory(id, parentId)
  } catch (error) {
    toast.danger(messageOf(error) ?? t('errors.directory-into-itself'))
  }

  await load()
}

async function removeSelected(): Promise<void> {
  const ids = [...selected.value]

  const agreed = await confirm({
    title: t('dialogs.delete-files-title', { count: ids.length }),
    message: t('dialogs.delete-files-text'),
    confirmText: t('dialogs.confirm'),
    cancelText: t('manager.cancel'),
    tone: 'danger',
  })

  if (agreed) {
    await api.remove(ids)
    await Promise.all([load(), loadFiles()])
  }
}

async function moveSelected(): Promise<void> {
  const target = window.prompt(t('manager.move-to'))
  const id = Number(target)

  if (!Number.isFinite(id) || id <= 0) {
    return
  }

  await api.move([...selected.value], id)
  await Promise.all([load(), loadFiles()])
}

async function rename(file: MediaFile, name: string): Promise<void> {
  await api.rename(file.id, name)
  await loadFiles()
}

async function remove(file: MediaFile): Promise<void> {
  await api.removeOne(file.id)
  await Promise.all([load(), loadFiles()])
}

/**
 * The editor works on a preview and hands back a blob; the server is told the operations
 * instead and applies them to the original, at full size.
 *
 * Filters and adjustments are switched off for the same reason: they cannot be said as an
 * operation, and a picture that is quietly smaller than it was is worse than a button that is
 * not there.
 */
async function edit(file: MediaFile): Promise<void> {
  const result = await openImageEditor({ src: file.url, fileName: file.file_name, filters: false })

  if (!result) {
    return
  }

  await api.edit(file.id, {
    crop: result.crop,
    rotate: (((result.rotation % 360) + 360) % 360) as 0 | 90 | 180 | 270,
    flip: result.flipX ? 'horizontal' : result.flipY ? 'vertical' : undefined,
  })

  await loadFiles()
}

function find(id: number | null): MediaDirectory | null {
  const walk = (nodes: MediaDirectory[]): MediaDirectory | null => {
    for (const node of nodes) {
      if (node.id === id) {
        return node
      }

      const deeper = walk(node.children ?? [])

      if (deeper) {
        return deeper
      }
    }

    return null
  }

  return id === null ? null : walk(directories.value)
}

function countsOf(error: unknown): { files: number; directories: number } | null {
  const body = (
    error as { body?: { code?: string; counts?: { files: number; directories: number } } }
  )?.body

  return body?.code === 'directory_not_empty' ? (body.counts ?? null) : null
}

function messageOf(error: unknown): string | null {
  return (error as { message?: string })?.message ?? null
}

function debounce(run: () => void, wait: number): () => void {
  let timer: ReturnType<typeof setTimeout> | undefined

  return () => {
    clearTimeout(timer)
    timer = setTimeout(run, wait)
  }
}
</script>

<template>
  <div class="wx-media">
    <aside class="wx-media__folders">
      <directory-tree v-model:selected="current" :directories="directories" @move="moveFolder" />

      <wx-space v-if="canManage" size="4" wrap>
        <wx-button size="sm" variant="text" icon="folder-plus" @click="createFolder">
          {{ t('manager.new-folder') }}
        </wx-button>
        <wx-button size="sm" variant="text" icon="pencil" @click="renameFolder">
          {{ t('manager.rename') }}
        </wx-button>
        <wx-button size="sm" variant="text" type="danger" icon="trash" @click="deleteFolder">
          {{ t('manager.delete') }}
        </wx-button>
      </wx-space>
    </aside>

    <section class="wx-media__files">
      <header class="wx-media__bar">
        <wx-input v-model="search" :placeholder="t('manager.search')" clearable size="sm" />
        <wx-select v-model="type" :options="types" size="sm" />
        <wx-select v-model="sort" :options="sorts" size="sm" />

        <wx-space v-if="selected.length > 0" size="4">
          <span class="wx-media__count">{{
            t('manager.selected', { count: selected.length })
          }}</span>
          <wx-button v-if="canManage" size="sm" variant="text" @click="moveSelected">
            {{ t('manager.move') }}
          </wx-button>
          <wx-button
            v-if="canManage"
            size="sm"
            variant="text"
            type="danger"
            @click="removeSelected"
          >
            {{ t('manager.delete') }}
          </wx-button>
        </wx-space>
      </header>

      <wx-upload
        v-if="canUpload && !picking"
        class="wx-media__upload"
        multiple
        button-only
        :button-text="t('manager.upload')"
        :hint="t('manager.upload-hint')"
        @add="upload"
      />

      <file-grid
        v-model:selected="selected"
        :files="files"
        :api="api"
        :query="search"
        :single="picking"
        @rename="rename"
        @edit="edit"
        @remove="remove"
        @open="(file) => emit('pick', file)"
      />

      <wx-pagination
        v-if="page && page.meta.last_page > 1"
        :page="page.meta.current_page"
        :per-page="page.meta.per_page"
        :total="page.meta.total"
        :last-page="page.meta.last_page"
        :disabled="busy"
        @change="({ page: to }) => loadFiles(to)"
      />
    </section>
  </div>
</template>

<style>
.wx-media {
  display: grid;
  grid-template-columns: minmax(180px, 240px) minmax(0, 1fr);
  gap: var(--wx-space-16);
  container-type: inline-size;
  min-height: 0;
  height: 100%;
}

.wx-media__folders {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-height: 0;
  border-right: 1px solid var(--wx-color-border);
  padding-right: var(--wx-space-12);
}

.wx-media__files {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-height: 0;
}

.wx-media__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-media__count {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-color-text-muted);
}

/* The panel decides, not the window: this screen is also a dialog half the width of one. */
@container (max-width: 640px) {
  .wx-media {
    grid-template-columns: minmax(0, 1fr);
  }

  .wx-media__folders {
    border-right: none;
    border-bottom: 1px solid var(--wx-color-border);
    padding-right: 0;
    padding-bottom: var(--wx-space-8);
    max-height: 30cqh;
  }
}
</style>
