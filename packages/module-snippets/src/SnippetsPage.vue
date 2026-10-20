<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  confirm,
  toast,
  WxAlert,
  WxBadge,
  WxButton,
  WxCard,
  WxEmpty,
  WxIcon,
  WxListDetail,
  WxSkeleton,
  WxSortableList,
  WxSwitch,
  WxTooltip,
  type TabItem,
  type TabValue,
} from '@webx-ui/core'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  WxListScreen,
  WxRowMenu,
  type RowAction,
} from '@webx-ui/module-admin'
import { createSnippetsApi } from './api'
import { useSnippetsMessages } from './i18n'
import SnippetPane from './SnippetPane.vue'
import { silentHere } from './copy'
import { coverageOf, DRAG_GROUP, localesOf } from './summary'
import type { SnippetPlace, SnippetRow } from './types'

/**
 * The codes, by place (§7.2): every place of the config with its codes in the order they are
 * printed, the one being edited beside them.
 *
 * Grouped by place and not one long list, because the place is what decides where the code
 * lands on the page — two codes in `head` are ordered against each other and against nothing
 * else. A drag inside a place writes its order; a drag into another place moves the code there,
 * and writes the order of the place it was dropped in, which is all the server needs.
 *
 * A place taken out of the config but still holding codes is a group at the end with the
 * reason it prints nothing, and nothing is dropped into it (§4.3). A declared place no page has
 * printed yet says so beside its name (§10.3).
 *
 * Everything the screen looks at lives in the address — the bin, the open code — so a link to a
 * code is a link to it in its list.
 */
withDefaults(defineProps<{ base?: string }>(), { base: '/snippets' })

const context = useAdmin()
const api = createSnippetsApi(context)
const route = useRoute()
const router = useRouter()
useSnippetsMessages()

const t = useTranslate('webx-snippets')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const places = ref<SnippetPlace[]>([])
const bin = ref<SnippetRow[]>([])
const environment = ref('production')
const everythingPrinted = ref(true)
const loading = ref(true)

