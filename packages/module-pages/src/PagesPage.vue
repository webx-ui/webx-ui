<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  toast,
  useElementWidth,
  WxBadge,
  WxButton,
  WxCard,
  WxHeading,
  WxSegmented,
  WxTable,
  WxText,
  type TableColumn,
  type TableNodeDropEvent,
  type TableState,
  type TableTreeOptions,
  type TreeDropZone,
} from '@webx-ui/core'
import PageActions from './PageActions.vue'
import PageCreateDialog from './PageCreateDialog.vue'
import PageMoveDialog from './PageMoveDialog.vue'
import { createPagesApi } from './api'
import { usePagesMessages } from './i18n'
import type { PageRow, PageStatus } from './types'

/**
 * The section: the site's pages as a tree, a level at a time.
 *
 * The home page is pinned at the top and its children are the top level, because every page of
 * the site is inside it — drawn as a branch, every row of the table would carry one step of
 * indentation that says nothing (§9).
 *
 * Searching puts the tree away. A branch drawn for the sake of one match deep inside it tells
 * the reader nothing they asked about, so a search is a flat list of matches with the address
 * under each.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/pages' })

const context = useAdmin()
const api = createPagesApi(context)
const router = useRouter()
usePagesMessages()

const t = useTranslate('webx-pages')

const home = ref<PageRow | null>(null)
const items = ref<PageRow[]>([])
const loading = ref(true)
const search = ref('')
const filter = ref<PageStatus | '' | 'trashed'>('')

/*
 * Below the width where the table becomes cards, the tree becomes a flat list.
 *
 * A card has no indentation to read and no chevron to open, so a tree drawn as cards is a tree
 * nobody can walk — the pages below the first level simply cannot be reached (§9, last
 * paragraph). Flat, with the address under the name, every page is one tap away and the address
 * says where it sits. The width of the section decides it, not the width of the window.
 */
const CARDS_BELOW = 640

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)
const narrow = computed(() => width.value > 0 && width.value < CARDS_BELOW)

const create = createModal<PageRow, { parent: PageRow | null }>(PageCreateDialog)
const pickTarget = createModal<number, { page: PageRow }>(PageMoveDialog)

const canManage = computed(() => context.can('pages.manage'))
const inBin = computed(() => filter.value === 'trashed')
/** A search, the bin and a phone are flat lists; only a level of the tree is a tree. */
const asTree = computed(() => !inBin.value && !narrow.value && search.value.trim() === '')

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'pages')?.title ??
    t('module.title'),
)

const filters = computed(() => [
  { value: '', label: t('page.filter-all') },
  { value: 'draft', label: t('page.filter-draft') },
  { value: 'published', label: t('page.filter-published') },
  { value: 'modified', label: t('page.filter-modified') },
  { value: 'trashed', label: t('page.bin'), icon: 'trash' as const },
])

/**
 * The home page rides in the rows rather than beside them, with no children of its own: what
 * would hang under it is already the level below, and a chevron that opened a copy of the list
 * is the one thing worse than no chevron.
 */
const rows = computed<PageRow[]>(() =>
  asTree.value && home.value ? [{ ...home.value, children_count: 0 }, ...items.value] : items.value,
)

/**
 * Every column but the title is given a width, and the table is laid out `fixed`: the row of
 * actions is seven icons wide and only folds into a menu when its cell refuses to grow for it.
 * Left to size itself, the cell takes the width of all seven, and the table scrolls sideways
 * instead — which on a phone is the whole table.
 */
const columns = computed<TableColumn<PageRow>[]>(() => [
  { key: 'title', label: t('page.column-title'), minWidth: 200 },
  { key: 'path', label: t('page.column-address'), width: 200 },
  // In the bin the state of a page is not what it was published as — it is off the site either
  // way — so the column says when it went in, which is what decides whether to bring it back.
  {
    key: 'status',
    label: inBin.value ? t('page.column-deleted') : t('page.column-status'),
    align: 'center',
    width: 130,
    hideBelow: 620,
  },
  {
    key: 'updated_at',
    label: t('page.column-updated'),
    width: 180,
    hideBelow: 900,
    hideOnCards: true,
    hidden: inBin.value,
  },
  { key: 'actions', label: '', width: 48, align: 'right', hideOnCards: true },
])

