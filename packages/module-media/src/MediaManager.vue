<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef, watch } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  openImageEditor,
  toast,
  useElementWidth,
  WxAction,
  WxActions,
  WxDrawer,
  WxPagination,
} from '@webx-ui/core'
import DirectoryTree from './DirectoryTree.vue'
import FileGrid from './FileGrid.vue'
import MediaToolbar from './MediaToolbar.vue'
import MoveDialog from './MoveDialog.vue'
import NameDialog from './NameDialog.vue'
import { createMediaApi } from './api'
import { readable } from './format'
import { useMediaMessages } from './i18n'
import type { MediaDirectory, MediaFile, MediaKind, MediaPage } from './types'

/**
 * The library: folders on the left, files on the right, a row of icons over both.
 *
 * Everything the screen does is one request and a reload of the part that changed — there is no
 * cache here on purpose. A library is edited by several people, and a panel that believes its
 * own copy shows a folder that was emptied an hour ago.
 *
 * The width that decides the layout is this component's own, not the window's: the same manager
 * is a page, half of a picker dialog, and a phone.
 */
const props = withDefaults(
  defineProps<{
    /** A picker hands one file back instead of managing the library. */
    picking?: boolean
    /** Only these kinds can be chosen, when picking. */
    accept?: MediaKind | null
    /**
     * A picker that takes several files at once.
     *
     * What this switches on is the selection the manager already has for its batch operations:
     * the rubber band, the tap that toggles, the run held with shift. A picker with a way of
     * marking files of its own would be a second thing to learn for the very same job.
     */
    multiple?: boolean
    /** The most that may be held at once, when picking several. */
    max?: number | null
  }>(),
  { picking: false, accept: null, multiple: false, max: null },
)

const emit = defineEmits<{ pick: [file: MediaFile]; selection: [files: MediaFile[]] }>()

const admin = useAdmin()
const api = createMediaApi(admin)
useMediaMessages()

const t = useTranslate('webx-media')

/**
 * The image editor's words, in the language the panel is being read in.
 *
 * Read when the editor is opened rather than kept in a computed: the dialog is mounted outside
 * the app, so it is handed plain strings once and nothing re-renders it afterwards.
 */
function editorLabels(): Record<string, string> {
  return {
    title: t('editor.title'),
    saveLabel: t('editor.save'),
    cancelLabel: t('editor.cancel'),
    resetLabel: t('editor.reset'),
    rotateLeftLabel: t('editor.rotate-left'),
    rotateRightLabel: t('editor.rotate-right'),
    flipHorizontalLabel: t('editor.flip-horizontal'),
    flipVerticalLabel: t('editor.flip-vertical'),
    ratioLabel: t('editor.ratio'),
    cropLabel: t('editor.crop'),
    outputLabel: t('editor.output'),
    outputHint: t('editor.output-hint'),
    widthLabel: t('editor.width'),
    heightLabel: t('editor.height'),
    freeLabel: t('editor.free'),
    originalLabel: t('editor.original'),
    errorText: t('editor.error'),
  }
}

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)
const compact = computed(() => width.value > 0 && width.value < 640)

const directories = ref<MediaDirectory[]>([])
const current = ref<number | null>(null)
const page = ref<MediaPage | null>(null)
const selected = ref<number[]>([])
const search = ref('')
/*
 * What the caller asked for is not a default the person can put back: a field that wants a
 * picture would otherwise be handed a PDF, and find out when the page renders.
 */
const type = ref<MediaKind | 'all'>(props.accept ?? 'all')

watch(
  () => props.accept,
  (kind) => {
    type.value = kind ?? 'all'
  },
)
const sort = ref('-created_at')
const busy = ref(false)
const foldersOpen = ref(false)
const picker = useTemplateRef<HTMLInputElement>('picker')

const rows = computed(() => page.value?.data ?? [])
const canManage = computed(() => admin.can('media.manage'))
const canUpload = computed(() => admin.can('media.upload') || canManage.value)
const folder = computed(() => find(current.value))
/** Picking one file is the one case the band is off for; the manager itself always has it. */
const picksOne = computed(() => props.picking && !props.multiple)

/**
 * The limit belongs to the field, so the dialog keeps to it.
 *
 * Trimmed rather than refused: what the person sees is that the grid stops taking more, and the
 * count under it says how far they have got. Finding out on save that a field takes ten is not
 * a limit, it is a rejected form.
 */
watch(selected, (ids) => {
  if (props.max !== null && props.max > 0 && ids.length > props.max) {
    selected.value = ids.slice(0, props.max)

    return
  }

  // In the order they are on screen, not the order they were clicked in: what comes back is a
  // gallery, and its order is the one the person was looking at.
  emit(
    'selection',
    rows.value.filter((file) => ids.includes(file.id)),
  )
})

