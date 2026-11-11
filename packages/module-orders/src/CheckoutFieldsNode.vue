<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  createModal,
  toast,
  WxBadge,
  WxButton,
  WxSkeleton,
  WxSortableList,
  WxSwitch,
  WxText,
} from '@webx-ui/core'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxRowMenu,
  type RowAction,
} from '@webx-ui/module-admin'
import CheckoutFieldDialog from './CheckoutFieldDialog.vue'
import ConsequencesDialog from './ConsequencesDialog.vue'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type {
  CheckoutField,
  CheckoutFieldConsequences,
  CheckoutFieldPlace,
  CheckoutFieldsData,
} from './types'

/**
 * `wx-orders-checkout-fields` — the extra fields of the checkout on the tab «Checkout» of
 * `orders.settings` (WEBX_UI_ORDER_FIELDS.md §7.1). It writes through its own calls, not the
 * button of the tab: a field is a row of a table, not a setting, and is there on a site without
 * the settings module too.
 *
 * Grouped by place, in the order a buyer meets them. A field is dragged inside its place, and
 * the fields shown behind a checkbox inside it — that is where the site prints them (§4.5); the
 * place changes in the dialog. A field whose place the site no longer has is listed apart,
 * marked: the buyer is not asked it until it gets a place.
 */
defineOptions({ name: 'WxOrdersCheckoutFields' })

interface Row extends CheckoutField {
  dependents: CheckoutField[]
}

interface Group {
  place: CheckoutFieldPlace
  rows: Row[]
}

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const board = ref<CheckoutFieldsData | null>(null)
const groups = ref<Group[]>([])
const lost = ref<CheckoutField[]>([])
const loading = ref(true)

const canManage = computed(() => admin.can('orders.checkout.settings'))
const full = computed(() =>
  board.value ? board.value.fields.length >= board.value.limits.fields : true,
)

const fieldDialog = createModal<
  CheckoutFieldsData,
  {
    field: CheckoutField | null
    fields: CheckoutField[]
    places: CheckoutFieldPlace[]
    limits: CheckoutFieldsData['limits']
    place?: string
  }
>(CheckoutFieldDialog)
const consequences = createModal<
  CheckoutFieldsData,
  {
    title: string
    confirmText: string
    tone?: 'primary' | 'danger'
    preview: () => Promise<CheckoutFieldConsequences>
    apply: () => Promise<CheckoutFieldsData>
  }
>(ConsequencesDialog)

/** Copies, so that a drag rewrites these and not the answer a failed one goes back to. */
function take(answer: CheckoutFieldsData): void {
  board.value = answer

  const byKey = new Map(answer.fields.map((field) => [field.key, field]))
  const behind = (key: string) => answer.fields.filter((field) => field.parent === key)

  groups.value = answer.places.map((place) => ({
    place,
    rows: answer.fields
      .filter(
        (field) => field.place === place.key && (field.parent === null || !byKey.has(field.parent)),
      )
      .map((field) => ({ ...field, dependents: behind(field.key) })),
  }))
  lost.value = answer.fields.filter(
    (field) => !field.place_known && (field.parent === null || !byKey.has(field.parent)),
  )
}

async function load(): Promise<void> {
  try {
    take(await api.checkoutFields())
  } catch (failure) {
    toast.danger(message(failure))
  } finally {
    loading.value = false
  }
}

onMounted(() => void load())

/* ------------------------------------------------------------------------- dragging ----- */

/** Every key once: the places in their order, a checkbox's fields right after it. */
function arrangement(): string[] {
  const keys: string[] = []
  const add = (row: CheckoutField & { dependents?: CheckoutField[] }) => {
    keys.push(row.key)
    for (const dependent of row.dependents ?? []) keys.push(dependent.key)
  }

  for (const group of groups.value) for (const row of group.rows) add(row)
  for (const field of lost.value) {
    add({ ...field, dependents: board.value?.fields.filter((one) => one.parent === field.key) })
  }

  return keys
}

async function dropped(): Promise<void> {
  try {
    take(await api.arrangeCheckoutFields(arrangement()))
  } catch (failure) {
    const errors = (failure as { errors?: Record<string, string[]> }).errors

    toast.danger(errors?.keys?.[0] ?? message(failure, t('panel.checkout-fields.reorder-failed')))
    await load()
  }
}

/* -------------------------------------------------------------------------- dialogs ----- */

