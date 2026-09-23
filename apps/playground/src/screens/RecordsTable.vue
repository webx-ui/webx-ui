<script setup lang="ts">
import { computed, ref, useTemplateRef, watch } from 'vue'
import { useElementWidth } from '@webx-ui/core'
import type { RowKey, TableColumn, TableState, TableSummaryRow, TabValue } from '@webx-ui/core'

/**
 * The list itself: fourteen columns, the filters behind the funnel, the card for narrow boxes.
 *
 * A component rather than markup inside the screen, because the whole point of the bench is to
 * put the same table in different boxes — a screen and a drawer — and find out what each box
 * does to it. Two copies, each fetching for itself, is also closer to the truth than one moved
 * around: two lists on one screen is a thing panels do.
 *
 * The rows come over HTTP from the dev server (`apps/playground/server/records.ts`) in Laravel's
 * paginator shape, and paging, sorting, searching and all four filters are answered there.
 */
type Status = 'published' | 'modified' | 'scheduled' | 'draft' | 'unpublished'

interface Named {
  id: number
  title: string
}

interface Row extends Record<string, unknown> {
  id: number
  title: string
  slug: string
  cover: string | null
  rubrics: Named[]
  author: { id: number; name: string; initials: string } | null
  channel: string
  views: number
  ctr: number
  revenue: number
  comments: number
  source: string
  note: string
  published_at: string | null
  status: Status
  pinned: boolean
}

interface Page {
  data: Row[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
  filters: { rubrics: Named[]; authors: Named[]; channels: string[] }
}

const props = withDefaults(
  defineProps<{
    /** Which view of the list to ask for: the status, or `''` for all of them. */
    view?: TabValue
    /** Where the row stops being a row. The same number tells the table and the columns. */
    cardsBelow?: number
    /** Heading inside the table's own head. A screen that has one above the card needs none. */
    title?: string
    perPage?: number
  }>(),
  { view: '', cardsBelow: 640, title: undefined, perPage: 20 },
)

const emit = defineEmits<{ loaded: [total: number] }>()

const selected = defineModel<RowKey[]>('selected', { default: () => [] })

const STATUS_LABELS: Record<Status, string> = {
  published: 'Жива',
  modified: 'Жива',
  scheduled: 'Запланована',
  draft: 'Чернетка',
  unpublished: 'Знята',
}

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)

/** Whether the table is drawing cards, worked out from the same box it measures. */
const asCards = computed(() => width.value > 0 && width.value < props.cardsBelow)

const page = ref<Page | null>(null)
const loading = ref(true)
const state = ref<TableState | null>(null)

const rubric = ref<number | null>(null)
const author = ref<number | null>(null)
const channel = ref<string | null>(null)

const filters = computed(
  () => page.value?.filters ?? { rubrics: [], authors: [], channels: [] as string[] },
)

/* A new view is a new list, and it starts at its first page. */
watch(
  () => props.view,
  () => narrow(),
)

/* ------------------------------------------------------------------ loading --- */

async function load(request?: TableState): Promise<void> {
  if (request) state.value = request

  const current = state.value
  loading.value = true

  const params = new URLSearchParams({
    page: String(current?.page ?? 1),
    per_page: String(current?.perPage ?? props.perPage),
    q: current?.search ?? '',
    sort: current?.sort ? `${current.sort.order === 'desc' ? '-' : ''}${current.sort.key}` : '',
    status: String(props.view ?? ''),
    rubric: rubric.value === null ? '' : String(rubric.value),
    author: author.value === null ? '' : String(author.value),
    channel: channel.value ?? '',
  })

  try {
    const response = await fetch(`/api/records?${params.toString()}`)
    page.value = (await response.json()) as Page
    emit('loaded', page.value.total)
  } finally {
    loading.value = false
  }
}

/** A filter moved: a different list, from its first page. */
function narrow(): void {
  if (state.value) state.value = { ...state.value, page: 1 }
  void load()
}

/**
 * What is on, in the reader's words rather than in the query's.
 *
 * The table gives the strip and the spacing; naming a filter and taking it off is the caller's
 * job, because only the caller knows that `rubric=2` reads "Рубрика: Новини".
 */
