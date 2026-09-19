<script setup lang="ts">
import { computed, ref, useTemplateRef, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  rowMenuWidth,
  WxRowMenu,
  type RowAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  localizedValue,
  toast,
  useLocales,
  WxAction,
  WxAvatar,
  WxBadge,
  WxButton,
  WxHeading,
  WxIcon,
  WxSelect,
  WxTable,
  WxTabs,
  WxText,
  WxEntityCard,
  WxTooltip,
  useElementWidth,
  type TabItem,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import SubmissionCreateDialog from './SubmissionCreateDialog.vue'
import { createInboxApi } from './api'
import { useInboxMessages } from './i18n'
import type {
  InboxForm,
  InboxStatus,
  InboxSubmission,
  SubmissionRow,
  SubmissionsPage,
} from './types'

/**
 * What has come in through the chosen form — the right-hand side of the section (§11).
 *
 * Its columns are the form's own `in_table` fields and arrive with the rows, so this never has
 * to fetch the form to know what it is drawing. Which of them survive a narrow pane is decided
 * here and not by the form: the first answer identifies the row and stays at any width, and
 * each one after it goes a step earlier than the last. Pairing two of them into one cell — the
 * e-mail under the name — would mean guessing which fields mean what, and the panel does not
 * know that about somebody else's form.
 *
 * The state of the list lives in the address: coming back from a submission has to land on the
 * same form, the same tab and the same page, or the screen costs exactly that over the dialog
 * it was chosen instead of (§11).
 */
const props = withDefaults(
  defineProps<{
    form: InboxForm
    /** Where the section is mounted. */
    base?: string
    /** Whether the pane is beside the list of forms or is the whole screen. */
    inline?: boolean
  }>(),
  { base: '/inbox', inline: true },
)

const emit = defineEmits<{
  /** The pane wants out — only ever on a phone, where it is the screen. */
  back: []
  /** Something changed that the column of forms counts. */
  changed: []
}>()

const context = useAdmin()
const api = createInboxApi(context)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
useInboxMessages()

/** Where a row stops being a row: the table and the columns read the same number. */
const CARDS = 640

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)
const asCards = computed(() => width.value > 0 && width.value < CARDS)

const t = useTranslate('webx-inbox')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const page = ref<SubmissionsPage | null>(null)
const statuses = ref<InboxStatus[]>([])
const loading = ref(false)
const selected = ref<SubmissionRow[]>([])
const moving = ref(false)

const canUpdate = computed(() => context.can('inbox.update'))
const canManage = computed(() => context.can('inbox.manage'))

const add = createModal<InboxSubmission, { form: InboxForm }>(SubmissionCreateDialog)

/** The tab, kept in the address so the way back lands on it. */
const view = computed({
  get: () =>
    typeof route.query.view === 'string' && route.query.view !== '' ? route.query.view : 'all',
  set: (value: string) => {
    // A new tab is a new list: the page it was on belongs to the tab it was on.
    void router.replace({
      query: { ...route.query, view: value === 'all' ? undefined : value, page: undefined },
    })
  },
})

/** Everything the list is looking at, as it travels to the server and into the address. */
const query = computed(() => ({
  view: view.value,
  search: typeof route.query.search === 'string' ? route.query.search : '',
  assignee: typeof route.query.assignee === 'string' ? route.query.assignee : null,
  sort: typeof route.query.sort === 'string' ? route.query.sort : null,
  page: Number(route.query.page ?? 1) || 1,
}))

const views = computed<TabItem[]>(() => {
  const counts = page.value?.counts

  const items: TabItem[] = [
    { value: 'all', label: t('panel.view-all'), badge: counts?.all || undefined },
    { value: 'unread', label: t('panel.view-unread'), badge: counts?.unread || undefined },
  ]

  for (const status of statuses.value) {
    items.push({
      value: status.key,
      label: name(status),
      badge: counts?.statuses[status.key] || undefined,
    })
  }

  return items
})

