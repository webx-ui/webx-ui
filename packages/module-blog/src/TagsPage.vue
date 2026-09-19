<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxListScreen,
  rowMenuWidth,
  WxRowMenu,
  type RowAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  toast,
  WxActionBar,
  WxButton,
  WxIcon,
  WxTable,
  WxText,
  WxTooltip,
  type RowKey,
  type TabItem,
  type TableColumn,
  type TableSort,
  type TableState,
  type TabValue,
} from '@webx-ui/core'
import TagCreateDialog from './TagCreateDialog.vue'
import TagRenameDialog from './TagRenameDialog.vue'
import TagMergeDialog from './TagMergeDialog.vue'
import { createBlogApi } from './api'
import { useBlogMessages } from './i18n'
import type { TagIndexing, TagMerged, TagQuery, TagRow, TagsPage } from './types'

/**
 * Tags, and the raking over of them (§10).
 *
 * Entered from the article form by the hundred, which is what the whole screen is shaped by:
 * within six months the table holds "belts", "belt" and "drive belts", and all three are right.
 * So renaming is done in place — a tag is one word, and a form to change one word is a form
 * nobody opens — and everything else is done to a pile of rows at once.
 *
 * It stays a table at every width, with columns dropping out by `hideBelow` until the word and
 * the `···` are left. Cards would be 250px each for a word and a number (CLAUDE.md §4), and
 * this is a screen somebody scrolls through sixty rows of.
 *
 * "Not indexed" is the one filter that is not a `where`: it is the answer of the same rule the
 * rendered page follows, rules in the SEO section included, so the column, the tab's count and
 * the filter are one answer worked out on the server (§12).
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/blog' })

const context = useAdmin()
const api = createBlogApi(context)
const route = useRoute()
const router = useRouter()
useBlogMessages()

const t = useTranslate('webx-blog')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const page = ref<TagsPage | null>(null)
const loading = ref(true)
const selected = ref<RowKey[]>([])
const working = ref(false)

const rename = createModal<string, { name: string }>(TagRenameDialog)

const create = createModal<TagRow, Record<string, never>>(TagCreateDialog)
const merge = createModal<TagMerged, { tags: TagRow[] }>(TagMergeDialog)

const canManage = computed(() => context.can('blog.taxonomy.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'tags')?.title ??
    t('module.tags'),
)

/** Everything the list is looking at lives in the address, so a link to it is a link. */
const view = computed<TabValue>({
  get: () => (typeof route.query.view === 'string' ? route.query.view : ''),
  set: (value) => {
    void router.replace({
      query: { ...route.query, view: value === '' ? undefined : String(value), page: undefined },
    })
  },
})

const counts = computed(() => page.value?.filters ?? { total: 0, empty: 0, noindex: 0 })

const views = computed<TabItem[]>(() => [
  { value: '', label: t('tag.view-all'), badge: counts.value.total },
  { value: 'empty', label: t('tag.view-empty'), badge: counts.value.empty },
  { value: 'noindex', label: t('tag.view-noindex'), badge: counts.value.noindex },
])

const rows = computed(() => page.value?.data ?? [])

const chosen = computed(() => rows.value.filter((tag) => selected.value.includes(tag.id as RowKey)))

/**
 * Five columns, and never cards.
 *
 * The word and the menu stay at every width; the address goes first, because it is the one
 * thing a tag's name already tells you, then the state of the index. What is left on a phone is
 * a word, a number and a `···`, which is the screen this is (CLAUDE.md §4).
 */
const columns = computed<TableColumn<TagRow>[]>(() => [
  { key: 'title', label: t('tag.column-title'), minWidth: 150, sortable: true },
  // The number is what the other columns and the narrowest the title may be add up to without
  // this one: below it the address is what does not fit, and a fixed table does not shrink —
  // it scrolls sideways and takes the ··· with it (CLAUDE.md §4). Measured, not guessed: at
  // 375px the row is the word, the number and the menu, and nothing hangs off the edge.
  { key: 'path', label: t('tag.column-address'), width: 180, hideBelow: 760 },
  {
    key: 'articles_count',
    label: t('tag.column-articles'),
    width: 80,
    align: 'right',
    sortable: true,
  },
  // Wide enough for the longest of the three states on one line, in every language: measured
  // at 180px of text, and a cell keeps 32 of its own. Narrower, the ruled row wrapped and
  // stood eighteen pixels taller than the ones around it.
  { key: 'indexing', label: t('tag.column-indexing'), width: 230, hideBelow: 560 },
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
])

