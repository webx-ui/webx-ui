<script setup lang="ts">
import { computed, ref, useTemplateRef, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  WxListScreen,
  WxFilterChips,
  WxRowMenu,
  type AppliedFilter,
  type RowAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  toast,
  useElementWidth,
  WxBadge,
  WxButton,
  WxEntityCard,
  WxFormItem,
  WxIcon,
  WxSelect,
  WxTable,
  WxText,
  WxTooltip,
  type TabItem,
  type TableColumn,
  type TableState,
  type TabValue,
} from '@webx-ui/core'
import ArticleCreateDialog from './ArticleCreateDialog.vue'
import { createBlogApi } from './api'
import { useBlogMessages } from './i18n'
import type { ArticleRow, ArticleStatus, ArticlesPage } from './types'

/**
 * The section: a page of articles with the filters of §10 over it.
 *
 * A paginator rather than a tree, which is the whole difference from `Pages`: articles all sit
 * at the same depth and there are a hundred of them, so the screen is twenty rows, a filter bar
 * and a page number.
 *
 * Below {@link CARDS} the row becomes a card, and the card is not the table's own stack of
 * labelled lines: at 375px five of those are four hundred pixels for one article. It is the
 * cover on the left and everything else beside it, which is 96px and ten articles on a screen
 * instead of two (§10). The table draws whatever columns it is given, so the switch is made
 * here — one column at that width, seven above it — and both halves agree on the number because
 * it is the same constant.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/blog' })

/** Where the row stops being a row. Below it the table draws cards and this screen draws one. */
const CARDS = 640

const context = useAdmin()
const api = createBlogApi(context)
const route = useRoute()
const router = useRouter()
useBlogMessages()

const t = useTranslate('webx-blog')
/* The two words a list needs as soon as it has filters belong to the panel, not to the blog. */
const admin = useTranslate('webx-admin')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)

const page = ref<ArticlesPage | null>(null)
const loading = ref(true)

const create = createModal<ArticleRow, Record<string, never>>(ArticleCreateDialog)

