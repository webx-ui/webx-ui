<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  toast,
  WxAlert,
  WxButton,
  WxCard,
  WxDialog,
  WxEmpty,
  WxFormItem,
  WxListDetail,
  WxRadio,
  WxRadioGroup,
  WxSkeleton,
  WxSortableList,
  WxSpace,
} from '@webx-ui/core'
import { useAdmin, useErrorText, WxListScreen, type ScreenAction } from '@webx-ui/module-admin'
import { createPaymentsApi } from './api'
import GroupDialog from './GroupDialog.vue'
import GroupPictogram from './GroupPictogram.vue'
import { usePaymentsWords } from './i18n'
import MethodLine from './MethodLine.vue'
import MethodPane from './MethodPane.vue'
import type { MethodGroup, MethodRow, MethodType, PaymentMark, Pictogram } from './types'

/** A group of the list with the methods standing in it, in their order. */
type GroupList = MethodGroup & { methods: MethodRow[] }

/**
 * The methods (spec §13.2): every one in the order the checkout lists them — dragged by the grip —
 * on the left, the one being written on the right. A new method starts from its type: the shop's
 * own two, or a gateway's.
 *
 * On a site with groups (groups spec §7) the left column is the groups in their order, each with
 * its methods: one list inside another, a method dragged between groups by the shared `group`. The
 * groups are the config's, so there is no «Add» or «Delete» for them, only «Edit». A site whose
 * config has none keeps the one flat list.
 *
 * The open method is in the address (`?method=` — an id, or `new:<type>`), so a link to one is a
 * link to it in its list.
 */
const context = useAdmin()
const api = createPaymentsApi(context)
const route = useRoute()
const router = useRouter()
const t = usePaymentsWords()
const message = useErrorText()

const rows = ref<MethodRow[]>([])
const types = ref<MethodType[]>([])
const groups = ref<GroupList[]>([])
const marks = ref<Record<string, PaymentMark>>({})
const pictograms = ref<Pictogram[]>([])
const editing = ref<MethodGroup | null>(null)
const editingOpen = ref(false)
const loading = ref(true)
const choosing = ref(false)
const picked = ref<string | null>(null)