const applied = computed(() => {
  const chips: { key: string; label: string; clear: () => void }[] = []

  const rubricOn = filters.value.rubrics.find((item) => item.id === rubric.value)
  if (rubricOn) {
    chips.push({
      key: 'rubric',
      label: `Рубрика: ${rubricOn.title}`,
      clear: () => {
        rubric.value = null
        narrow()
      },
    })
  }

  const authorOn = filters.value.authors.find((item) => item.id === author.value)
  if (authorOn) {
    chips.push({
      key: 'author',
      label: `Автор: ${authorOn.title}`,
      clear: () => {
        author.value = null
        narrow()
      },
    })
  }

  if (channel.value) {
    chips.push({
      key: 'channel',
      label: `Канал: ${channel.value}`,
      clear: () => {
        channel.value = null
        narrow()
      },
    })
  }

  return chips
})

function clearFilters(): void {
  rubric.value = null
  author.value = null
  channel.value = null
  narrow()
}

/* ------------------------------------------------------------------ columns --- */

const number = (value: unknown) => (value === 0 ? '—' : Number(value).toLocaleString('uk-UA'))

const money = (value: unknown) =>
  value === 0 ? '—' : `€${Number(value).toLocaleString('uk-UA', { minimumFractionDigits: 2 })}`

const percent = (value: unknown) => `${Number(value).toFixed(1)} %`

/**
 * Fourteen columns, or one.
 *
 * Every column but the title carries a width and the table is laid out `fixed`, so a column
 * that drops below its breakpoint gives what it had to the title rather than to whichever cell
 * happens to hold the longest string. They go in the order they can be spared, and what is left
 * at the narrow end is the cover, the title, the state and the menu.
 */
const columns = computed<TableColumn<Row>[]>(() => {
  if (asCards.value) return [{ key: 'card', label: '' }]

  return [
    { key: 'cover', label: '', width: 68 },
    { key: 'title', label: 'Заголовок', minWidth: 240 },
    { key: 'rubrics', label: 'Рубрики', width: 190, hideBelow: 1090 },
    { key: 'author', label: 'Автор', width: 170, hideBelow: 1390 },
    { key: 'channel', label: 'Канал', width: 140, hideBelow: 1530 },
    {
      key: 'views',
      label: 'Перегляди',
      width: 110,
      align: 'right',
      sortable: true,
      hideBelow: 810,
    },
    { key: 'ctr', label: 'CTR', width: 90, align: 'right', sortable: true, hideBelow: 900 },
    {
      key: 'revenue',
      label: 'Дохід',
      width: 130,
      align: 'right',
      sortable: true,
      formatter: money,
      hideBelow: 1220,
    },
    { key: 'comments', label: 'Коментарі', width: 110, align: 'center', hideBelow: 1640 },
    { key: 'source', label: 'Джерело', width: 200, hideBelow: 1840 },
    { key: 'note', label: 'Примітка', width: 240, hideBelow: 2080 },
    {
      key: 'published_at',
      label: 'Дата',
      width: 140,
      sortable: true,
      hideBelow: 700,
      cellClass: 'lab__when',
    },
    { key: 'status', label: 'Статус', width: 150 },
    { key: 'actions', label: '', width: 56, align: 'right' },
  ]
})

const summary = computed<TableSummaryRow[]>(() => {
  const rows = page.value?.data ?? []
  if (rows.length === 0 || asCards.value) return []

  return [
    {
      label: `На сторінці — ${rows.length}`,
      cells: {
        views: number(rows.reduce((sum, row) => sum + row.views, 0)),
        revenue: money(rows.reduce((sum, row) => sum + row.revenue, 0)),
        comments: rows.reduce((sum, row) => sum + row.comments, 0),
      },
      strong: true,
    },
  ]
})

/* -------------------------------------------------------------------- cells --- */

function badge(value: Status): 'default' | 'success' | 'warning' {
  if (value === 'published' || value === 'modified') return 'success'
  if (value === 'scheduled') return 'warning'

  return 'default'
}

/** Dates as a list shows them: the day, and the clock only where it is still news. */
function when(value: string | null): string {
  if (!value) return '—'

  const date = new Date(value)
  const days = Math.round((Date.now() - date.getTime()) / 86_400_000)
  const clock = date.toLocaleTimeString('uk-UA', { hour: '2-digit', minute: '2-digit' })

  if (days === 0) return `сьогодні о ${clock}`
  if (days === 1) return `вчора о ${clock}`

  return date.toLocaleDateString('uk-UA', { day: 'numeric', month: 'long' })
}
</script>