const tree = computed<TableTreeOptions<PageRow> | undefined>(() =>
  asTree.value
    ? {
        childrenKey: 'children',
        hasChildrenKey: 'children_count',
        lazy: true,
        load: (row) => api.list({ parent: row.id }).then((level) => level.items),
        draggable: canManage.value,
        allowDrag: (row) => row.can.move,
        allowDrop,
      }
    : undefined,
)

/**
 * Nothing goes beside the home page: that place is a second root, and the tree has one. Inside
 * it is the ordinary case — everything on the site is.
 */
function allowDrop(drag: PageRow, drop: PageRow, zone: TreeDropZone): boolean {
  if (drop.is_home) return zone === 'inside'

  return drag.id !== drop.id
}

async function load(): Promise<void> {
  loading.value = true

  try {
    const level = await api.list({
      search: search.value.trim(),
      status: inBin.value ? '' : filter.value,
      trashed: inBin.value,
      flat: narrow.value,
    })

    home.value = level.home
    items.value = level.items
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

function onState(state: TableState): void {
  if (state.search === search.value) return

  search.value = state.search
  void load()
}

/**
 * The editor of a page arrives with the form; until it does, the row opens nothing rather than
 * pushing the router at a route that is not there.
 */
function open(page: PageRow): void {
  const path = `${props.base}/${page.id}`

  if (router.resolve(path).matched.length > 0) void router.push(path)
}

async function add(parent: PageRow | null): Promise<void> {
  const page = await create({ parent })

  if (!page) return

  await load()
  open(page)
}

async function duplicate(page: PageRow): Promise<void> {
  try {
    await api.duplicate(page.id)
    toast.success(t('page.duplicated'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function move(page: PageRow): Promise<void> {
  const target = await pickTarget({ page })

  if (target == null) return

  await apply(page, target, 'inside')
}

async function dropped(event: TableNodeDropEvent<PageRow>): Promise<void> {
  await apply(event.row, event.target.id, event.zone)
}

/** One move, one sentence about the addresses it rewrote, and the list as the server sees it. */
async function apply(page: PageRow, target: number, zone: TreeDropZone): Promise<void> {
  try {
    const result = await api.move(page.id, target, zone)

    toast.success(
      result.addresses_changed > 1
        ? t('page.moved', { count: result.addresses_changed })
        : t('page.moved-one'),
    )
  } catch (error) {
    toast.danger(message(error))
  } finally {
    // Either way: a refused move has left the table showing the row where it was dropped, and
    // a move that worked has changed addresses the answer does not carry row by row.
    await load()
  }
}

async function remove(page: PageRow): Promise<void> {
  const agreed = await confirm({
    title: t('page.delete-title', { title: page.title }),
    message:
      // The whole branch, not the level under it: a catalogue with two sections and forty
      // products is forty-two pages leaving the site, and "2" would be a comforting lie.
      page.descendants_count > 0
        ? `${t('page.delete-text')} ${t('page.delete-branch', { count: page.descendants_count })}`
        : t('page.delete-text'),
    confirmText: t('page.delete'),
    cancelText: t('page.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(page.id)
    toast.success(t('page.deleted'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function restore(page: PageRow): Promise<void> {
  try {
    await api.restore(page.id)
    toast.success(t('page.restored'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function copyAddress(page: PageRow): Promise<void> {
  if (!page.url) return

  try {
    await navigator.clipboard.writeText(page.url)
    toast.success(t('page.address-copied'))
  } catch {
    // A browser that refuses the clipboard without a gesture it trusts; the address is on
    // screen and can be copied from there.
  }
}

function badge(status: PageStatus): 'default' | 'success' | 'warning' {
  if (status === 'published') return 'success'

  return status === 'modified' ? 'warning' : 'default'
}

function when(page: PageRow): string {
  const at = inBin.value ? page.deleted_at : page.updated_at

  return at ? new Date(at).toLocaleDateString() : ''
}

function message(error: unknown): string {
  return (error as { body?: { message?: string } }).body?.message ?? String(error)
}

function changeFilter(): void {
  void load()
}

// The answer for a phone is a different answer, not a different stylesheet: crossing the width
// re-asks the server for a flat list or for a level of the tree.
watch(narrow, () => void load())

onMounted(load)
</script>

<template>
  <div ref="root" class="wx-pages">
    <div class="wx-pages__head">
      <wx-heading :level="2">{{ title }}</wx-heading>
      <wx-button v-if="canManage" type="primary" icon="plus" @click="add(null)">
        {{ t('page.new') }}
      </wx-button>
    </div>

    <wx-card>
      <template #header>
        <wx-segmented
          v-model="filter"
          :options="filters"
          size="sm"
          :aria-label="t('page.column-status')"
          @change="changeFilter"
        />
      </template>

      <wx-table
        :data="rows"
        :columns="columns"
        row-key="id"
        :tree="tree"
        searchable
        hover
        flush
        layout="fixed"
        :loading="loading"
        :cards-below="CARDS_BELOW"
        :search-placeholder="t('page.search')"
        :empty-text="
          inBin ? t('page.empty-bin') : search ? t('page.empty-search') : t('page.empty')
        "
        :aria-label="title"
        @state-change="onState"
        @node-drop="dropped"
        @row-click="open"
      >
        <template #cell-title="{ row }">
          <span class="wx-pages__title">
            {{ row.is_home ? t('pages.home') : row.title }}
          </span>
        </template>

        <template #cell-path="{ row }">
          <!-- A page in the bin holds no address: the registry let it go when it went in, and
               a live address beside a page nobody can reach would be the wrong promise. -->
          <wx-text v-if="inBin" size="sm" tone="muted">—</wx-text>
          <!-- The address of a live page is a link to it; a draft's is the address it will
               have, which is worth showing and not worth clicking. -->
          <a
            v-else-if="row.url && row.status !== 'draft'"
            class="wx-pages__address"
            :href="row.url"
            target="_blank"
            rel="noopener"
            @click.stop
          >
            /{{ row.path }}
          </a>
          <wx-text v-else-if="row.path !== null" mono size="sm" tone="muted"
            >/{{ row.path }}</wx-text
          >
          <wx-text v-else size="sm" tone="muted">{{ t('page.no-address') }}</wx-text>
        </template>

        <template #cell-status="{ row }">
          <wx-text v-if="inBin" size="sm" tone="muted">{{ when(row) }}</wx-text>
          <wx-badge v-else :type="badge(row.status)" dot>
            {{ t(`page.status-${row.status}`) }}
          </wx-badge>
        </template>

        <template #cell-updated_at="{ row }">
          <wx-text size="sm" tone="muted">
            {{ when(row) }}<template v-if="row.edited_by && !inBin">, {{ row.edited_by }}</template>
          </wx-text>
        </template>

        <template #cell-actions="{ row }">
          <page-actions
            v-if="canManage"
            :page="row"
            :in-bin="inBin"
            @open="open"
            @add="add"
            @duplicate="duplicate"
            @move="move"
            @copy="copyAddress"
            @remove="remove"
            @restore="restore"
          />
        </template>

        <!-- A card has the same actions, and the same reason to fold them into a menu: at that
             width the card itself is the whole screen. -->
        <template #card-actions="{ row }">
          <page-actions
            v-if="canManage"
            menu-only
            :page="row"
            :in-bin="inBin"
            @open="open"
            @add="add"
            @duplicate="duplicate"
            @move="move"
            @copy="copyAddress"
            @remove="remove"
            @restore="restore"
          />
        </template>
      </wx-table>
    </wx-card>
  </div>
</template>

<style scoped>
.wx-pages {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  container-type: inline-size;
}

.wx-pages__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
  flex-wrap: wrap;
}

.wx-pages__title {
  font-weight: var(--wx-font-weight-medium);
}

.wx-pages__address {
  font-family: var(--wx-font-mono);
  font-size: var(--wx-font-size-sm);
  color: var(--wx-color-primary);
  text-decoration: none;
}

.wx-pages__address:hover {
  text-decoration: underline;
}
</style>