const canManage = computed(() => context.can('payments.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'payments')?.title ??
    t('sections.methods'),
)

/** The open method: an id, or a new one of a type, or nothing. */
const selected = computed<{ id: number | null; type: string | null } | null>(() => {
  const raw = route.query.method

  if (typeof raw !== 'string' || raw === '') return null
  if (raw.startsWith('new:')) return canManage.value ? { id: null, type: raw.slice(4) } : null

  const id = Number(raw)

  return Number.isInteger(id) && id > 0 ? { id, type: null } : null
})

const open = computed({
  get: () => selected.value !== null,
  set: (value) => {
    if (!value) close()
  },
})

/* The types by gateway: the shop's own first, as they are the ones a new shop starts with. */
const grouped = computed(() => {
  const groups = new Map<string, { title: string; types: MethodType[] }>()

  for (const type of types.value) {
    const key = type.gateway ?? 'store'
    const group = groups.get(key) ?? {
      title: type.gateway_title ?? t('methods.offline'),
      types: [],
    }

    group.types.push(type)
    groups.set(key, group)
  }

  return [...groups.entries()]
    .sort(([a], [b]) => (a === 'store' ? -1 : b === 'store' ? 1 : a.localeCompare(b)))
    .map(([key, group]) => ({ key, ...group }))
})

async function load(): Promise<void> {
  loading.value = true

  try {
    const [answer, site] = await Promise.all([api.methods(), api.groups()])

    rows.value = answer.methods
    types.value = answer.types
    marks.value = answer.marks
    pictograms.value = site.pictograms
    groups.value = site.groups.map((group, index) => ({
      ...group,
      // A method with no group of the site's stands in the first, where it can still be reached.
      methods: answer.methods.filter((row) =>
        site.groups.some((one) => one.key === row.group) ? row.group === group.key : index === 0,
      ),
    }))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

void load()

function choose(value: string): void {
  void router.replace({ query: { ...route.query, method: value } })
}

function close(): void {
  const query = { ...route.query }

  delete query.method
  void router.replace({ query })
}

/* «Add a method» asks the type first: the form after it depends on it. */
function startChoosing(): void {
  picked.value = types.value[0]?.key ?? null
  choosing.value = true
}

function start(): void {
  if (picked.value === null) return

  choosing.value = false
  choose(`new:${picked.value}`)
}

/* The section's one action, in the head like every list's — a word, no icon (§18.3). */
const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [{ key: 'new', label: t('methods.new'), primary: true, run: () => startChoosing() }]
    : [],
)

function created(method: MethodRow): void {
  choose(String(method.id))
  void load()
}

/* The list is in its new order already; a refusal puts the database's back on screen. */
async function reorder(): Promise<void> {
  try {
    await api.orderMethods(rows.value.map((row) => row.id))
  } catch (error) {
    toast.danger(message(error, t('common.order-failed')))
    await load()
  }
}

/*
 * A row dragged between two lists changes both models in one go, and a drag of a group changes a
 * third: put together in the next tick, so that one move is one request. Read back after it, since
 * a move changes which methods are similar and what the open form says.
 */
let ordering = false

function queueOrder(): void {
  if (ordering) return

  ordering = true
  void nextTick(async () => {
    ordering = false

    try {
      await api.orderGroups(
        groups.value.map((group) => ({
          key: group.key,
          methods: group.methods.map((row) => row.id),
        })),
      )
    } catch (error) {
      toast.danger(message(error, t('common.order-failed')))
    }

    await load()
  })
}

function placeMethods(group: GroupList, methods: MethodRow[]): void {
  group.methods = methods
  queueOrder()
}

function placeGroups(list: GroupList[]): void {
  groups.value = list
  queueOrder()
}

function edit(group: GroupList): void {
  editing.value = group
  editingOpen.value = true
}

function pictogramOf(group: MethodGroup): string | null {
  return pictograms.value.find((one) => one.name === group.pictogram)?.svg ?? null
}

const paneKey = computed(() => String(route.query.method ?? ''))

/* Undefined until the list is there: nothing to follow yet. */
const standing = computed(() => {
  const row = rows.value.find((one) => one.id === selected.value?.id)

  return row && `${row.group} ${row.similar_to.map((one) => one.key).join()}`
})
</script>

<template>
  <wx-list-screen :title="title" :actions="actions" :card="false">
    <wx-card class="wx-payments-methods" padding="none">
      <wx-list-detail v-model:open="open" :list-width="380" :detail-min="460" :detail-label="title">
        <template #list>
          <wx-skeleton
            v-if="loading && rows.length === 0"
            class="wx-payments-methods__ghost"
            :rows="4"
          />

          <wx-empty
            v-else-if="rows.length === 0"
            :title="t('methods.empty')"
            :description="t('methods.empty-help')"
          />

          <template v-else>
            <wx-sortable-list
              v-if="groups.length > 0"
              :model-value="groups"
              class="wx-payments-methods__groups"
              plain
              item-key="key"
              :handle="canManage ? 'grip' : 'row'"
              :item-label="(item: GroupList) => item.title"
              :disabled="!canManage"
              @update:model-value="placeGroups"
            >
              <template #default="{ item: group }">
                <div class="wx-payments-group" :data-group="group.key">
                  <div class="wx-payments-group__head">
                    <group-pictogram v-if="pictogramOf(group)" :svg="pictogramOf(group) ?? ''" />
                    <span class="wx-payments-group__names">
                      <span class="wx-payments-group__title">{{ group.title }}</span>
                      <span v-if="group.description" class="wx-payments-group__description">{{
                        group.description
                      }}</span>
                    </span>
                    <wx-button
                      v-if="canManage"
                      size="sm"
                      variant="text"
                      :data-test="`group-edit-${group.key}`"
                      @click="edit(group)"
                      >{{ t('common.edit') }}</wx-button
                    >
                  </div>

                  <wx-sortable-list
                    :model-value="group.methods"
                    group="payment-methods"
                    hoverable
                    plain
                    item-key="id"
                    :handle="canManage ? 'grip' : 'row'"
                    :item-label="(item: MethodRow) => item.title"
                    :disabled="!canManage"
                    :empty-text="t('groups.drop')"
                    @update:model-value="placeMethods(group, $event)"
                  >
                    <template #default="{ item }">
                      <method-line
                        :method="item"
                        :marks="marks"
                        :current="selected?.id === item.id"
                        @choose="choose(String(item.id))"
                      />
                    </template>
                  </wx-sortable-list>
                </div>
              </template>
            </wx-sortable-list>

            <wx-sortable-list
              v-else
              v-model="rows"
              class="wx-payments-methods__list"
              hoverable
              plain
              item-key="id"
              :handle="canManage ? 'grip' : 'row'"
              :item-label="(item: MethodRow) => item.title"
              :disabled="!canManage"
              @move="reorder"
            >
              <template #default="{ item }">
                <method-line
                  :method="item"
                  :marks="marks"
                  :current="selected?.id === item.id"
                  @choose="choose(String(item.id))"
                />
              </template>
            </wx-sortable-list>

            <wx-alert
              v-if="canManage && rows.length > 1"
              class="wx-payments-methods__note"
              type="info"
              :description="t('common.drag')"
            />
          </template>
        </template>

        <template #detail="{ inline, back }">
          <method-pane
            v-if="selected !== null"
            :id="selected.id"
            :key="paneKey"
            :type="selected.type"
            :inline="inline"
            :standing="standing"
            @back="back"
            @saved="load"
            @created="created"
          />
        </template>

        <template #empty>
          <wx-empty
            :title="t('methods.choose')"
            :description="canManage ? t('methods.choose-help') : undefined"
          />
        </template>
      </wx-list-detail>
    </wx-card>

    <group-dialog
      v-model:open="editingOpen"
      :group="editing"
      :pictograms="pictograms"
      @saved="load"
    />

    <!--
      A list of choices and not a wall of buttons: one name and one line of what it is per type,
      under the carrier it belongs to — the shape «New block» asks its kind in (module-blocks).
    -->
    <wx-dialog v-model:open="choosing" :title="t('methods.choose-type')" :width="520">
      <div class="wx-payments-types">
        <wx-form-item v-for="group in grouped" :key="group.key" :label="group.title">
          <wx-radio-group v-model="picked" class="wx-payments-types__list">
            <wx-radio
              v-for="type in group.types"
              :key="type.key"
              :value="type.key"
              :data-test="`type-${type.key}`"
              @dblclick="start"
            >
              <span class="wx-payments-types__title">{{ type.title }}</span>
              <span class="wx-payments-types__help">{{
                type.kind === 'online' ? t('methods.online') : t('methods.offline')
              }}</span>
            </wx-radio>
          </wx-radio-group>
        </wx-form-item>
      </div>

      <template #footer>
        <wx-space size="sm">
          <wx-button variant="outline" @click="choosing = false">{{
            t('common.cancel')
          }}</wx-button>
          <wx-button
            type="primary"
            :disabled="picked === null"
            data-test="type-start"
            @click="start"
          >
            {{ t('common.add') }}
          </wx-button>
        </wx-space>
      </template>
    </wx-dialog>
  </wx-list-screen>
</template>

<style>
.wx-payments-methods > .wx-card__body {
  display: flex;
  flex-direction: column;
  overflow: clip;
  border-radius: inherit;
}
</style>

<style scoped>
.wx-payments-methods__ghost {
  padding: var(--wx-space-12);
}

/* Nothing stands above the list in its card, so it keeps off the top edge too (as Menu's lists do). */
.wx-payments-methods__list,
.wx-payments-methods__groups {
  padding: var(--wx-space-8);
}

.wx-payments-methods__groups :deep(.wx-sortable-list__row),
.wx-payments-methods__list :deep(.wx-sortable-list__row) {
  gap: var(--wx-space-4);
  padding-inline: var(--wx-space-4);
}

/* A group's row is as tall as its methods: its grip belongs to the head, not to the middle of them. */
.wx-payments-methods__groups > :deep(.wx-sortable-list__body > .wx-sortable-list__row) {
  align-items: flex-start;
}

.wx-payments-methods__groups
  > :deep(.wx-sortable-list__body > .wx-sortable-list__row > .wx-sortable-list__grip) {
  margin-block-start: var(--wx-space-14);
}

.wx-payments-methods__note {
  margin: var(--wx-space-8);
}

.wx-payments-group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
  padding-block: var(--wx-space-8);
}

.wx-payments-group__head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-payments-group__names {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  min-width: 0;
}

.wx-payments-group__title {
  overflow: hidden;
  font-weight: var(--wx-font-weight-semibold);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-payments-group__description {
  overflow: hidden;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-payments-types {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-payments-types__list {
  gap: var(--wx-space-10);
}

/* The circle beside the name, not beside the middle of name and line. */
.wx-payments-types__list :deep(.wx-radio) {
  align-items: flex-start;
}

.wx-payments-types__title {
  display: block;
  font-weight: var(--wx-font-weight-medium);
}

.wx-payments-types__help {
  display: block;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}
</style>
