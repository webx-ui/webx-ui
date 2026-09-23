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
  WxScreenHead,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  localizedValue,
  toast,
  useLocales,
  WxAvatar,
  WxBadge,
  WxButton,
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
  }>(),
  { base: '/inbox' },
)

const emit = defineEmits<{
  /** Something changed that the column of forms counts. */
  changed: []
}>()

const context = useAdmin()
const api = createInboxApi(context)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
useInboxMessages()

/** Where a row stops being a row. */
const CARDS = 640

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)
const asCards = computed(() => width.value > 0 && width.value < CARDS)

/**
 * One decision about it, not two.
 *
 * The pane measures itself and the table measures itself, and between the two stands the
 * pane's own step — so the same number meant two different widths, and in the band between
 * them the table drew cards out of the full set of columns: five lines of "Label: value" for
 * one enquiry, which is exactly what the card exists instead of. The table is told rather
 * than left to work it out: never, or always, and by the same measurement the columns use.
 */
const cardsBelow = computed(() => (asCards.value ? Number.POSITIVE_INFINITY : 0))

const t = useTranslate('webx-inbox')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const page = ref<SubmissionsPage | null>(null)
const statuses = ref<InboxStatus[]>([])
const loading = ref(false)
const selected = ref<SubmissionRow[]>([])
const moving = ref(false)

const canUpdate = computed(() => context.can('inbox.update'))

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

/*
 * The tools of the pane are what is done to this list of answers, and that is one thing:
 * take them away as a file.
 *
 * What the form is — its fields, its letters, its address — belongs to the form's own ··· in
 * the list of forms. And writing a submission is the section's own action, so it stands in
 * the head of the section beside its name (§11); the dialog is still this component's, which
 * is why it is exposed rather than moved: what is created has to land in this list, in this
 * filter, and be counted in these tabs.
 */
const actions = computed<ScreenAction[]>(() => [
  { key: 'export', label: t('panel.export'), icon: 'download', href: exportHref.value },
])

defineExpose({ create: byHand })
</script>

<template>
  <div ref="root" class="wx-submissions">
    <wx-screen-head
      class="wx-submissions__head"
      :level="3"
      :title="formName()"
      :subtitle="form.slug"
      :actions="actions"
    />

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
        :cards-below="cardsBelow"
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
 * One step, and everything in the pane keeps it.
 *
 * The panel's own — 8 on a phone and 16 on a desktop — and not a number of this screen's own.
 * Written as 16 here, the pane kept desktop air on a 375px screen: the head, the tabs and the
 * rows each took a line of nothing between them, and four rows fitted where six do now.
 *
 * It is the pane that holds it, so the name of the form, the tabs, the search box and the rows
 * all begin on the same line down the left. The table used to add a step of its own inside
 * this one — measured on a phone: the head at 17 and the search at 33 — and two insets, one
 * for the words and one for the list they are about, read as two panels stacked rather than as
 * one screen.
 */
.wx-submissions {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  padding: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
}

/* The address the form posts to, in the type an address is written in. */
.wx-submissions__head :deep(.wx-screen-head__subtitle) {
  font-family: var(--wx-font-family-mono);
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