onMounted(load)

watch([current, type, sort], () => loadFiles(1))
watch(
  search,
  debounce(() => loadFiles(1), 300),
)
watch(compact, (narrow) => {
  if (!narrow) {
    foldersOpen.value = false
  }
})

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
      type: type.value === 'all' ? null : type.value,
      sort: sort.value,
      page: to,
    })
    selected.value = []
  } finally {
    busy.value = false
  }
}

function choose(): void {
  picker.value?.click()
}

/**
 * Uploading says what happened in a toast and nowhere else.
 *
 * A list of what was just added under the toolbar is a second place to look and a thing to
 * dismiss; the files themselves appear in the grid a moment later, which is the answer.
 */
async function upload(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const chosen = Array.from(input.files ?? [])

  // Cleared straight away, so choosing the same file twice in a row still counts as a change.
  input.value = ''

  if (chosen.length === 0 || current.value === null) {
    return
  }

  try {
    const stored = await api.upload(current.value, chosen)
    const duplicates = stored.filter((file) => file.duplicate).length

    if (stored.length > duplicates) {
      toast.success(t('manager.uploaded', { count: stored.length - duplicates }))
    }

    if (duplicates > 0) {
      toast.info(t('manager.duplicate-added'))
    }

    await Promise.all([load(), loadFiles(1)])
  } catch (error) {
    toast.danger(messageOf(error) ?? t('errors.upload'))
  }
}

const askName = createModal<
  string,
  { title: string; label?: string; value?: string; confirmText?: string }
>(NameDialog)

async function createFolder(): Promise<void> {
  const title = await askName({
    title: t('manager.new-folder'),
    confirmText: t('manager.new-folder'),
  })

  if (title === undefined || current.value === null) {
    return
  }

  await api.createDirectory(current.value, title)
  await load()
}

async function renameFolder(): Promise<void> {
  const target = folder.value

  if (!target || target.is_root) {
    return
  }

  const title = await askName({
    title: t('manager.rename'),
    value: target.title,
    confirmText: t('manager.save'),
  })

  if (title !== undefined) {
    await api.renameDirectory(target.id, title)
    await load()
  }
}