<template>
  <!-- The ref sits on the table's own box: `cards-below` is measured against the table, and a
       screen measured instead disagrees with it by the width of the card's padding — at which
       point the columns say "row" while the table draws cards. -->
  <div ref="root" class="lab__table">
    <wx-table
      v-model:selected="selected"
      :data="page"
      :columns="columns"
      row-key="id"
      :title="title"
      searchable
      selectable
      :filters-count="applied.length"
      filters-label="Фільтри"
      hover
      flush
      layout="fixed"
      :loading="loading"
      :cards-below="cardsBelow"
      :per-page="perPage"
      :per-page-options="[10, 20, 50, 100]"
      :summary="summary"
      search-placeholder="Пошук за заголовком або адресою"
      empty-text="За цими умовами нічого немає"
      aria-label="Записи"
      @state-change="load"
    >
      <!--
        The fields live behind the funnel, and what they are set to comes back as chips under
        the head. Three dropdowns standing open cost three controls of chrome above the first
        row; the chips cost a line only while something is on.
      -->
      <template #filters>
        <wx-form-item label="Рубрика">
          <wx-select
            v-model="rubric"
            :options="filters.rubrics.map((item) => ({ value: item.id, label: item.title }))"
            placeholder="Будь-яка"
            clearable
            size="sm"
            @change="narrow"
          />
        </wx-form-item>

        <wx-form-item label="Автор">
          <wx-select
            v-model="author"
            :options="filters.authors.map((item) => ({ value: item.id, label: item.title }))"
            placeholder="Будь-який"
            clearable
            filterable
            size="sm"
            @change="narrow"
          />
        </wx-form-item>

        <wx-form-item label="Канал">
          <wx-select
            v-model="channel"
            :options="filters.channels.map((item) => ({ value: item, label: item }))"
            placeholder="Будь-який"
            clearable
            size="sm"
            @change="narrow"
          />
        </wx-form-item>

        <!-- "Reset everything" belongs with the fields it resets, not beside the chips: next to
             them it reads as one more chip, and it is the only control in that row that does not
             take one filter off. -->
        <wx-button v-if="applied.length > 0" variant="text" size="sm" block @click="clearFilters">
          <template #icon><wx-icon name="close" /></template>
          Скинути все
        </wx-button>
      </template>

      <template #applied>
        <wx-badge
          v-for="chip in applied"
          :key="chip.key"
          size="sm"
          round
          closable
          @close="chip.clear()"
        >
          {{ chip.label }}
        </wx-badge>
      </template>

      <template #cell-cover="{ row }">
        <span class="lab__cover" :class="{ 'is-empty': !row.cover }">
          <img v-if="row.cover" :src="row.cover" alt="" loading="lazy" />
          <wx-icon v-else name="image" size="sm" />
        </span>
      </template>

      <template #cell-title="{ row }">
        <div class="lab__name">
          <wx-tooltip v-if="row.pinned" content="Закріплено">
            <wx-icon class="lab__pin" name="star" size="sm" />
          </wx-tooltip>
          <div class="lab__name-text">
            <span class="lab__title-text">{{ row.title }}</span>
            <span class="lab__address">{{ row.slug }}</span>
          </div>
        </div>
      </template>

      <template #cell-rubrics="{ row }">
        <span class="lab__chips">
          <wx-badge v-for="item in row.rubrics" :key="item.id" size="sm" round>
            {{ item.title }}
          </wx-badge>
          <wx-text v-if="row.rubrics.length === 0" size="sm" tone="muted">—</wx-text>
        </span>
      </template>

      <template #cell-author="{ row }">
        <div v-if="row.author" class="lab__author">
          <wx-avatar size="xs" :name="row.author.name" />
          <wx-text size="sm" truncate>{{ row.author.name }}</wx-text>
        </div>
        <wx-text v-else size="sm" tone="muted">—</wx-text>
      </template>

      <template #cell-channel="{ value }">
        <wx-text size="sm" tone="muted" truncate>{{ value }}</wx-text>
      </template>

      <template #cell-views="{ value }">
        <span class="lab__figure">{{ number(value) }}</span>
      </template>

      <template #cell-ctr="{ value }">
        <span class="lab__figure">{{ percent(value) }}</span>
      </template>

      <template #cell-comments="{ value }">
        <wx-text size="sm" :tone="value === 0 ? 'muted' : 'default'">
          {{ value === 0 ? '—' : value }}
        </wx-text>
      </template>

      <!-- One token, no spaces, three times the width of its column: the case every `overflow`
           rule in the table has to survive. -->
      <template #cell-source="{ value }">
        <wx-text v-if="value" class="lab__source" size="xs" tone="muted" truncate>
          {{ value }}
        </wx-text>
        <wx-text v-else size="sm" tone="muted">—</wx-text>
      </template>

      <template #cell-note="{ value }">
        <wx-text v-if="value" size="sm" tone="muted" truncate>{{ value }}</wx-text>
        <wx-text v-else size="sm" tone="muted">—</wx-text>
      </template>

      <template #cell-published_at="{ value }">
        <wx-text size="sm" :tone="value ? 'default' : 'muted'">{{ when(value as string) }}</wx-text>
      </template>

      <template #cell-status="{ row }">
        <span class="lab__state">
          <wx-badge :type="badge(row.status)" dot size="sm">
            {{ STATUS_LABELS[row.status as Status] }}
          </wx-badge>
          <wx-badge v-if="row.status === 'modified'" type="primary" size="sm" round>
            правки
          </wx-badge>
        </span>
      </template>

      <template #cell-actions="{ row }">
        <wx-actions class="lab__menu" collapse="always" align="end" size="sm" @click.stop>
          <template #collapsed>
            <wx-dropdown-item icon="edit">Відкрити</wx-dropdown-item>
            <wx-dropdown-item icon="external-link">Відкрити на сайті</wx-dropdown-item>
            <wx-dropdown-item icon="copy">Скопіювати адресу</wx-dropdown-item>
            <wx-dropdown-item icon="star">
              {{ row.pinned ? 'Відкріпити' : 'Закріпити' }}
            </wx-dropdown-item>
            <hr class="wx-dropdown__divider" />
            <wx-dropdown-item icon="trash" tone="danger">Видалити</wx-dropdown-item>
          </template>
        </wx-actions>
      </template>

      <!--
        The card is `WxEntityCard`, `plain` so that the box around it stays the table's own: a
        picture, a name, an address under it and a row of facts is what the component is, and
        writing that by hand once per section is how five sections end up with five different
        cards.
      -->
      <template #cell-card="{ row }">
        <wx-entity-card
          class="lab__entity"
          variant="plain"
          :title="row.title"
          :image="row.cover ?? undefined"
          image-size="56px"
          :subtitle="row.slug"
        >
          <template #title>
            <wx-icon v-if="row.pinned" class="lab__pin" name="star" size="sm" />
            {{ row.title }}
          </template>

          <template #meta>
            <wx-badge v-if="row.rubrics[0]" size="sm" round>{{ row.rubrics[0].title }}</wx-badge>
            <wx-badge :type="badge(row.status)" dot size="sm">
              {{ STATUS_LABELS[row.status as Status] }}
            </wx-badge>
            <wx-text size="sm" tone="muted">{{ when(row.published_at) }}</wx-text>
          </template>
        </wx-entity-card>
      </template>

      <!--
        The menu goes in the card's own top strip, beside the checkbox, rather than in the
        entity's `#actions`: that line exists either way as soon as rows can be picked, and a
        `···` inside the entity takes width off a title that is already one line short.
      -->
      <template #card-actions>
        <wx-actions class="lab__menu" collapse="always" align="end" size="sm" @click.stop>
          <template #collapsed>
            <wx-dropdown-item icon="edit">Відкрити</wx-dropdown-item>
            <wx-dropdown-item icon="trash" tone="danger">Видалити</wx-dropdown-item>
          </template>
        </wx-actions>
      </template>
    </wx-table>
  </div>