const canManage = computed(() => context.can('snippets.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'snippets')?.title ??
    t('module.title'),
)

const view = computed<TabValue>({
  get: () => (route.query.view === 'trashed' ? 'trashed' : ''),
  set: (value) => {
    const query: LocationQueryRaw = {
      ...route.query,
      view: value === '' ? undefined : String(value),
    }

    // A code from the list is not a code from the bin: the form closes with the view.
    delete query.snippet
    delete query.place
    void router.replace({ query })
  },
})

const inBin = computed(() => view.value === 'trashed')

const views = computed<TabItem[]>(() => [
  { value: '', label: t('panel.all') },
  { value: 'trashed', label: t('panel.bin'), icon: 'trash' },
])

/** The open code: a number, `'new'` for one not yet saved, or nothing. */
const selected = computed<number | 'new' | null>(() => {
  const raw = route.query.snippet

  if (raw === 'new') return canManage.value && !inBin.value ? 'new' : null

  return typeof raw === 'string' && raw !== '' && !inBin.value ? Number(raw) || null : null
})

/** The place a new code starts in: the one whose + was pressed. */
const newPlace = computed(() => {
  const raw = route.query.place
  const declared = places.value.filter((place) => place.declared)

  return declared.find((place) => place.key === raw)?.key ?? declared[0]?.key ?? 'head'
})

/*
 * Open is "a code is chosen", and closing is un-choosing it — so the address, the list's
 * highlight and the drawer on a phone can never disagree about it.
 */
const open = computed({
  get: () => selected.value !== null,
  set: (value) => {
    if (!value) close()
  },
})

const total = computed(() =>
  places.value.reduce((count, place) => count + place.snippets.length, 0),
)

async function load(): Promise<void> {
  loading.value = true

  try {
    if (inBin.value) {
      bin.value = await api.trashed()
    } else {
      const answer = await api.list()

      places.value = answer.data
      environment.value = answer.environment
      everythingPrinted.value = answer.everything_printed
    }
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

watch(view, () => void load())
void load()

/* `replace` and not `push`: going through twenty codes is not twenty steps of history. */
function choose(id: number | 'new', place?: string): void {
  void router.replace({ query: { ...route.query, snippet: String(id), place } })
}

function close(): void {
  const query = { ...route.query }

  delete query.snippet
  delete query.place
  void router.replace({ query })
}

/** A new code was saved: it is a record now, and the list has it. */
function created(row: SnippetRow): void {
  choose(row.id)
  void load()
}

/**
 * A place's list changed under a drag — the order inside it, or a code dropped in from another
 * place. The place a code left is only redrawn: the order of what stayed is the same, and the
 * place it went to writes the move. A failure puts the screen back the way the database has it.
 */
async function arrange(place: SnippetPlace, rows: SnippetRow[]): Promise<void> {
  const before = place.snippets.map((row) => row.id)
  const after = rows.map((row) => row.id)

  place.snippets = rows

  if (after.length < before.length) return
  if (after.length === before.length && after.every((id, index) => id === before[index])) return

  // What the server writes for a code dragged in: its new place. The screen says it already.
  for (const row of rows) row.place = place.key

  try {
    await api.reorder(place.key, after)
  } catch (error) {
    toast.danger(message(error, t('panel.reorder-failed')))
    await load()
  }
}

/** On or off the site at once — the switch writes `enabled` and nothing else. */
async function toggle(row: SnippetRow, value: unknown): Promise<void> {
  const enabled = value === true
  const was = row.enabled

  row.enabled = enabled

  try {
    const detail = await api.save(row.id, { enabled })

    Object.assign(row, detail.snippet)
    toast.success(enabled ? t('panel.switched-on') : t('panel.switched-off'))
  } catch (error) {
    row.enabled = was
    toast.danger(message(error, t('panel.switch-failed')))
  }
}

function actionsFor(row: SnippetRow): RowAction[] {
  if (!canManage.value) return []

  if (inBin.value) {
    return [
      {
        key: 'restore',
        icon: 'refresh',
        label: t('panel.restore'),
        run: () => void restore(row),
      },
      {
        key: 'purge',
        icon: 'trash',
        label: t('panel.purge'),
        danger: true,
        run: () => void purge(row),
      },
    ]
  }

  return [
    {
      key: 'delete',
      icon: 'trash',
      label: t('panel.delete'),
      danger: true,
      run: () => void remove(row),
    },
  ]
}

async function remove(row: SnippetRow): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-title', { title: nameOf(row) }),
    message: t('panel.delete-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(t('panel.deleted'))
    removed(row.id)
  } catch (error) {
    toast.danger(message(error))
  }
}

/** Gone from the list; and if it was the open one, the form goes with it. */
function removed(id: number): void {
  if (selected.value === id) close()

  void load()
}

async function restore(row: SnippetRow): Promise<void> {
  try {
    await api.restore(row.id)
    toast.success(t('panel.restored'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await load()
  }
}

async function purge(row: SnippetRow): Promise<void> {
  const agreed = await confirm({
    title: t('panel.purge-title', { title: nameOf(row) }),
    message: t('panel.purge-text'),
    confirmText: t('panel.purge'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.purge(row.id)
    toast.success(t('panel.purged'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await load()
  }
}

function nameOf(row: SnippetRow): string {
  return row.name.trim() === '' ? t('panel.untitled') : row.name
}

/* The label the config gave a place, or the module's word for a built-in one. */
function placeLabel(place: SnippetPlace): string {
  return place.label !== '' ? place.label : place.key
}
</script>

<template>
  <wx-list-screen v-model:view="view" :title="title" :views="views" :card="false">
    <wx-card class="wx-snippets" padding="none">
      <wx-list-detail
        v-model:open="open"
        class="wx-snippets__panes"
        :list-width="400"
        :detail-min="480"
        :detail-label="t('module.title')"
      >
        <template #list>
          <!-- Outside production almost nothing is printed: said first, before any list. -->
          <wx-alert
            v-if="!inBin && !everythingPrinted"
            class="wx-snippets__note"
            type="warning"
            :description="t('panel.environment', { environment })"
          />

          <wx-skeleton
            v-if="loading && (inBin ? bin.length === 0 : places.length === 0)"
            class="wx-snippets__loading"
            :rows="6"
          />

          <!-- The bin: one flat list, the last thing thrown away first. -->
          <template v-else-if="inBin">
            <wx-empty v-if="bin.length === 0" :title="t('panel.empty-bin')" />

            <wx-sortable-list
              v-else
              v-model="bin"
              class="wx-snippets__list"
              plain
              item-key="id"
              handle="row"
              disabled
              :item-label="(item: SnippetRow) => nameOf(item)"
            >
              <template #default="{ item }">
                <div class="wx-snippets-row">
                  <span class="wx-snippets-row__name">{{ nameOf(item) }}</span>
                  <span class="wx-snippets-row__facts">
                    <wx-badge size="sm" round>{{ item.place }}</wx-badge>
                    <span class="wx-snippets-row__muted">{{ t('panel.deleted-at') }}</span>
                    <wx-date :value="item.deleted_at" compact />
                  </span>
                </div>
              </template>

              <template #actions="{ item }">
                <wx-row-menu :actions="actionsFor(item)" :label="nameOf(item)" />
              </template>
            </wx-sortable-list>
          </template>

          <template v-else>
            <section
              v-for="place in places"
              :key="place.key"
              class="wx-snippets-place"
              :data-place="place.key"
              :class="{ 'is-undeclared': !place.declared }"
            >
              <header class="wx-snippets-place__head">
                <div class="wx-snippets-place__title">
                  <span class="wx-snippets-place__label">{{ placeLabel(place) }}</span>
                  <!-- A place nobody declares has no words but its key: said once. -->
                  <code v-if="placeLabel(place) !== place.key" class="wx-snippets-place__key">
                    {{ place.key }}
                  </code>

                  <wx-badge v-if="!place.declared" type="warning" dot size="sm">
                    {{ t('panel.undeclared') }}
                  </wx-badge>
                  <wx-tooltip
                    v-else-if="place.last_seen === null"
                    :content="t('panel.not-seen-help', { place: place.key })"
                  >
                    <wx-badge type="info" dot size="sm" tabindex="0">
                      {{ t('panel.not-seen') }}
                    </wx-badge>
                  </wx-tooltip>
                </div>

                <wx-button
                  v-if="canManage && place.declared"
                  variant="text"
                  size="sm"
                  :aria-label="t('panel.add', { place: placeLabel(place) })"
                  @click="choose('new', place.key)"
                >
                  <template #icon><wx-icon name="plus" /></template>
                </wx-button>
              </header>

              <wx-alert
                v-if="!place.declared"
                class="wx-snippets-place__alert"
                type="warning"
                :description="t('panel.orphaned')"
              />

              <!-- A grip and not the whole row: the row opens a code, and a row that both
                   opens and drags is one where one of the two happens by accident. -->
              <wx-sortable-list
                :model-value="place.snippets"
                class="wx-snippets__list"
                :class="{ 'is-loading': loading }"
                hoverable
                plain
                size="sm"
                item-key="id"
                :group="place.declared ? DRAG_GROUP : undefined"
                :handle="canManage && place.declared ? 'grip' : 'row'"
                :disabled="!canManage || !place.declared"
                :empty-text="t('panel.empty-place')"
                :item-label="(item: SnippetRow) => nameOf(item)"
                @update:model-value="(rows: SnippetRow[]) => arrange(place, rows)"
              >
                <template #default="{ item }">
                  <button
                    type="button"
                    class="wx-snippets-row"
                    :class="{ 'is-current': selected === item.id, 'is-off': !item.enabled }"
                    @click="choose(item.id)"
                  >
                    <span class="wx-snippets-row__name">{{ nameOf(item) }}</span>

                    <span class="wx-snippets-row__facts">
                      <wx-badge v-if="!item.enabled" dot size="sm">{{ t('panel.off') }}</wx-badge>
                      <wx-badge
                        size="sm"
                        round
                        :type="item.consent === 'necessary' ? undefined : 'primary'"
                      >
                        {{ t(`module.consent.${item.consent}`) }}
                      </wx-badge>
                      <wx-badge v-if="item.on_stage" size="sm" round type="warning">
                        {{ t('panel.on-stage') }}
                      </wx-badge>
                      <wx-badge
                        v-if="silentHere(item, everythingPrinted)"
                        class="wx-snippets-row__silent"
                        size="sm"
                        round
                        :title="t('panel.not-here-help', { environment })"
                      >
                        {{ t('panel.not-here') }}
                      </wx-badge>
                      <span class="wx-snippets-row__muted">{{ coverageOf(item, t) }}</span>
                      <span class="wx-snippets-row__locales">{{ localesOf(item, t) }}</span>
                    </span>
                  </button>
                </template>

                <template #actions="{ item }">
                  <wx-switch
                    v-if="canManage"
                    :model-value="item.enabled"
                    size="sm"
                    :aria-label="t('panel.switch-on', { title: nameOf(item) })"
                    @update:model-value="(value: unknown) => toggle(item, value)"
                  />
                  <wx-row-menu :actions="actionsFor(item)" :label="nameOf(item)" />
                </template>
              </wx-sortable-list>
            </section>

            <!-- What a drag does here; nothing to say before there is something to drag. -->
            <wx-alert
              v-if="canManage && total > 0"
              class="wx-snippets__note"
              type="info"
              :description="t('panel.reorder-hint')"
            />
          </template>
        </template>

        <template #detail="{ inline, back }">
          <snippet-pane
            v-if="selected !== null"
            :id="selected === 'new' ? null : selected"
            :key="String(selected)"
            :place="newPlace"
            :places="places"
            :inline="inline"
            :environment="environment"
            :everything-printed="everythingPrinted"
            @back="back"
            @saved="load"
            @created="created"
            @removed="removed"
          />
        </template>

        <template #empty>
          <wx-empty
            :title="t('panel.choose')"
            :description="canManage && !inBin ? t('panel.choose-help') : undefined"
          />
        </template>
      </wx-list-detail>
    </wx-card>
  </wx-list-screen>
</template>

<style>
/*
 * What scrolls here is the page, the way it does on every other list of the panel. The card's
 * corners are the screen's, and the panes paint their own background to the edge: `clip`, not
 * `hidden`, which would make this a scroll container and pin everything sticky inside to a box
 * that never moves (CLAUDE.md §4).
 */
.wx-snippets > .wx-card__body {
  display: flex;
  flex-direction: column;
  overflow: clip;
  border-radius: inherit;
}
</style>

<style scoped>
.wx-snippets__loading {
  padding: var(--wx-space-12);
}

.wx-snippets__note {
  margin: var(--wx-space-8);
}

.wx-snippets-place {
  padding-block: var(--wx-space-8) var(--wx-space-4);
  border-bottom: 1px solid var(--wx-border-muted);
}

.wx-snippets-place__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
  min-height: 32px;
  padding-inline: var(--wx-space-12) var(--wx-space-8);
}

.wx-snippets-place__title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-8);
  min-width: 0;
}

.wx-snippets-place__label {
  font-weight: var(--wx-font-weight-semibold);
  overflow-wrap: anywhere;
}

.wx-snippets-place__key {
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
}

.wx-snippets-place__alert {
  margin: var(--wx-space-4) var(--wx-space-8);
}

.wx-snippets__list {
  padding-inline: var(--wx-space-8);
}

.wx-snippets__list.is-loading {
  opacity: 0.6;
}

.wx-snippets__list :deep(.wx-sortable-list__row) {
  gap: var(--wx-space-4);
  padding-inline: var(--wx-space-4);
}

.wx-snippets__list :deep(.wx-sortable-list__actions) {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
}

.wx-snippets-row {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: var(--wx-space-4);
  /* The row is the target: a short name should not leave half the line dead — and a button,
     unlike a link, is only as wide as what is in it until it is told otherwise. */
  flex: 1 1 auto;
  width: 100%;
  min-width: 0;
  padding-block: var(--wx-space-8);
  padding-inline: 0;
  border: 0;
  background: none;
  font: inherit;
  color: inherit;
  text-align: start;
}

button.wx-snippets-row {
  cursor: pointer;
}

.wx-snippets-row.is-current .wx-snippets-row__name {
  color: var(--wx-color-primary);
}

.wx-snippets-row.is-off .wx-snippets-row__name {
  color: var(--wx-text-muted);
}

.wx-snippets-row__name {
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-snippets-row__facts {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-8);
  min-width: 0;
}

.wx-snippets-row__muted {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

.wx-snippets-row__locales {
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
}
</style>
