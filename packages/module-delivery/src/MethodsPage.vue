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
  WxIcon,
  WxListDetail,
  WxSkeleton,
  WxSortableList,
} from '@webx-ui/core'
import { useAdmin, useErrorText, WxListScreen } from '@webx-ui/module-admin'
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

function start(type: MethodType): void {
  choosing.value = false
  choose(`new:${type.key}`)
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
  <wx-list-screen :title="title" :card="false">
    <wx-card class="wx-delivery-methods" padding="none">
      <wx-list-detail v-model:open="open" :list-width="380" :detail-min="460" :detail-label="title">
        <template #list>
          <button
            v-if="canManage"
            type="button"
            class="wx-delivery-new"
            data-test="new-method"
            @click="choosing = true"
          >
            <wx-icon name="plus" size="sm" />
            {{ t('method.new') }}
          </button>

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
                  <span class="wx-delivery-method__line">
                    {{ item.type_title }}
                    <template v-if="item.carrier !== 'store' && item.carrier_title">
                      · {{ item.carrier_title }}</template
                    >
                  </span>
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

    <wx-dialog v-model:open="choosing" :title="t('method.choose-type')" :width="480">
      <div class="wx-delivery-types">
        <section v-for="group in grouped" :key="group.key" class="wx-delivery-types__group">
          <h4 class="wx-delivery-types__carrier">{{ group.title }}</h4>
          <wx-button
            v-for="type in group.types"
            :key="type.key"
            variant="outline"
            class="wx-delivery-types__type"
            :data-test="`type-${type.key}`"
            @click="start(type)"
          >
            {{ type.title }}
          </wx-button>
        </section>
      </div>
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
.wx-delivery-new {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  width: calc(100% - 2 * var(--wx-space-8));
  margin: var(--wx-space-8) var(--wx-space-8) var(--wx-space-4);
  padding: var(--wx-space-8);
  border: 1px dashed var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  background: none;
  font: inherit;
  color: var(--wx-color-primary);
  text-align: start;
  cursor: pointer;
}

.wx-delivery-new:hover {
  background: var(--wx-bg-subtle);
}

.wx-delivery-methods__ghost {
  padding: var(--wx-space-12);
}

.wx-delivery-methods__list {
  padding-inline: var(--wx-space-8);
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
  gap: var(--wx-space-16);
}

.wx-delivery-types__group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-delivery-types__carrier {
  margin: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
}

.wx-delivery-types__type {
  justify-content: flex-start;
}
</style>
