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
import { createPaymentsApi } from './api'
import { usePaymentsWords } from './i18n'
import MethodPane from './MethodPane.vue'
import type { MethodRow, MethodType } from './types'

/**
 * The methods (spec §13.2): every one in the order the checkout lists them — dragged by the grip —
 * on the left, the one being written on the right. A new method starts from its type: the shop's
 * own two, or a gateway's.
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
    ? [{ key: 'new', label: t('methods.new'), primary: true, run: () => startChoosing() }]
    : [],
)

/* The type and the gateway under the method's name, unless the name already says it. */
function line(row: MethodRow): string {
  const echo = row.type_title.trim().toLowerCase() === row.title.trim().toLowerCase()

  return [echo ? '' : row.type_title, row.gateway_title ?? '']
    .filter((part, index, all) => part !== '' && all.indexOf(part) === index)
    .join(' · ')
}

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

const paneKey = computed(() => String(route.query.method ?? ''))
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
                <button
                  type="button"
                  class="wx-payments-method"
                  :class="{ 'is-current': selected?.id === item.id }"
                  @click="choose(String(item.id))"
                >
                  <span class="wx-payments-method__title">{{ item.title }}</span>
                  <span v-if="line(item)" class="wx-payments-method__line">{{ line(item) }}</span>
                  <span class="wx-payments-method__facts">
                    <wx-badge v-if="!item.enabled" dot size="sm">{{ t('methods.off') }}</wx-badge>
                    <wx-badge v-else type="success" dot size="sm">{{ t('methods.on') }}</wx-badge>
                    <wx-badge v-if="item.mode" size="sm">{{
                      item.mode === 'live' ? t('methods.live') : t('methods.test')
                    }}</wx-badge>
                    <wx-badge
                      v-if="item.credentials"
                      :type="
                        item.credentials === 'set' || item.credentials === 'environment'
                          ? 'default'
                          : 'warning'
                      "
                      size="sm"
                      >{{ t(`methods.keys.${item.credentials}`) }}</wx-badge
                    >
                    <wx-badge v-if="item.hold" size="sm">{{ t('methods.hold') }}</wx-badge>
                    <wx-badge
                      v-if="
                        item.warnings.includes('type_missing') ||
                        item.warnings.includes('gateway_missing')
                      "
                      type="danger"
                      size="sm"
                      >{{ t(`methods.warnings.${item.warnings[0]}`) }}</wx-badge
                    >
                  </span>
                </button>
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
.wx-payments-methods__list {
  padding: var(--wx-space-8);
}

.wx-payments-methods__list :deep(.wx-sortable-list__row) {
  gap: var(--wx-space-4);
  padding-inline: var(--wx-space-4);
}

.wx-payments-methods__note {
  margin: var(--wx-space-8);
}

.wx-payments-method {
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

.wx-payments-method__title {
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-payments-method.is-current .wx-payments-method__title {
  color: var(--wx-color-primary);
}

.wx-payments-method__line {
  overflow: hidden;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-payments-method__facts {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  font-size: var(--wx-font-size-sm);
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