async function deleteFolder(): Promise<void> {
  const target = folder.value

  if (!target || target.is_root) {
    return
  }

  // Asked twice on purpose, and the second question carries the counts the server sent back:
  // what goes with the folder is somebody's article illustrations.
  try {
    await api.deleteDirectory(target.id)
  } catch (error) {
    const counts = countsOf(error)

    if (!counts) {
      throw error
    }

    const agreed = await confirm({
      title: t('dialogs.delete-folder-title', { title: target.title }),
      message: `${t('dialogs.delete-folder-contents', counts)} ${t('dialogs.delete-folder-warning')}`,
      confirmText: t('dialogs.confirm'),
      cancelText: t('manager.cancel'),
      tone: 'danger',
    })

    if (!agreed) {
      return
    }

    await api.deleteDirectory(target.id, true)
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

const askWhereTo = createModal<
  number,
  { directories: MediaDirectory[]; from: number | null; count: number }
>(MoveDialog)

async function moveSelected(): Promise<void> {
  const to = await askWhereTo({
    directories: directories.value,
    from: current.value,
    count: selected.value.length,
  })

  if (to === undefined) {
    return
  }

  await api.move([...selected.value], to)
  await Promise.all([load(), loadFiles()])
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
  // `source`, not `url`: the editor draws the picture onto a canvas and writes that canvas out,
  // and a browser refuses to do that for bytes from another origin without CORS headers — which
  // is exactly what a CDN in front of the library is. `url` stays what everything that only
  // looks at the picture uses.
  const result = await openImageEditor({
    src: file.source ?? file.url,
    fileName: file.file_name,
    filters: false,
    // The editor is a component of the design system: its words are props with English
    // defaults, not lines in the panel's dictionary. Whoever opens it is the one who knows
    // what language the panel is being read in.
    ...editorLabels(),
  })

  if (!result) {
    return
  }

  await api.edit(file.id, {
    // The editor's frame is read against the picture as it is on screen — already turned — and
    // the server applies the operations in that order for the same reason.
    crop: result.crop,
    rotate: (((result.rotation % 360) + 360) % 360) as 0 | 90 | 180 | 270,
    flip: result.flipX ? 'horizontal' : result.flipY ? 'vertical' : undefined,
    resize: { width: result.width, height: result.height },
  })

  // Both addresses — the picture and its preview — carry the new version, so what is on screen
  // a moment later is what was just saved rather than what the browser kept.
  await loadFiles()
}

/**
 * A double-click means "this one, now".
 *
 * While several are being picked it means nothing extra: the first click of it has already put
 * the file in the selection, and the dialog ends on its own button instead.
 */
function open(file: MediaFile): void {
  if (!props.multiple) {
    emit('pick', file)
  }
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
  <div ref="root" class="wx-media" :class="{ 'wx-media--compact': compact }">
    <aside v-if="!compact" class="wx-media__folders">
      <directory-tree v-model:selected="current" :directories="directories" @move="moveFolder" />

      <wx-actions v-if="canManage" size="sm" align="start">
        <wx-action type="add" :title="t('manager.new-folder')" @click="createFolder" />
        <wx-action
          type="edit"
          :title="t('manager.rename')"
          :disabled="!folder || folder.is_root"
          @click="renameFolder"
        />
        <wx-action
          type="remove"
          :title="t('manager.delete')"
          :disabled="!folder || folder.is_root"
          @click="deleteFolder"
        />
      </wx-actions>
    </aside>

    <section class="wx-media__files">
      <!--
        Uploading is offered while picking too. Most of the time the picture somebody is looking
        for is the one on their desk, and a picker that can only choose from what is already
        there sends them off to the library, to the upload, and back to a form they then have to
        find again.
      -->
      <media-toolbar
        v-model:search="search"
        v-model:type="type"
        v-model:sort="sort"
        :can-upload="canUpload"
        :fixed-type="accept !== null"
        :can-manage="canManage"
        :selected="selected.length"
        :compact="compact"
        @upload="choose"
        @move="moveSelected"
        @remove="removeSelected"
        @folders="foldersOpen = true"
      />

      <!--
        `accept` here is a hint to the file dialog, not a rule: what may be uploaded at all is
        the server's list of extensions, and it says so in a language the person reads.
      -->
      <input
        ref="picker"
        type="file"
        multiple
        hidden
        :accept="accept === null ? undefined : `${accept}/*`"
        @change="upload"
      />

      <file-grid
        v-model:selected="selected"
        :files="rows"
        :api="api"
        :query="search"
        :single="picksOne"
        @rename="rename"
        @edit="edit"
        @remove="remove"
        @open="open"
      />

      <footer class="wx-media__status">
        <span>{{ t('manager.status-files', { count: page?.stats.files ?? 0 }) }}</span>
        <span v-if="selected.length > 0">
          {{ t('manager.status-selected', { count: selected.length }) }}
        </span>
        <span>{{ t('manager.status-size', { size: readable(page?.stats.size ?? 0) }) }}</span>
      </footer>

      <wx-pagination
        v-if="page && page.meta.last_page > 1"
        :page="page.meta.current_page"
        :per-page="page.meta.per_page"
        :total="page.meta.total"
        :last-page="page.meta.last_page"
        :disabled="busy"
        size="sm"
        @change="({ page: to }) => loadFiles(to)"
      />
    </section>

    <!-- On a narrow screen the folders are a drawer: a tree beside a grid leaves room for
         neither, and an editor on a phone is looking for one thing at a time. -->
    <wx-drawer v-model:open="foldersOpen" side="left" :title="t('manager.folders')" :size="280">
      <div class="wx-media__drawer">
        <directory-tree
          v-model:selected="current"
          :directories="directories"
          @move="moveFolder"
          @update:selected="foldersOpen = false"
        />

        <wx-actions v-if="canManage" size="sm" align="start">
          <wx-action type="add" :title="t('manager.new-folder')" @click="createFolder" />
          <wx-action
            type="edit"
            :title="t('manager.rename')"
            :disabled="!folder || folder.is_root"
            @click="renameFolder"
          />
          <wx-action
            type="remove"
            :title="t('manager.delete')"
            :disabled="!folder || folder.is_root"
            @click="deleteFolder"
          />
        </wx-actions>
      </div>
    </wx-drawer>
  </div>
</template>

<style>
.wx-media {
  display: grid;
  grid-template-columns: minmax(180px, 240px) minmax(0, 1fr);
  gap: var(--wx-space-16);
  min-height: 0;
  height: 100%;
}

.wx-media--compact {
  grid-template-columns: minmax(0, 1fr);
  gap: var(--wx-space-8);
}

.wx-media__folders {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-height: 0;
  border-right: 1px solid var(--wx-border-muted);
  padding-right: var(--wx-space-12);
}

/* The drawer hands its content the edge of the screen; the tree and its actions want air. */
.wx-media__drawer {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-10);
  padding-block: var(--wx-space-4);
}

.wx-media__status {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
  border-top: 1px solid var(--wx-border-muted);
  padding-top: var(--wx-space-6);
}

.wx-media__files {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-10);
  min-height: 0;
}
</style>