function query(state?: TableState): TagQuery {
  return {
    q: state?.search ?? (typeof route.query.q === 'string' ? route.query.q : ''),
    empty: view.value === 'empty',
    noindex: view.value === 'noindex',
    // Only when it is not the default: most used first is what the list is, and a query
    // string that repeats the default is a link that outlives a change of mind about it.
    sort: sortOf(state),
    page: state?.page ?? (Number(route.query.page ?? 1) || 1),
    per_page: state?.perPage,
  }
}

async function load(state?: TableState): Promise<void> {
  loading.value = true

  try {
    page.value = await api.tagsPage(query(state))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

/**
 * The order the table asks for, in the words the server knows.
 *
 * Two keys and a direction: the word from A, or the used ones first — and a minus turns either
 * of them around. The default is most used first, so the query string leaves it out: a link that
 * repeats the default outlives a change of mind about it.
 */
function sortOf(state?: TableState): TagQuery['sort'] {
  const from = state?.sort ?? sortFromRoute()

  if (!from) return undefined

  const key = from.key === 'title' ? 'name' : 'articles'
  const sort = `${from.order === 'desc' ? '-' : ''}${key}` as NonNullable<TagQuery['sort']>

  return sort === 'articles' ? undefined : sort
}

/** What the address says the order is, for a list arrived at by a link. */
function sortFromRoute(): TableSort | null {
  const value = typeof route.query.sort === 'string' ? route.query.sort : ''

  if (value === '') return null

  const desc = value.startsWith('-')
  const key = (desc ? value.slice(1) : value) === 'name' ? 'title' : 'articles_count'

  return { key, order: desc ? 'desc' : 'asc' }
}

/** The table reports everything it knows in one event, and the address is written from it. */
function onState(state: TableState): void {
  const wanted: LocationQueryRaw = {
    ...route.query,
    q: state.search === '' ? undefined : state.search,
    sort: sortOf(state),
    page: state.page === 1 ? undefined : String(state.page),
  }

  void router.replace({ query: wanted })
  void load(state)
}

watch(view, () => {
  // A new view is a new list, and a selection made in the old one is a selection of rows
  // nobody can see any more.
  selected.value = []
  void load()
})

void load()

async function add(): Promise<void> {
  if (await create({})) await load()
}

/**
 * Renaming, as an act rather than a side effect.
 *
 * A dialog with one field, because a name that is quietly an `<input>` reads as a name: nothing
 * says it can be typed in, and a stray click on a row is a rename nobody asked for. The address
 * does not move with the word — a tag respelled three times before lunch would leave three
 * aliases behind a decision nobody made — so it has a menu item of its own.
 */
async function startRename(tag: TagRow): Promise<void> {
  const title = await rename({ name: tag.title })

  if (title) await patch(tag, { title }, t('tag.renamed'))
}

async function setIndexing(tag: TagRow, noindex: boolean): Promise<void> {
  await patch(tag, { noindex }, noindex ? t('tag.kept-out') : t('tag.indexed'))
}

/**
 * One tag changed, and the row it changed in.
 *
 * The answer replaces the row rather than reloading the page: the list is sixty words long and
 * somebody is halfway down it, and a reload after every rename would take their place away.
 */
async function patch(
  tag: TagRow,
  input: { title?: string; noindex?: boolean },
  said: string,
): Promise<void> {
  try {
    const saved = await api.saveTag(tag.id, input)

    if (page.value) {
      page.value = {
        ...page.value,
        data: page.value.data.map((row) => (row.id === saved.id ? saved : row)),
      }
    }

    toast.success(said)
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(tag: TagRow): Promise<void> {
  const agreed = await confirm({
    title: t('tag.delete-title', { title: tag.title }),
    message:
      tag.articles_count > 0
        ? t('tag.delete-text-used', { count: tag.articles_count })
        : t('tag.delete-text'),
    confirmText: t('tag.delete'),
    cancelText: t('tag.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeTag(tag.id)
    toast.success(t('tag.deleted'))
    selected.value = selected.value.filter((key) => key !== tag.id)
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

/**
 * What can be done to the pile, behind the same `···` a single row has.
 *
 * Three buttons in the bar were three lines of chrome on a phone and the same three words every
 * row already offers; merging stays a button because it is the one thing here that cannot be
 * done to a row on its own.
 */
const massActions = computed<RowAction[]>(() => [
  {
    key: 'index',
    icon: 'eye',
    label: t('tag.index'),
    disabled: working.value,
    run: () => void massSelected('index'),
  },
  {
    key: 'noindex',
    icon: 'eye-off',
    label: t('tag.noindex'),
    disabled: working.value,
    run: () => void massSelected('noindex'),
  },
  {
    key: 'delete',
    icon: 'trash',
    label: t('tag.delete'),
    danger: true,
    disabled: working.value,
    run: () => void massSelected('delete'),
  },
])

function actionsFor(tag: TagRow): RowAction[] {
  if (!canManage.value) return []

  const actions: RowAction[] = [
    { key: 'rename', icon: 'edit', label: t('tag.rename'), run: () => void startRename(tag) },
  ]

  if (tag.url) {
    actions.push({
      key: 'site',
      icon: 'external-link',
      label: t('tag.open-on-site'),
      href: tag.url,
    })
  }

  actions.push({
    key: 'articles',
    icon: 'list',
    label: t('tag.show-articles'),
    run: () => void router.push({ path: `${props.base}/articles`, query: { tag: String(tag.id) } }),
  })

  actions.push(
    tag.noindex
      ? {
          key: 'index',
          icon: 'eye',
          label: t('tag.index'),
          run: () => void setIndexing(tag, false),
        }
      : {
          key: 'noindex',
          icon: 'eye-off',
          label: t('tag.noindex'),
          run: () => void setIndexing(tag, true),
        },
  )

  actions.push({
    key: 'delete',
    icon: 'trash',
    label: t('tag.delete'),
    danger: true,
    run: () => void remove(tag),
  })

  return actions
}

/** The colour the state of the index is said in: green for in, quiet for out (§12). */
function tone(indexing: TagIndexing): 'success' | 'muted' {
  return indexing === 'noindex' ? 'muted' : 'success'
}

async function mergeSelected(): Promise<void> {
  if (chosen.value.length < 2) return

  const merged = await merge({ tags: chosen.value })

  if (!merged) return

  toast.success(t('tag.merged', { tag: merged.tag.title, count: merged.articles_count }))
  selected.value = []
  await load()
}

async function massSelected(action: 'index' | 'noindex' | 'delete'): Promise<void> {
  const ids = chosen.value.map((tag) => tag.id)

  if (ids.length === 0 || working.value) return

  if (action === 'delete') {
    const held = chosen.value.reduce((sum, tag) => sum + tag.articles_count, 0)

    const agreed = await confirm({
      title: t('tag.delete-many-title', { count: ids.length }),
      message:
        held > 0 ? t('tag.delete-many-text-used', { count: held }) : t('tag.delete-many-text'),
      confirmText: t('tag.delete'),
      cancelText: t('tag.cancel'),
      tone: 'danger',
    })

    if (!agreed) return
  }

  working.value = true

  try {
    const affected = await api.massTags(ids, action)

    toast.success(
      action === 'delete'
        ? t('tag.deleted-many', { count: affected })
        : action === 'index'
          ? t('tag.indexed-many', { count: affected })
          : t('tag.kept-out-many', { count: affected }),
    )
    selected.value = []
    await load()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}
</script>

<template>
  <div class="wx-tags">
    <wx-list-screen v-model:view="view" :title="title" :views="views">
      <template v-if="canManage" #actions>
        <wx-button type="primary" @click="add">
          <template #icon><wx-icon name="plus" /></template>
          {{ t('tag.new') }}
        </wx-button>
      </template>

      <wx-table
        v-model:selected="selected"
        :data="page"
        :columns="columns"
        row-key="id"
        searchable
        hover
        flush
        layout="fixed"
        :loading="loading"
        :selectable="canManage"
        :cards-below="0"
        :search-placeholder="t('tag.search')"
        :empty-text="rows.length === 0 && !loading ? t('tag.empty') : undefined"
        :aria-label="title"
        @state-change="onState"
      >
        <template #cell-title="{ row }">
          <wx-text truncate>{{ row.title }}</wx-text>
        </template>

        <template #cell-path="{ row }">
          <wx-text size="sm" tone="muted" mono truncate>
            {{ row.path ?? t('tag.no-address') }}
          </wx-text>
        </template>

        <template #cell-articles_count="{ row }">
          <wx-text :tone="row.articles_count === 0 ? 'muted' : 'default'">
            {{ row.articles_count }}
          </wx-text>
        </template>

        <!--
          Three states and not two. A rule in the SEO section opens the page whatever the flag
          says, and an editor who wrote that rule must not be left looking at a row that says
          `noindex` and disagreeing with it (§12).
        -->
        <template #cell-indexing="{ row }">
          <wx-tooltip v-if="row.indexing === 'rule'" :content="t('tag.indexing-rule-help')">
            <wx-text size="sm" :tone="tone(row.indexing)">{{ t('tag.indexing-rule') }}</wx-text>
          </wx-tooltip>
          <wx-text v-else size="sm" :tone="tone(row.indexing)">
            {{ t(`tag.indexing-${row.indexing}`) }}
          </wx-text>
        </template>

        <template #cell-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="row.title" />
        </template>
      </wx-table>
    </wx-list-screen>

    <!--
      The selection bar, at the bottom of the screen and only while something is selected.
      `WxActionBar` rather than a floating pill of its own: the panel already has one shape for
      "what can be done from here", and a second one would be a second one to keep in step.
    -->
    <wx-action-bar v-if="chosen.length > 0" class="wx-tags__bar" sticky>
      <template #state>
        <wx-text weight="medium">{{ t('tag.selected', { count: chosen.length }) }}</wx-text>
      </template>

      <!--
        One button and a menu. Merging is what this bar exists for — it is the only thing here
        that cannot be done to a row on its own — and the other three are the row's own menu
        applied to several rows at once, which is where a reader already looks for them.

        The icon is a slot and not a prop: `WxButton` takes `#icon`, and `icon="…"` lands on the
        element as an attribute and draws nothing at all (CLAUDE.md §4).
      -->
      <wx-button type="primary" :disabled="chosen.length < 2 || working" @click="mergeSelected">
        <template #icon><wx-icon name="link" /></template>
        {{ t('tag.merge') }}
      </wx-button>

      <!--
        No way out of the selection here. Untick the rows, or untick them all from the box in
        the heading — a × beside a red "Delete" is a button whose whole job is to undo something
        harmless, standing where the dangerous one is, and it read as a way to close the bar.

        `lg` because the menu stands beside a button rather than at the end of a row: a row menu
        is 30px, a button is 42, and the pair read as a control and a leftover. In a bar the two
        are the same two things and have to be the same size.
      -->
      <wx-row-menu
        size="lg"
        :actions="massActions"
        :label="t('tag.selected', { count: chosen.length })"
      />
    </wx-action-bar>
  </div>
</template>

<style scoped>
.wx-tags {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-tags__rename {
  display: block;
  min-width: 0;
}

.wx-tags__sort {
  display: flex;
  gap: var(--wx-space-4);
  min-width: 0;
}

/*
 * The name is the rename: a tag is one word, and a form to change one word is a form nobody
 * opens. It reads as text until it is hovered, because a table of sixty underlined words is a
 * table of sixty links to nowhere.
 */
.wx-tags__name {
  max-width: 100%;
  overflow: hidden;
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  text-align: start;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: text;
}

.wx-tags__name:hover {
  text-decoration: underline;
  text-decoration-style: dotted;
}

/*
 * One line on a phone, not two.
 *
 * The bar keeps its state box at 220px so that a sentence about a draft — "Saved · goes out on
 * 25 September" — has room to be read before the buttons take a line of their own. What stands
 * here is two words and a number, and asking for 220 of them pushed "Merge" and the ··· onto a
 * second line at every phone width. `:deep()` because the box is the bar's element and the rule
 * is ours. Measured at 375: the words 89px, the button and the ··· 203, and 41px of air left
 * between them.
 */
.wx-tags__bar :deep(.wx-action-bar__state) {
  flex: 0 1 auto;
}
</style>