const canManage = computed(() => context.can('blog.articles.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'articles')?.title ??
    t('module.articles'),
)

/**
 * Everything the list is looking at lives in the address.
 *
 * Coming back from an article has to land on the same filter and the same page, or the section
 * costs the reader their place every time they open something.
 */
const view = computed<TabValue>({
  get: () => (typeof route.query.view === 'string' ? route.query.view : ''),
  set: (value) => {
    // A new view is a new list: the page it was on belongs to the view it was on.
    void router.replace({
      query: { ...route.query, view: value === '' ? undefined : String(value), page: undefined },
    })
  },
})

const inBin = computed(() => view.value === 'trashed')

const query = computed(() => ({
  q: typeof route.query.q === 'string' ? route.query.q : '',
  rubric: number(route.query.rubric),
  tag: number(route.query.tag),
  author: number(route.query.author),
  sort: typeof route.query.sort === 'string' ? route.query.sort : null,
  page: Number(route.query.page ?? 1) || 1,
}))

function number(value: unknown): number | null {
  return typeof value === 'string' && value !== '' ? Number(value) || null : null
}

/** The views of the same list, as tabs over the card. The bin rides in the same row (§10). */
const views = computed<TabItem[]>(() => [
  { value: '', label: t('panel.view-all') },
  { value: 'published', label: t('panel.view-published') },
  { value: 'scheduled', label: t('panel.view-scheduled') },
  { value: 'draft', label: t('panel.view-draft') },
  { value: 'unpublished', label: t('panel.view-unpublished') },
  { value: 'trashed', label: t('panel.bin'), icon: 'trash' },
])

const filters = computed(() => page.value?.filters ?? { rubrics: [], tags: [], authors: [] })

const asCards = computed(() => width.value > 0 && width.value < CARDS)

/**
 * Seven columns, or one.
 *
 * Every column but the title carries a width and the table is laid out `fixed`, so the ones
 * that drop below a breakpoint give what they had to the title rather than to whichever cell
 * happened to hold the longest string. They go in the order they can be spared — who wrote it,
 * then where it belongs, then when — and what is left at the bottom is the cover, the title and
 * the state, which is what identifies an article and what an editor is looking for.
 */
const columns = computed<TableColumn<ArticleRow>[]>(() => {
  if (asCards.value) {
    return [{ key: 'card', label: '' }]
  }

  return [
    { key: 'cover', label: '', width: 68 },
    { key: 'title', label: t('panel.column-title'), minWidth: 220 },
    { key: 'rubrics', label: t('panel.column-rubrics'), width: 190, hideBelow: 840 },
    { key: 'author', label: t('panel.column-author'), width: 160, hideBelow: 1000 },
    {
      key: 'date',
      label: inBin.value ? t('panel.column-deleted') : t('panel.column-date'),
      width: 130,
      hideBelow: 700,
      // "today at 16:22" is three words the table will break over two lines given half a
      // chance, and a date read down a column has to be one line to be read at all.
      cellClass: 'wx-articles__when',
    },
    { key: 'status', label: t('panel.column-status'), width: 150 },
    { key: 'actions', label: '', width: 56, align: 'right' },
  ]
})

const emptyText = computed(() => {
  if (query.value.q !== '') return t('panel.empty-search')

  return inBin.value ? t('panel.empty-bin') : t('panel.empty')
})

/**
 * What the three dropdowns are set to, said in the reader's words.
 *
 * They live behind the funnel now, and a shut panel says nothing about itself: a list narrowed
 * by something nobody can see is a list that looks wrong. The chips are the answer, and taking
 * one off is the same `narrow()` the dropdown itself calls.
 */
const applied = computed<AppliedFilter[]>(() => {
  const chips: AppliedFilter[] = []

  const rubric = filters.value.rubrics.find((item) => item.id === query.value.rubric)
  if (rubric) {
    chips.push({
      key: 'rubric',
      label: `${t('panel.filter-rubric')}: ${rubric.title}`,
      clear: () => narrow('rubric', undefined),
    })
  }

  const tag = filters.value.tags.find((item) => item.id === query.value.tag)
  if (tag) {
    chips.push({
      key: 'tag',
      label: `${t('panel.filter-tag')}: ${tag.title}`,
      clear: () => narrow('tag', undefined),
    })
  }

  const author = filters.value.authors.find((item) => item.id === query.value.author)
  if (author) {
    chips.push({
      key: 'author',
      label: `${t('panel.filter-author')}: ${author.title}`,
      clear: () => narrow('author', undefined),
    })
  }

  return chips
})

/** All three at once, from inside the panel they were set in. */
function clearFilters(): void {
  void router.replace({
    query: {
      ...route.query,
      rubric: undefined,
      tag: undefined,
      author: undefined,
      page: undefined,
    },
  })
}

async function load(state?: TableState): Promise<void> {
  loading.value = true

  try {
    page.value = await api.articles({
      q: state?.search ?? query.value.q,
      status: inBin.value ? '' : (view.value as ArticleStatus | ''),
      trashed: inBin.value,
      rubric: query.value.rubric,
      tag: query.value.tag,
      author: query.value.author,
      sort: state?.sort ? `${state.sort.order === 'desc' ? '-' : ''}${state.sort.key}` : null,
      page: state?.page ?? query.value.page,
      per_page: state?.perPage,
    })
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

/**
 * The table reports everything it knows in one event, and that is the one place the address is
 * written from — so a page, a sort and a search all survive opening an article and coming back,
 * and none of them has its own half of the bookkeeping.
 */
function onState(state: TableState): void {
  const wanted: LocationQueryRaw = {
    ...route.query,
    q: state.search === '' ? undefined : state.search,
    sort:
      state.sort === null
        ? undefined
        : `${state.sort.order === 'desc' ? '-' : ''}${state.sort.key}`,
    page: state.page === 1 ? undefined : String(state.page),
  }

  void router.replace({ query: wanted })
  void load(state)
}

/** One of the three dropdowns moved: a new list, from its first page. */
function narrow(name: 'rubric' | 'tag' | 'author', value: unknown): void {
  void router.replace({
    query: {
      ...route.query,
      [name]: typeof value === 'number' ? String(value) : undefined,
      page: undefined,
    },
  })
}

watch(
  () => [view.value, query.value.rubric, query.value.tag, query.value.author] as const,
  () => void load(),
)

void load()

/**
 * The editor of an article arrives with session C; until it does, the row opens nothing rather
 * than pushing the router at a route that is not there.
 *
 * The bin never reaches here: the table is told the rows lead nowhere, and it withholds the
 * click along with the pointer and the highlight (CLAUDE.md §4).
 */
function open(article: ArticleRow): void {
  const path = `${props.base}/articles/${article.id}`

  if (router.resolve(path).matched.length > 0) void router.push({ path, query: { ...route.query } })
}

/** A new article: the dialog asks for a title, the list shows it as a draft, the editor opens. */
async function add(): Promise<void> {
  const article = await create({})

  if (!article) return

  await load()
  open(article)
}

function actionsFor(article: ArticleRow): RowAction[] {
  if (!canManage.value) return []

  if (inBin.value) {
    return [
      {
        key: 'restore',
        icon: 'refresh',
        label: t('panel.restore'),
        run: () => void restore(article),
      },
    ]
  }

  const actions: RowAction[] = [
    { key: 'open', icon: 'edit', label: t('panel.open'), run: () => open(article) },
  ]

  if (article.url && article.status !== 'draft') {
    actions.push({
      key: 'site',
      icon: 'external-link',
      label: t('panel.open-on-site'),
      href: article.url,
    })
  }

  if (article.path !== null) {
    actions.push({
      key: 'copy',
      icon: 'copy',
      label: t('panel.copy-address'),
      run: () => void copyAddress(article),
    })
  }

  actions.push({
    key: 'pin',
    icon: 'star',
    label: article.pinned ? t('panel.unpin') : t('panel.pin'),
    run: () => void setPinned(article, !article.pinned),
  })

  actions.push(
    article.status === 'published' ||
      article.status === 'modified' ||
      article.status === 'scheduled'
      ? {
          key: 'unpublish',
          icon: 'eye-off',
          label: t('panel.unpublish'),
          run: () => void unpublish(article),
        }
      : {
          key: 'publish',
          icon: 'upload',
          label: t('panel.publish'),
          run: () => void publish(article),
        },
  )

  actions.push({
    key: 'delete',
    icon: 'trash',
    label: t('panel.delete'),
    danger: true,
    run: () => void remove(article),
  })

  return actions
}

async function publish(article: ArticleRow): Promise<void> {
  await run(() => api.publish(article.id), t('panel.published'))
}

async function unpublish(article: ArticleRow): Promise<void> {
  await run(() => api.unpublish(article.id), t('panel.unpublished-done'))
}

async function setPinned(article: ArticleRow, pinned: boolean): Promise<void> {
  await run(
    () => api.save(article.id, { values: { pinned }, revision: article.revision }),
    pinned ? t('panel.pinned-done') : t('panel.unpinned'),
  )
}

async function remove(article: ArticleRow): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-title', { title: article.title }),
    message: t('panel.delete-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  await run(() => api.remove(article.id), t('panel.deleted'))
}

async function restore(article: ArticleRow): Promise<void> {
  await run(() => api.restore(article.id), t('panel.restored'))
}

/** One request, one sentence about it, and the list as the server now sees it. */
async function run(request: () => Promise<unknown>, said: string): Promise<void> {
  try {
    await request()
    toast.success(said)
  } catch (error) {
    toast.danger(message(error))
  } finally {
    // Either way: a refused publication has left the row saying what it was, and one that
    // worked has changed an order the answer does not carry row by row.
    await load()
  }
}

async function copyAddress(article: ArticleRow): Promise<void> {
  if (!article.url) return

  try {
    await navigator.clipboard.writeText(article.url)
    toast.success(t('panel.address-copied'))
  } catch {
    // A browser that refuses the clipboard without a gesture it trusts; the address is on
    // screen and can be copied from there.
  }
}

/**
 * Live and live-with-edits are the same green: the article is on the site, and what is waiting
 * beside it is said by a chip of its own rather than by a different colour. An editor scanning
 * the column is asking "is this up", and two greens answer it once.
 */
function badge(status: ArticleStatus): 'default' | 'success' | 'warning' | 'danger' {
  if (status === 'published' || status === 'modified') return 'success'
  if (status === 'scheduled') return 'warning'

  return 'default'
}

/** The address under the title, and the two different nothings that can stand there instead. */
function address(article: ArticleRow): string {
  if (inBin.value) return '—'

  return article.path === null ? t('panel.no-address') : `/${article.path}`
}

/** The date a row is about: when it was published, or when it was deleted in the bin. */
function dateOf(article: ArticleRow): string | null {
  return inBin.value ? article.deleted_at : article.published_at
}
</script>

<template>
  <div ref="root" class="wx-articles">
    <wx-list-screen v-model:view="view" :title="title" :views="views">
      <template v-if="canManage" #actions>
        <wx-button type="primary" icon="plus" @click="add">
          {{ t('panel.new') }}
        </wx-button>
      </template>

      <wx-table
        :data="page"
        :columns="columns"
        row-key="id"
        searchable
        :clickable="!inBin"
        :hover="!inBin"
        flush
        layout="fixed"
        :loading="loading"
        :cards-below="CARDS"
        :filters-count="applied.length"
        :filters-label="admin('filters.title')"
        :search-placeholder="t('panel.search')"
        :empty-text="emptyText"
        :aria-label="title"
        @row-click="open"
        @state-change="onState"
      >
        <!--
          The three dropdowns live behind the funnel, and what they are set to comes back as
          chips beside it. Standing open they were three controls of chrome above the first
          article, and on a phone — where the table stacks everything in its head into a column —
          three lines of it before any data.
        -->
        <template #filters>
          <wx-form-item :label="t('panel.filter-rubric')">
            <wx-select
              :model-value="query.rubric"
              :options="filters.rubrics.map((item) => ({ value: item.id, label: item.title }))"
              :placeholder="t('panel.any-rubric')"
              clearable
              size="sm"
              @update:model-value="(value: unknown) => narrow('rubric', value)"
            />
          </wx-form-item>

          <wx-form-item :label="t('panel.filter-tag')">
            <wx-select
              :model-value="query.tag"
              :options="filters.tags.map((item) => ({ value: item.id, label: item.title }))"
              :placeholder="t('panel.any-tag')"
              clearable
              filterable
              size="sm"
              @update:model-value="(value: unknown) => narrow('tag', value)"
            />
          </wx-form-item>

          <wx-form-item :label="t('panel.filter-author')">
            <wx-select
              :model-value="query.author"
              :options="filters.authors.map((item) => ({ value: item.id, label: item.title }))"
              :placeholder="t('panel.any-author')"
              clearable
              size="sm"
              @update:model-value="(value: unknown) => narrow('author', value)"
            />
          </wx-form-item>

          <!-- Taking all of them off belongs with the fields it resets: beside the chips it
               reads as one more chip, and it is the only control there that does not take
               exactly one filter away. -->
          <wx-button v-if="applied.length > 0" variant="text" size="sm" block @click="clearFilters">
            <template #icon><wx-icon name="close" /></template>
            {{ admin('filters.reset') }}
          </wx-button>
        </template>

        <template #applied>
          <wx-filter-chips :filters="applied" />
        </template>

        <template #cell-cover="{ row }">
          <span class="wx-articles__cover" :class="{ 'is-empty': !row.cover }">
            <img v-if="row.cover?.thumb" :src="row.cover.thumb" alt="" loading="lazy" />
            <wx-icon v-else name="image" size="sm" />
          </span>
        </template>

        <template #cell-title="{ row }">
          <div class="wx-articles__name">
            <wx-tooltip v-if="row.pinned" :content="t('panel.pinned')">
              <wx-icon class="wx-articles__pin" name="star" size="sm" />
            </wx-tooltip>
            <div class="wx-articles__name-text">
              <span class="wx-articles__title">{{ row.title }}</span>
              <!-- An article in the bin holds no address — the registry let it go when it went
                   in — and "the address appears when it is published" would be a promise about
                   something nobody can publish without bringing it back first. -->
              <span class="wx-articles__address">
                {{ address(row) }}
              </span>
            </div>
          </div>
        </template>

        <template #cell-rubrics="{ row }">
          <span class="wx-articles__chips">
            <wx-badge v-for="rubric in row.rubrics" :key="rubric.id" size="sm" round>
              {{ rubric.title }}
            </wx-badge>
          </span>
        </template>

        <template #cell-author="{ row }">
          <wx-text size="sm" tone="muted" truncate>
            {{ row.author ? row.author.name : '—' }}
          </wx-text>
        </template>

        <template #cell-date="{ row }">
          <wx-date v-if="dateOf(row)" :value="dateOf(row)" />
          <wx-text v-else size="sm" tone="muted">—</wx-text>
        </template>

        <template #cell-status="{ row }">
          <span class="wx-articles__state">
            <wx-badge :type="badge(row.status)" dot size="sm">
              {{ t(`panel.status-${row.status}`) }}
            </wx-badge>
            <!-- What is waiting, said beside the state rather than instead of it: the article
                 is on the site, and there is something that is not. -->
            <wx-badge v-if="row.status === 'modified'" type="primary" size="sm" round>
              {{ t('panel.edits') }}
            </wx-badge>
          </span>
        </template>

        <!--
          The card: the cover on the left, everything else beside it. One field and no label,
          because the table's own stack of "Title: … / Author: … / Date: …" is five lines and
          four hundred pixels for one article (§10).
        -->
        <!--
          `WxEntityCard`, `plain` so that the box around it stays the table's own: a picture, a
          name, its address under it and a row of facts is exactly what the component is, and
          writing that by hand once per section is how five sections end up with five different
          cards.
        -->
        <template #cell-card="{ row }">
          <wx-entity-card
            class="wx-articles__entity"
            variant="plain"
            :title="row.title"
            :image="row.cover?.thumb ?? undefined"
            image-size="56px"
            :title-lines="2"
            :subtitle="address(row)"
          >
            <template #title>
              <wx-icon v-if="row.pinned" class="wx-articles__pin" name="star" size="sm" />
              {{ row.title }}
            </template>

            <template #meta>
              <wx-badge v-if="row.rubrics[0]" size="sm" round>{{ row.rubrics[0].title }}</wx-badge>
              <wx-badge :type="badge(row.status)" dot size="sm">
                {{ t(`panel.status-${row.status}`) }}
              </wx-badge>
              <wx-date v-if="dateOf(row)" :value="dateOf(row)" />
            </template>
          </wx-entity-card>
        </template>

        <!--
          The menu goes in the card's own top strip, beside the checkbox: that line is there
          either way as soon as rows can be picked, and a `···` inside the entity takes width
          off a title that is already one line short.
        -->
        <template #card-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="row.title" />
        </template>

        <template #cell-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="row.title" />
        </template>
      </wx-table>
    </wx-list-screen>
  </div>
</template>

<style scoped>
/* The list screen inside it lays the screen out; this only keeps a wide table from pushing the
   column it sits in. */
.wx-articles {
  min-width: 0;
}

/*
 * The cover is the picture's box, never the picture: `<img>` has `min-width: auto`, which is
 * its natural width, so a 1600px photograph would take the column and the ones beside it
 * (CLAUDE.md §4).
 */
.wx-articles__cover {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 32px;
  overflow: hidden;
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-subtle);
  color: var(--wx-text-muted);
}

.wx-articles__cover.is-empty {
  border: 1px dashed var(--wx-border-color);
  background: var(--wx-bg-base);
}

.wx-articles__cover img {
  width: 100%;
  height: 100%;
  min-width: 0;
  object-fit: cover;
}

.wx-articles__name {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-articles__name-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-articles__pin {
  flex: none;
  color: var(--wx-color-primary);
}

.wx-articles__title {
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.wx-articles__address {
  overflow: hidden;
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.wx-articles__chips,
.wx-articles__state {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  overflow: hidden;
}

/* `:deep()` because the cell is the table's element and the class is ours — a scoped rule
   would be looking for our attribute on somebody else's markup (CLAUDE.md §4). */
.wx-articles :deep(.wx-articles__when) {
  white-space: nowrap;
}
</style>