async function edit(field: CheckoutField | null, place?: string): Promise<void> {
  if (!board.value) return

  const answer = await fieldDialog({
    field,
    fields: board.value.fields,
    places: board.value.places,
    limits: board.value.limits,
    place,
  })

  if (answer) {
    take(answer)
    toast.success(t('panel.saved'))
  }
}

async function remove(field: CheckoutField): Promise<void> {
  const answer = await consequences({
    title: t('panel.checkout-fields.delete-title', { field: field.name }),
    confirmText: t('panel.checkout-fields.delete'),
    tone: 'danger',
    preview: () => api.previewRemoveCheckoutField(field.id),
    apply: () => api.removeCheckoutField(field.id),
  })

  if (answer) {
    take(answer)
    toast.success(t('panel.checkout-fields.deleted'))
  }
}

/** Switched on or off from the row: what it does is plain, and the row says it. */
async function toggle(field: CheckoutField, active: unknown): Promise<void> {
  try {
    take(await api.saveCheckoutField(field.id, { active: active === true }))
  } catch (failure) {
    toast.danger(message(failure))
    await load()
  }
}

/* ---------------------------------------------------------------------------- words ----- */

const typeName = (field: CheckoutField) => t(`panel.checkout-fields.types.${field.type}`)

const requiredWord = (field: CheckoutField) =>
  t(
    field.type === 'checkbox'
      ? 'panel.checkout-fields.must-tick'
      : 'panel.checkout-fields.required',
  )

function actions(field: CheckoutField): RowAction[] {
  if (!canManage.value) return []

  return [
    {
      key: 'edit',
      icon: 'edit',
      label: t('panel.checkout-fields.edit'),
      run: () => void edit(field),
    },
    {
      key: 'delete',
      icon: 'trash',
      label: t('panel.checkout-fields.delete'),
      danger: true,
      run: () => void remove(field),
    },
  ]
}
</script>