/**
 * The n-th answer of a submission, for the card that has no columns to put them in.
 *
 * By position rather than by name, because a form's fields are the form's own: the first answer
 * is what the row is recognised by wherever it came from, and the second is the next most likely
 * way to reach whoever wrote it.
 */
function answer(row: SubmissionRow, index: number): string {
  const column = page.value?.columns?.[index]

  return column ? (row.values[column.key] ?? '') : ''
}

const columns = computed<TableColumn<SubmissionRow>[]>(() => {
  const answers = page.value?.columns ?? []

  /*
   * Narrow, a submission is one entity rather than five labelled lines: who wrote it, the next
   * answer under that, and the state of it beside the date. Five lines of "Label: value" is four
   * hundred pixels for one enquiry, and an inbox is read in a list.
   */
  if (asCards.value) return [{ key: 'card', label: '' }]

  return [
    ...answers.map((column, index) => ({
      key: `values.${column.key}`,
      label: column.label,
      sortable: true,
      // The first answer is what the row is recognised by and stays at any width; every one
      // after it is worth less than the room it takes, so it goes a step sooner. The steps are
      // wide — 640, 960 — because an answer is a sentence and not a number: two of them plus
      // the status and the date is already what a 755px pane holds without scrolling sideways,
      // which is what the pane beside the forms actually is on a 1280 screen (§11).
      hideBelow: index === 0 ? undefined : 320 + index * 320,
    })),
    // A floor rather than a width: the cell holds a badge, sometimes an avatar and sometimes
    // a paperclip, and a fixed width that any of them overflows makes the whole table scroll
    // sideways by two pixels.
    { key: 'status', label: t('panel.status'), minWidth: 100 },
    {
      key: 'created_at',
      label: t('panel.received'),
      sortable: true,
      width: 120,
      // "today at 16:22" is three words the table will break over two lines given half a
      // chance, and a date read down a column has to be one line to be read at all.
      cellClass: 'wx-submissions__when',
      hideBelow: 520,
      // Kept on the card, unlike most dates: "when did this come in" is one of the two
      // questions a submission is looked at for, and the other one is who sent it.
    },
    { key: 'actions', label: '', width: rowMenuWidth, align: 'right', hideOnCards: true },
  ]
})

/**
 * Two different nothings, said differently.
 *
 * "Nothing has ever come in through this form" is a fact about the form; "nothing matches" is
 * a fact about the tab somebody is standing on. Saying the first one on a filtered tab tells a
 * reader their form is dead when it is not.
 */
const emptyText = computed(() =>
  query.value.view === 'all' && query.value.search === '' && query.value.assignee === null
    ? t('panel.submissions-empty')
    : t('panel.submissions-none'),
)

const statusOptions = computed(() =>
  statuses.value.map((status) => ({ value: status.id, label: name(status) })),
)

function name(status: InboxStatus): string {
  return localizedValue(status.title, locales.active.value, status.key)
}

function formName(): string {
  return localizedValue(props.form.title, locales.active.value, props.form.slug)
}

