<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  toast,
  WxAlert,
  WxBadge,
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
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import MethodPane from './MethodPane.vue'
import type { MethodRow, MethodType } from './types'

/**
 * The methods (§14.2): every one in the order the checkout lists them — dragged by the grip —
 * on the left, the one being written on the right. A new method starts from its type: the shop's
 * pick-up, its courier, or a carrier's method.
 *
 * The open method is in the address (`?method=` — an id, or `new:<type>`), so a link to one is a
 * link to it in its list.
 */
const context = useAdmin()
const api = createDeliveryApi(context)
const route = useRoute()
const router = useRouter()
const t = useDeliveryWords()
const message = useErrorText()

const rows = ref<MethodRow[]>([])
const types = ref<MethodType[]>([])
const loading = ref(true)
const choosing = ref(false)
const picked = ref<string | null>(null)

const canManage = computed(() => context.can('delivery.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'delivery')?.title ??
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

/* The types by carrier: the shop's own first, as they are the ones a new shop starts with. */
const grouped = computed(() => {
  const groups = new Map<string, { title: string; types: MethodType[] }>()

  for (const type of types.value) {
    const group = groups.get(type.carrier) ?? { title: type.carrier_title, types: [] }

    group.types.push(type)
    groups.set(type.carrier, group)
  }

  return [...groups.entries()]
    .sort(([a], [b]) => (a === 'store' ? -1 : b === 'store' ? 1 : a.localeCompare(b)))
    .map(([key, group]) => ({ key, ...group }))
})

async function load(): Promise<void> {
  loading.value = true

  try {
    const answer = await api.methods()

    rows.value = answer.methods
    types.value = answer.types
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
    ? [{ key: 'new', label: t('method.new'), primary: true, run: () => startChoosing() }]
    : [],
)

/*
 * The carrier under the method's name, unless the type's name already says it: a type named by
 * the panel reads «Carrier A branch», and «Carrier A branch · Carrier A» says it twice.
 */
function line(row: MethodRow): string {
  const carrier = row.carrier !== 'store' ? (row.carrier_title ?? '') : ''

  return carrier === '' || row.type_title.includes(carrier)
    ? row.type_title
    : `${row.type_title} · ${carrier}`
}

function created(method: MethodRow): void {
  choose(String(method.id))
  void load()
}

function removed(): void {
  close()
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

function countries(row: MethodRow): string {
  return row.countries.length === 0 ? t('method.all-countries') : row.countries.join(', ')
}

const paneKey = computed(() => String(route.query.method ?? ''))
</script>

<template>
  <wx-list-screen :title="title" :actions="actions" :card="false">
    <wx-card class="wx-delivery-methods" padding="none">
      <wx-list-detail v-model:open="open" :list-width="380" :detail-min="460" :detail-label="title">
        <template #list>
          <wx-skeleton
            v-if="loading && rows.length === 0"
            class="wx-delivery-methods__ghost"
            :rows="4"
          />

          <wx-empty
            v-else-if="rows.length === 0"
            :title="t('method.empty')"
            :description="t('method.empty-help')"
          />

          <template v-else>
            <wx-sortable-list
              v-model="rows"
              class="wx-delivery-methods__list"
              hoverable
              plain
              item-key="id"
              :handle="canManage ? 'grip' : 'row'"
              :item-label="(item: MethodRow) => item.title"
              :disabled="!canManage"
              @move="reorder"
            >
              <template #default="{ item }">
                <button
                  type="button"
                  class="wx-delivery-method"
                  :class="{ 'is-current': selected?.id === item.id }"
                  @click="choose(String(item.id))"
                >
                  <span class="wx-delivery-method__title">{{ item.title }}</span>
                  <span class="wx-delivery-method__line">{{ line(item) }}</span>
                  <span class="wx-delivery-method__facts">
                    <span class="wx-delivery-method__price">{{
                      item.price_text ?? t('method.free')
                    }}</span>
                    <span class="wx-delivery-method__countries">{{ countries(item) }}</span>
                    <wx-badge v-if="!item.enabled" dot size="sm">{{ t('method.off') }}</wx-badge>
                    <wx-badge v-if="item.missing" type="danger" size="sm">{{
                      t('method.missing-type')
                    }}</wx-badge>
                  </span>
                </button>
              </template>
            </wx-sortable-list>

            <wx-alert
              v-if="canManage && rows.length > 1"
              class="wx-delivery-methods__note"
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
            @back="back"
            @saved="load"
            @created="created"
            @removed="removed"
          />
        </template>

        <template #empty>
          <wx-empty
            :title="t('method.choose')"
            :description="canManage ? t('method.choose-help') : undefined"
          />
        </template>
      </wx-list-detail>
    </wx-card>

    <!--
      A list of choices and not a wall of buttons: one name and one line of what it is per type,
      under the carrier it belongs to — the shape «New block» asks its kind in (module-blocks).
    -->
    <wx-dialog v-model:open="choosing" :title="t('method.choose-type')" :width="520">
      <div class="wx-delivery-types">
        <wx-form-item v-for="group in grouped" :key="group.key" :label="group.title">
          <wx-radio-group v-model="picked" class="wx-delivery-types__list">
            <wx-radio
              v-for="type in group.types"
              :key="type.key"
              :value="type.key"
              :data-test="`type-${type.key}`"
              @dblclick="start"
            >
              <span class="wx-delivery-types__title">{{ type.title }}</span>
              <span v-if="type.description" class="wx-delivery-types__help">{{
                type.description
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
.wx-delivery-methods > .wx-card__body {
  display: flex;
  flex-direction: column;
  overflow: clip;
  border-radius: inherit;
}
</style>

<style scoped>
.wx-delivery-methods__ghost {
  padding: var(--wx-space-12);
}

/* Nothing stands above the list in its card, so it keeps off the top edge too (as Menu's lists do). */
.wx-delivery-methods__list {
  padding: var(--wx-space-8);
}

.wx-delivery-methods__list :deep(.wx-sortable-list__row) {
  gap: var(--wx-space-4);
  padding-inline: var(--wx-space-4);
}

.wx-delivery-methods__note {
  margin: var(--wx-space-8);
}

.wx-delivery-method {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-2);
  width: 100%;
  min-width: 0;
  padding-block: var(--wx-space-8);
  padding-inline: 0;
  border: 0;
  background: none;
  font: inherit;
  color: inherit;
  text-align: start;
  cursor: pointer;
}

.wx-delivery-method__title {
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-delivery-method.is-current .wx-delivery-method__title {
  color: var(--wx-color-primary);
}

.wx-delivery-method__line {
  overflow: hidden;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-delivery-method__facts {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  font-size: var(--wx-font-size-sm);
}

.wx-delivery-method__price {
  font-variant-numeric: tabular-nums;
}

.wx-delivery-method__countries {
  color: var(--wx-text-muted);
}

.wx-delivery-types {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-delivery-types__list {
  gap: var(--wx-space-10);
}

/* The circle beside the name, not beside the middle of name and line. */
.wx-delivery-types__list :deep(.wx-radio) {
  align-items: flex-start;
}

.wx-delivery-types__title {
  display: block;
  font-weight: var(--wx-font-weight-medium);
}

.wx-delivery-types__help {
  display: block;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}
</style>
