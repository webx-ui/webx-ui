<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  rowMenuWidth,
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  WxListScreen,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  toast,
  WxBadge,
  WxTable,
  WxText,
  type TabItem,
  type TableColumn,
  type TableNodeDropEvent,
  type TableState,
  type TableTreeOptions,
  type TabValue,
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
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const home = ref<PageRow | null>(null)
const items = ref<PageRow[]>([])
const loading = ref(true)
const search = ref('')
/* A tab's value is a string; what the server is asked for is read back out of it. */
const filter = ref<TabValue>('')

const create = createModal<PageRow, { parent: PageRow | null }>(PageCreateDialog)
const pickTarget = createModal<number, { page: PageRow }>(PageMoveDialog)

const canManage = computed(() => context.can('pages.manage'))
const inBin = computed(() => filter.value === 'trashed')
/**
 * A search and the bin are flat lists; everything else is a level of the tree, at every width.
 *
 * A narrow screen used to get a flat list instead, on the grounds that cards have no
 * indentation to read and no chevron to open. The cards were the mistake, not the tree (§11):
 * the table stays a table and drops columns until only the title and the `···` are left, so the
 * tree is walked on a phone the same way it is walked on a desk.
 */
const asTree = computed(() => !inBin.value && search.value.trim() === '')

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'pages')?.title ??
    t('module.title'),
)

/*
 * The views of the same list, as tabs over the card (§10). The bin rides in the same row with
 * an icon rather than standing apart: it is still this list, and a reader who wants what they
 * deleted looks for it beside what they did not.
 */
const views = computed<TabItem[]>(() => [
  { value: '', label: t('page.filter-all') },
  { value: 'draft', label: t('page.filter-draft') },
  { value: 'published', label: t('page.filter-published') },
  { value: 'modified', label: t('page.filter-modified') },
  { value: 'trashed', label: t('page.bin'), icon: 'trash' },
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
 * Every column but the title is given a width, and the table is laid out `fixed`, so the
 * widths that are hidden below a breakpoint give what they had to the title rather than to
 * whichever column happened to hold the longest string.
 *
 * They go in the order they can be spared — when it was last touched, then what state it is in,
 * then where it lives — and below the narrowest of them the row is the title and the `···`,
 * which is the whole of §11: two columns, still a tree.
 */
const columns = computed<TableColumn<PageRow>[]>(() => [
  { key: 'title', label: t('page.column-title'), minWidth: 200 },
  { key: 'path', label: t('page.column-address'), width: 200, hideBelow: 560 },
  // In the bin the state of a page is not what it was published as — it is off the site either
  // way — so the column says when it went in, which is what decides whether to bring it back.
  {
    key: 'status',
    label: inBin.value ? t('page.column-deleted') : t('page.column-status'),
    align: 'center',
    width: 150,
    hideBelow: 620,
  },
  {
    key: 'updated_at',
    label: t('page.column-updated'),
    width: 150,
    hideBelow: 900,
    hidden: inBin.value,
  },
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
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
      status: inBin.value ? '' : (filter.value as PageStatus | ''),
      trashed: inBin.value,
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
 *
 * The bin never reaches here: a page in it has no editor to open — the API answers a 404 for
 * one — so the table is told the rows lead nowhere (`:clickable="!inBin"`), and it withholds
 * the click along with the pointer and the highlight (§13). Everything a deleted page can have
 * done to it is in its `···`.
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

/**
 * A drop asks only when it rewrites more than one address (§14.3).
 *
 * The other way out — no question, and an "Undo" in the toast — reads better but is not one:
 * a move leaves a redirect on every address it vacated, so putting the page back would be a
 * second move and a pile of redirects, not an undo. Until the server can actually undo one,
 * dragging a single page stays a gesture and dragging a branch stays a decision.
 *
 * The number is known before the request: a move changes the address of the page and of
 * everything under it, which is what `descendants_count` counts. Landing in the same parent
 * changes nothing at all, so a reorder is never questioned.
 */
async function dropped(event: TableNodeDropEvent<PageRow>): Promise<void> {
  const parent = event.zone === 'inside' ? event.target.id : event.target.parent_id
  const moves = event.row.descendants_count + 1

  if (parent !== event.row.parent_id && moves > 1) {
    const agreed = await confirm({
      title: t('page.move-title', { title: event.row.title }),
      message: t('page.move-branch', { count: moves }),
      confirmText: t('page.move-confirm'),
      cancelText: t('page.cancel'),
    })

    if (!agreed) {
      // The table is showing the row where it was dropped; the server never heard about it.
      await load()

      return
    }
  }

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

/**
 * Coming back out of the bin is asked about too (§14.2): a branch restored by accident is a
 * section of the site back on it, and the count says how much of one.
 */
async function restore(page: PageRow): Promise<void> {
  const agreed = await confirm({
    title: t('page.restore-page-title', { title: page.title }),
    message:
      page.descendants_count > 0
        ? `${t('page.restore-page-text')} ${t('page.restore-branch', { count: page.descendants_count })}`
        : t('page.restore-page-text'),
    confirmText: t('page.restore'),
    cancelText: t('page.cancel'),
  })

  if (!agreed) return

  try {
    // The server knows what it actually brought back; the question could only guess at it.
    const restored = await api.restore(page.id)

    toast.success(
      restored > 1 ? t('page.restored-branch', { count: restored }) : t('page.restored'),
    )
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

watch(filter, () => void load())

onMounted(load)

/* What the section offers. Declared, because on a phone the head folds it into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [{ key: 'new', label: t('page.new'), icon: 'plus', primary: true, run: () => void add(null) }]
    : [],
)
</script>

<template>
  <div class="wx-pages">
    <wx-list-screen v-model:view="filter" :title="title" :views="views" :actions="actions">
      <wx-table
        :data="rows"
        :columns="columns"
        row-key="id"
        :tree="tree"
        searchable
        :clickable="!inBin"
        :hover="!inBin"
        flush
        layout="fixed"
        :loading="loading"
        :cards-below="0"
        :search-placeholder="t('page.search')"
        :empty-text="
          search ? t('page.empty-search') : inBin ? t('page.empty-bin') : t('page.empty')
        "
        :aria-label="title"
        @row-click="open"
        @state-change="onState"
        @node-drop="dropped"
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
          <wx-date v-if="inBin" :value="row.deleted_at" />
          <wx-badge v-else :type="badge(row.status)" dot>
            {{ t(`page.status-${row.status}`) }}
          </wx-badge>
        </template>

        <template #cell-updated_at="{ row }">
          <wx-text size="sm" tone="muted">
            <wx-date :value="row.updated_at" compact /><template v-if="row.edited_by && !inBin"
              >, {{ row.edited_by }}</template
            >
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
      </wx-table>
    </wx-list-screen>
  </div>
</template>

<style scoped>
/* The list screen inside it lays the screen out; this only keeps a wide table from pushing the
   column it sits in. */
.wx-pages {
  min-width: 0;
}

.wx-pages__title {
  font-weight: var(--wx-font-weight-medium);
}

.wx-pages__address {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  color: var(--wx-color-primary);
  text-decoration: none;
}

.wx-pages__address:hover {
  text-decoration: underline;
}
</style>