async function load(state?: TableState): Promise<void> {
  loading.value = true

  try {
    page.value = await api.submissions(props.form.id, {
      view: query.value.view,
      search: state?.search ?? query.value.search,
      assignee: query.value.assignee,
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
 * written from — so a page, a sort and a search all survive opening a submission and coming
 * back, and none of them has its own half of the bookkeeping.
 */
function onState(state: TableState): void {
  const wanted: LocationQueryRaw = {
    ...route.query,
    search: state.search === '' ? undefined : state.search,
    sort:
      state.sort === null
        ? undefined
        : `${state.sort.order === 'desc' ? '-' : ''}${state.sort.key}`,
    page: state.page === 1 ? undefined : String(state.page),
  }

  void router.replace({ query: wanted })
  void load(state)
}

/* The form changes under the pane — the left column is a list, not a navigation — so the list
   has to notice rather than go on showing the previous form's submissions. */
watch(
  () => [props.form.id, view.value, query.value.assignee] as const,
  () => {
    selected.value = []
    void load()
  },
)

void load()
void statusList()

async function statusList(): Promise<void> {
  try {
    statuses.value = await api.statuses()
  } catch (error) {
    toast.danger(message(error))
  }
}

/** Opening one carries the whole filter along, so its arrows walk the list it came from. */
function open(row: SubmissionRow): void {
  void router.push({ path: `${props.base}/submissions/${row.id}`, query: { ...route.query } })
}

function actionsFor(row: SubmissionRow): RowAction[] {
  if (!canUpdate.value) return []

  return [
    {
      key: 'read',
      icon: row.is_read ? 'eye-off' : 'eye',
      label: row.is_read ? t('panel.mark-unread') : t('panel.mark-read'),
      run: () => void mass([row.id], row.is_read ? 'unread' : 'read'),
    },
    {
      key: 'delete',
      icon: 'trash',
      label: t('panel.delete'),
      danger: true,
      run: () => void remove([row.id]),
    },
  ]
}

async function moveTo(statusId: unknown): Promise<void> {
  if (typeof statusId !== 'number') return

  moving.value = true
  await mass(
    selected.value.map((row) => row.id),
    'status',
    statusId,
  )
  moving.value = false
}

async function remove(ids: number[]): Promise<void> {
  const agreed = await confirm({
    title:
      ids.length === 1
        ? t('panel.delete-submission-title')
        : t('panel.delete-selected-title', { count: ids.length }),
    message: t('panel.delete-submission-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  await mass(ids, 'delete')
}

async function mass(
  ids: number[],
  action: 'status' | 'read' | 'unread' | 'delete',
  statusId?: number,
): Promise<void> {
  if (ids.length === 0) return

  try {
    await api.massSubmissions({ ids, action, status_id: statusId })
    selected.value = []
    await load()
    emit('changed')
  } catch (error) {
    toast.danger(message(error))
  }
}

async function byHand(): Promise<void> {
  const made = await add({ form: props.form })

  if (!made) return

  toast.success(t('panel.created'))
  await load()
  emit('changed')
  void router.push({ path: `${props.base}/submissions/${made.id}`, query: { ...route.query } })
}

/**
 * A plain link and not a fetch: the browser downloads files, and pulling a spreadsheet into
 * memory to hand it straight back is work for nothing. It carries the filter that is on, so
 * what comes out is what is on screen.
 */
const exportHref = computed(() =>
  api.exportUrl(props.form.id, {
    view: query.value.view,
    search: query.value.search,
    assignee: query.value.assignee,
    sort: query.value.sort,
  }),
)

function settings(): void {
  void router.push(`${props.base}/forms/${props.form.id}`)
}
</script>

<template>
  <div ref="root" class="wx-submissions" :class="{ 'is-pane': inline }">
    <div class="wx-submissions__head">
      <!-- On a phone the pane is a screen of its own and the drawer carries no close of its
           own, so the way back has to be here. Beside the list there is nothing to go back to. -->
      <wx-action
        v-if="!inline"
        class="wx-submissions__back"
        icon="arrow-left"
        :title="t('panel.forms')"
        @click="emit('back')"
      />

      <div class="wx-submissions__who">
        <wx-heading :level="3" truncate>{{ formName() }}</wx-heading>
        <wx-text size="sm" tone="muted" mono truncate>{{ form.slug }}</wx-text>
      </div>

      <wx-button v-if="canUpdate" variant="outline" icon="plus" size="sm" @click="byHand">{{
        t('panel.new-submission')
      }}</wx-button>
      <wx-button variant="outline" icon="download" size="sm" :href="exportHref">
        {{ t('panel.export') }}
      </wx-button>
      <wx-button v-if="canManage" variant="outline" icon="settings" size="sm" @click="settings">
        {{ t('panel.settings') }}
      </wx-button>
    </div>

    <!--
      `items` and one panel: the table stays mounted while the tab changes, so the search
      somebody typed does not go with it.
    -->
    <wx-tabs
      v-model="view"
      class="wx-submissions__views"
      :items="views"
      :collapse-below="560"
      :aria-label="t('panel.status')"
    >
      <wx-table
        :data="page"
        :columns="columns"
        row-key="id"
        searchable
        clickable
        hover
        flush
        :loading="loading"
        :selectable="canUpdate"
        :row-class="(row: SubmissionRow) => (row.is_read ? undefined : 'is-unread')"
        :search-placeholder="t('panel.search-submissions')"
        :empty-text="emptyText"
        :cards-below="640"
        @row-click="open"
        @state-change="onState"
        @selection-change="(_keys: unknown, rows: SubmissionRow[]) => (selected = rows)"
      >
        <!-- The pile only offers what can be done to all of it at once; one row's own menu
             keeps the rest. -->
        <template v-if="selected.length > 0" #actions>
          <div class="wx-submissions__mass">
            <wx-text size="sm" weight="medium">
              {{ t('panel.selected', { count: selected.length }) }}
            </wx-text>
            <wx-select
              :model-value="null"
              :options="statusOptions"
              :placeholder="t('panel.move-to')"
              :disabled="moving"
              size="sm"
              style="width: 180px"
              @update:model-value="moveTo"
            />
            <wx-button
              size="sm"
              variant="outline"
              type="danger"
              icon="trash"
              @click="remove(selected.map((row) => row.id))"
            >
              {{ t('panel.delete') }}
            </wx-button>
          </div>
        </template>

        <template #cell-status="{ row }">
          <div class="wx-submissions__state">
            <wx-badge v-if="row.status" :type="row.status.color">{{ name(row.status) }}</wx-badge>
            <!-- The assignee is an avatar beside the status rather than a column: at the
                 width this pane has, a column of names costs an answer (§11). -->
            <wx-avatar
              v-if="row.assignee"
              :name="row.assignee.name"
              :title="row.assignee.name"
              size="xs"
              tone="auto"
            />
            <wx-tooltip v-if="row.files_count > 0" :content="t('panel.attachments')">
              <wx-icon name="file" size="sm" />
            </wx-tooltip>
          </div>
        </template>

        <template #cell-created_at="{ row }">
          <wx-date :value="row.created_at" compact />
        </template>

        <template #cell-card="{ row }">
          <wx-entity-card variant="plain" shape="circle" :title="answer(row, 0)">
            <template #subtitle>{{ answer(row, 1) }}</template>

            <template #meta>
              <wx-badge v-if="row.status" :type="row.status.color" size="sm">
                {{ name(row.status) }}
              </wx-badge>
              <wx-avatar
                v-if="row.assignee"
                :name="row.assignee.name"
                :title="row.assignee.name"
                size="xs"
                tone="auto"
              />
              <wx-icon v-if="row.files_count > 0" name="file" size="sm" />
              <wx-date :value="row.created_at" />
            </template>
          </wx-entity-card>
        </template>

        <template #card-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="String(row.id)" />
        </template>

        <template #cell-actions="{ row }">
          <wx-row-menu :actions="actionsFor(row)" :label="String(row.id)" />
        </template>
      </wx-table>
    </wx-tabs>
  </div>
</template>

<style scoped>
/*
 * The panel's own step, which is 8 on a phone and 16 on a desktop — not a number of this
 * screen's own. Written as 16 here, the pane kept desktop air inside a 375px drawer: the head,
 * the tabs and the rows each took a line of nothing between them, and four rows fitted where
 * six do now.
 *
 * The head and the tabs stand this far from the edge on both. The table takes a step of its
 * own on top of it in the column and none on a sheet — see below for why.
 */
.wx-submissions {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  padding: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
  height: 100%;
  min-height: 0;
}

/*
 * The way back lines up with the name, not with the pair of lines under it: what stands beside
 * it is the form's name and the address it posts to, and a centred row put the arrow level with
 * the gap between the two.
 */
.wx-submissions__head {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-8);
  flex-wrap: wrap;
}

/* The button is taller than the line it stands beside, so aligning their boxes leaves its
   centre low; half the difference back up puts the two centres together. `:deep()` because
   the class is ours and the element it rides is `WxAction`'s (CLAUDE.md §4). */
.wx-submissions__head > :deep(.wx-submissions__back) {
  margin-block-start: -2px;
}

/* The name takes the middle, so the way back stays at the start of the line and the buttons
   stay at its end. */
.wx-submissions__who {
  flex: 1 1 auto;
  min-width: 0;
}

.wx-submissions__views {
  flex: 1 1 auto;
  min-height: 0;
}

/*
 * A step of the table's own, on all four sides, and the same one in both of its views.
 *
 * The pane is a column of a card — a rule down its left side, the card's frame on its right —
 * and a list that begins on the column's own boundary reads as glued to it. One number for the
 * whole table rather than one for the cards: the search field, the rows and the boxes are the
 * same list seen at three widths, and a step that only one of them keeps is a step that shows.
 */
.wx-submissions :deep(.wx-table) {
  padding: var(--wx-table-padding-x);
}

/* As a sheet there is no column and no frame — the screen's edge is the boundary, and the
   pane's own step is all the air the list needs. A second one inside it stood the same list
   further from the edge than it stands on every other screen. */
.wx-drawer .wx-submissions :deep(.wx-table) {
  padding: 0;
}

/*
 * The scroll bar rides in that step rather than in the cards' own right edge.
 *
 * A list that scrolls inside itself is given its bar out of its own width: the cards ended
 * fifteen pixels short of where the search field above them ends, and the step stood beyond the
 * bar rather than beside the cards — air on the wrong side of it, and a list that looks pushed
 * left. Padding cannot answer that; the bar is laid inside the padding box whatever is there.
 * So the scroller reaches the end of the table's padding and keeps its gutter reserved: the
 * cards end where everything above them ends, and the bar stands in the step. `stable`, so a
 * list short enough not to scroll is not a wider list. Only in the column — as a sheet the
 * drawer does the scrolling and there is no bar here to make room for.
 */
.wx-submissions.is-pane :deep(.wx-table__cards) {
  margin-inline-end: calc(-1 * var(--wx-table-padding-x));
  scrollbar-gutter: stable;
}

/*
 * The tabs and the rows they filter are one thing, so they stand closer than two things do.
 * `WxTabs` leaves the panel's step under the strip, which is right where a card follows it;
 * here what follows is the search box of the same table, and a full step between a filter and
 * what it filters reads as a gap between two screens.
 */
.wx-submissions__views :deep(.wx-tabs__panels) {
  padding-top: var(--wx-space-8);
}

.wx-submissions__mass {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex-wrap: wrap;
}

/* `:deep()` because the cell is the table's element and the class is ours — a scoped rule
   would be looking for our attribute on somebody else's markup (CLAUDE.md §4). */
.wx-submissions :deep(.wx-submissions__when) {
  white-space: nowrap;
}

.wx-submissions__state {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--wx-space-6);
  min-width: 0;
}

/*
 * What nobody has opened yet, said on the whole row. `:deep()` because the row is the table's
 * element and the class is ours — a scoped rule would be looking for our attribute on somebody
 * else's markup (CLAUDE.md §4).
 */
.wx-submissions :deep(.is-unread) {
  font-weight: var(--wx-font-weight-medium);
}

.wx-submissions :deep(.is-unread) td:first-child {
  box-shadow: inset 2px 0 0 0 var(--wx-color-primary);
}
</style>