<template>
  <div class="wx-orders-checkout-fields">
    <wx-text size="sm" tone="muted">{{ t('panel.checkout-fields.help') }}</wx-text>

    <wx-skeleton v-if="loading" :rows="4" />

    <template v-else-if="board">
      <wx-sortable-list
        v-for="group in groups"
        :key="group.place.key"
        v-model="group.rows"
        :title="group.place.label"
        item-key="key"
        item-label="name"
        size="sm"
        :drag-label="t('panel.checkout-fields.reorder')"
        :disabled="!canManage"
        :empty-text="t('panel.checkout-fields.empty')"
        :aria-label="group.place.label"
        @update:model-value="dropped"
      >
        <template v-if="canManage && !full" #extra>
          <wx-button
            size="sm"
            variant="text"
            icon="plus"
            :aria-label="`${t('panel.checkout-fields.add')}: ${group.place.label}`"
            @click="edit(null, group.place.key)"
          >
            {{ t('panel.checkout-fields.add') }}
          </wx-button>
        </template>

        <!-- The switch and the menu stand in the row's own first line, not in the list's column
             of actions: the fields behind a checkbox then take the row's whole width, which on a
             phone is what leaves their names readable. -->
        <template #default="{ item }">
          <div class="wx-orders-checkout-fields__row" :class="{ 'is-off': !item.active }">
            <div class="wx-orders-checkout-fields__line">
              <div class="wx-orders-checkout-fields__words">
                <wx-text weight="medium" truncate>{{ item.name }}</wx-text>
                <wx-text size="sm" tone="muted" class="wx-orders-checkout-fields__meta">
                  <span
                    ><code>{{ item.key }}</code></span
                  >
                  <span>{{ typeName(item) }}</span>
                  <span v-if="item.required">{{ requiredWord(item) }}</span>
                  <span v-if="!item.active">{{ t('panel.checkout-fields.off') }}</span>
                </wx-text>
              </div>
              <div class="wx-orders-checkout-fields__controls">
                <wx-switch
                  :model-value="item.active"
                  size="sm"
                  :disabled="!canManage"
                  :aria-label="`${t('panel.checkout-fields.active')}: ${item.name}`"
                  @update:model-value="(on: unknown) => toggle(item, on)"
                />
                <wx-row-menu :actions="actions(item)" :label="item.name" />
              </div>
            </div>

            <wx-sortable-list
              v-if="item.dependents.length > 0"
              v-model="item.dependents"
              class="wx-orders-checkout-fields__behind"
              item-key="key"
              item-label="name"
              size="sm"
              :title="t('panel.checkout-fields.when', { field: item.name })"
              :drag-label="t('panel.checkout-fields.reorder')"
              :disabled="!canManage"
              :aria-label="t('panel.checkout-fields.when', { field: item.name })"
              @update:model-value="dropped"
            >
              <template #default="{ item: dependent }">
                <div
                  class="wx-orders-checkout-fields__line"
                  :class="{ 'is-off': !dependent.active }"
                >
                  <div class="wx-orders-checkout-fields__words">
                    <wx-text weight="medium" truncate>{{ dependent.name }}</wx-text>
                    <wx-text size="sm" tone="muted" class="wx-orders-checkout-fields__meta">
                      <span
                        ><code>{{ dependent.key }}</code></span
                      >
                      <span>{{ typeName(dependent) }}</span>
                      <span v-if="dependent.required">{{ requiredWord(dependent) }}</span>
                      <span v-if="!dependent.active">{{ t('panel.checkout-fields.off') }}</span>
                    </wx-text>
                  </div>
                  <div class="wx-orders-checkout-fields__controls">
                    <wx-switch
                      :model-value="dependent.active"
                      size="sm"
                      :disabled="!canManage"
                      :aria-label="`${t('panel.checkout-fields.active')}: ${dependent.name}`"
                      @update:model-value="(on: unknown) => toggle(dependent, on)"
                    />
                    <wx-row-menu :actions="actions(dependent)" :label="dependent.name" />
                  </div>
                </div>
              </template>
            </wx-sortable-list>
          </div>
        </template>
      </wx-sortable-list>

      <div v-if="lost.length > 0" class="wx-orders-checkout-fields__lost">
        <wx-text size="sm" weight="medium">{{ t('panel.checkout-fields.unknown-place') }}</wx-text>
        <wx-text size="sm" tone="muted">{{ t('panel.checkout-fields.unknown-help') }}</wx-text>

        <ul class="wx-orders-checkout-fields__list">
          <li v-for="field in lost" :key="field.key" class="wx-orders-checkout-fields__gone">
            <div class="wx-orders-checkout-fields__row">
              <wx-text weight="medium" truncate>{{ field.name }}</wx-text>
              <wx-text size="sm" tone="muted" class="wx-orders-checkout-fields__meta">
                <span
                  ><code>{{ field.key }}</code></span
                >
                <span>{{ typeName(field) }}</span>
                <span>
                  <wx-badge type="warning" size="sm">
                    {{ t('panel.checkout-fields.unknown-place') }}: {{ field.place }}
                  </wx-badge>
                </span>
              </wx-text>
            </div>
            <wx-row-menu :actions="actions(field)" :label="field.name" />
          </li>
        </ul>
      </div>

      <div v-if="canManage" class="wx-orders-checkout-fields__foot">
        <wx-button variant="outline" icon="plus" :disabled="full" @click="edit(null)">
          {{ t('panel.checkout-fields.add') }}
        </wx-button>
        <wx-text v-if="full" size="sm" tone="muted">
          {{
            t('panel.checkout-fields.refusals.too_many', {
              fields: board.limits.fields,
              options: board.limits.options,
            })
          }}
        </wx-text>
      </div>
    </template>
  </div>
</template>

<style scoped>
.wx-orders-checkout-fields {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-orders-checkout-fields__row {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  flex: 1 1 auto;
  min-width: 0;
}

.wx-orders-checkout-fields__line {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-orders-checkout-fields__words {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  flex: 1 1 auto;
  min-width: 0;
}

.wx-orders-checkout-fields__controls {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  flex: none;
}

.is-off > .wx-orders-checkout-fields__words > :first-child,
.is-off > .wx-orders-checkout-fields__line > .wx-orders-checkout-fields__words > :first-child {
  color: var(--wx-text-muted);
}

.wx-orders-checkout-fields__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0 var(--wx-space-8);
}

.wx-orders-checkout-fields__meta > span + span::before {
  content: '·';
  margin-inline-end: var(--wx-space-8);
}

/* The fields behind a checkbox, under it and indented: that is where the site prints them. */
.wx-orders-checkout-fields__behind {
  margin-block-start: var(--wx-space-8);
}

.wx-orders-checkout-fields__lost {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12);
  border: 1px dashed var(--wx-border-default);
  border-radius: var(--wx-radius-md);
}

.wx-orders-checkout-fields__list {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-orders-checkout-fields__gone {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
}

.wx-orders-checkout-fields__foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8) var(--wx-space-12);
}
</style>