</template>

<style scoped>
.lab__table {
  min-width: 0;
}

/*
 * The cover is the picture's box, never the picture: `<img>` has `min-width: auto`, which is
 * its natural width, so a 1200px cover would take the column and the ones beside it.
 */
.lab__cover {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 32px;
  overflow: hidden;
  /* The smallest step in the scale, and the one `WxEntityCard` gives its own thumbnail: on a
     box 32px tall the next one up is 12, which is over a third of it and reads as a pill. */
  border-radius: var(--wx-radius-xs);
  background: var(--wx-bg-subtle);
  color: var(--wx-text-muted);
}

.lab__cover.is-empty {
  border: 1px dashed var(--wx-border-default);
  background: var(--wx-bg-base);
}

.lab__cover img {
  width: 100%;
  height: 100%;
  min-width: 0;
  object-fit: cover;
}

.lab__name {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.lab__name-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.lab__pin {
  flex: none;
  color: var(--wx-color-primary);
}

.lab__title-text {
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.lab__address {
  overflow: hidden;
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.lab__chips,
.lab__state {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  overflow: hidden;
}

.lab__author {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
}

.lab__figure {
  font-variant-numeric: tabular-nums;
  font-size: var(--wx-font-size-sm);
  white-space: nowrap;
}

.lab__source {
  /* A UTM tail has nowhere to break, and a cell that lets it through drags the table. */
  word-break: break-all;
}

/* `:deep()` because the cell is the table's element and the class is ours. */
:deep(.lab__when) {
  white-space: nowrap;
}
</style>
