<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import {
  toast,
  useModal,
  WxAlert,
  WxAutocomplete,
  WxBadge,
  WxButton,
  WxCheckbox,
  WxDialog,
  WxDivider,
  WxFormItem,
  WxInputNumber,
  WxSelect,
  WxSpace,
  WxText,
  WxTextarea,
  type AutocompleteOption,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type {
  ItemsEditInput,
  ItemsEditPreview,
  NewOrderLine,
  OrderCardData,
  OrderDetail,
  OrderLine,
  ProductChoice,
} from './types'

/**
 * «Change the lines» (spec §42.1): the lines of the order with their quantity and «Take out», a
 * product found and added as on «New order», and the service's own dry run of the edit made on
 * every change — each line before → after, the totals, the balance after it and the warnings of
 * §37.7. A set is one line with its parent's quantity, a rule's gift may only go. The reason is
 * asked on saving; a paid order says what the edit does to its payment before it is saved
 * (§42.2). An edit saved meanwhile — another tab — loads the order again, the changes kept.
 */
const props = defineProps<{
  order: OrderCardData
  /** The card as it stands now, when the editor had to read it again. */
  refreshed?: (detail: OrderDetail) => void
}>()

const { resolve, dismiss, open } = useModal<OrderDetail>()

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

/** The order the edit is made against; read again after a refusal `stale`. */
const current = ref<OrderCardData>(props.order)

interface Row {
  line: OrderLine
  qty: number
  out: boolean
}

interface Added {
  uid: number
  choice: ProductChoice
  qty: number
  options: Record<string, string | number | null>
}

/** The lines the order shows, top level: a set's parts go with it. A line taken out with units
 *  returned of it stays in the order, and in the editor, as it is. */
const rows = ref<Row[]>([])

function fill(order: OrderCardData): void {
  rows.value = order.items
    .filter((line) => !line.removed)
    .map((line) => ({ line, qty: line.qty, out: false }))
}

fill(current.value)

const added = ref<Added[]>([])
let uid = 0

const reason = ref('')
const note = ref('')
const notify = ref(true)

const preview = ref<ItemsEditPreview | null>(null)
const counting = ref(false)
const countError = ref('')
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)

const paid = computed(() => current.value.totals.paid !== null)

const chosen = (line: Added): boolean => Object.values(line.options).every((one) => one !== null)

function payload(line: Added): NewOrderLine {
  return {
    type: line.choice.type,
    ref: line.choice.ref,
    qty: line.qty,
    ...(line.choice.options.length > 0
      ? { options: line.options as Record<string, string | number> }
      : {}),
  }
}

/** The edit as the server takes it; a variant not chosen yet is not a line of it. */
const input = computed<ItemsEditInput>(() => {
  const qty: Record<number, number> = {}
  const remove: number[] = []

  for (const row of rows.value) {
    if (row.out) remove.push(row.line.id)
    else if (row.qty !== row.line.qty) qty[row.line.id] = row.qty
  }

  return {
    revision: current.value.items_revision ?? 0,
    add: added.value.filter(chosen).map(payload),
    qty,
    remove,
    reason: reason.value.trim(),
    message: note.value.trim(),
    notify: notify.value,
  }
})

const empty = computed(
  () =>
    input.value.add.length === 0 &&
    input.value.remove.length === 0 &&
    Object.keys(input.value.qty).length === 0,
)

const waiting = computed(() => added.value.some((line) => !chosen(line)))

/* --- finding a product: the route and the words of «New order» --- */

const term = ref('')
const found = ref<ProductChoice[]>([])
const searching = ref(false)
let asked = 0

async function find(search: string): Promise<void> {
  const mine = ++asked

  searching.value = true

  try {
    const answer = await api.findProducts(search.trim(), current.value.locale)

    if (mine === asked) found.value = answer
  } catch (failure) {
    toast.danger(message(failure))
  } finally {
    if (mine === asked) searching.value = false
  }
}

const suggestions = computed<AutocompleteOption[]>(() => {
  const seen = new Set<string>()

  return found.value.map((choice) => {
    let value = `${choice.name} · ${choice.sku ?? `#${choice.ref}`}`

    if (seen.has(value)) value = `${value} (${choice.type}:${choice.ref})`
    seen.add(value)

    return {
      value,
      label: choice.name,
      description: [choice.sku, choice.hint, choice.price_formatted ?? t('panel.new-unavailable')]
        .filter(Boolean)
        .join(' · '),
      disabled: !choice.available,
      choice,
    }
  })
})

function add(option: AutocompleteOption): void {
  const choice = option.choice as ProductChoice

  void nextTick(() => (term.value = ''))

  if (!choice.available) return

  const same =
    choice.options.length === 0
      ? added.value.find(
          (line) => line.choice.type === choice.type && line.choice.ref === choice.ref,
        )
      : undefined

  if (same) {
    same.qty += 1

    return
  }

  added.value.push({
    uid: ++uid,
    choice,
    qty: 1,
    options: Object.fromEntries(choice.options.map((one) => [one.key, null])),
  })
}

function drop(line: Added): void {
  added.value = added.value.filter((one) => one.uid !== line.uid)
}

const optionItems = (option: ProductChoice['options'][number]) =>
  option.values.map((one) => ({ value: one.value, label: one.label }))

/* --- the live count --- */

let counted = 0
let timer: ReturnType<typeof setTimeout> | undefined

async function count(): Promise<void> {
  const mine = ++counted

  if (empty.value) {
    preview.value = null
    errors.value = {}
    countError.value = ''

    return
  }

  counting.value = true

  try {
    const answer = await api.previewItems(current.value.id, input.value)

    // Saved meanwhile in another tab: the count is of an order that is not there any more.
    if (mine === counted && answer.refusal === 'stale') {
      await reload()

      return
    }

    if (mine === counted) {
      preview.value = answer
      errors.value = {}
      countError.value = ''
    }
  } catch (failure) {
    if (mine !== counted) return

    const body = (failure as { body?: { errors?: Record<string, string[]> } }).body

    preview.value = null
    errors.value = body?.errors ?? {}
    countError.value = body?.errors ? '' : message(failure)
  } finally {
    if (mine === counted) counting.value = false
  }
}

watch(
  () => JSON.stringify({ ...input.value, reason: '', message: '', notify: true }),
  () => {
    clearTimeout(timer)
    timer = setTimeout(() => void count(), 250)
  },
)

onBeforeUnmount(() => clearTimeout(timer))

/** What the count says of a line of the order: «before → after» once it changes. */
function changeOf(row: Row) {
  return preview.value?.changes.find((change) => change.item_id === row.line.id) ?? null
}

/** What the count says of a product added: its line «add» — or the line it joined (§37.4). */
function addedChange(line: Added) {
  return (
    preview.value?.changes.find(
      (change) =>
        change.op === 'add' && change.name === line.choice.name && change.qty_to === line.qty,
    ) ?? null
  )
}

/** A refusal or an error about this line: `qty.<id>`, `remove.<n>`. */
function rowError(row: Row): string | undefined {
  const own = (field: string | null) =>
    field === `qty.${row.line.id}` ||
    (field?.startsWith('remove.') && input.value.remove[Number(field.slice(7))] === row.line.id)

  if (preview.value?.refusal && own(preview.value.field)) return preview.value.message ?? undefined

  for (const [field, messages] of Object.entries(errors.value)) {
    if (own(field)) return messages[0]
  }

  return undefined
}

/** An error of the cart about a line added: `add.N.*` — N among the lines sent. */
function addedError(line: Added): string | undefined {
  const index = added.value.filter(chosen).indexOf(line)

  if (index < 0) return undefined

  for (const [field, messages] of Object.entries(errors.value)) {
    if (field === `add.${index}` || field.startsWith(`add.${index}.`)) return messages[0]
  }

  return preview.value?.warnings.find((warning) => warning.line === `add.${index}`)?.message
}

/** What is not about a line: the refusal of the whole edit, an error under no line. */
const general = computed<string[]>(() => {
  const lines: string[] = []

  if (preview.value?.refusal && !preview.value.field?.match(/^(qty|remove)\./)) {
    lines.push(preview.value.message ?? preview.value.refusal)
  }

  for (const [field, messages] of Object.entries(errors.value)) {
    if (!/^(qty|remove|add)\./.test(field) && field !== 'reason') lines.push(...messages)
  }

  if (countError.value) lines.push(countError.value)

  return lines
})

const paidWarning = computed(
  () => preview.value?.warnings.find((warning) => warning.code === 'paid') ?? null,
)

/** §37.7 beside the totals: the payment's has its own plate, a line's is under the line. */
const warnings = computed(() =>
  (preview.value?.warnings ?? []).filter((warning) => warning.code !== 'paid' && !warning.line),
)

const after = computed(() => (preview.value?.refusal === null ? preview.value : null))

const canSave = computed(
  () =>
    !empty.value &&
    !waiting.value &&
    !counting.value &&
    !saving.value &&
    after.value !== null &&
    reason.value.trim() !== '',
)

async function save(): Promise<void> {
  if (!canSave.value) return

  saving.value = true

  try {
    resolve(await api.saveItems(current.value.id, input.value))
  } catch (failure) {
    const body = (failure as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors?.revision) {
      await reload()
    } else if (body?.errors && Object.keys(body.errors).length > 0) {
      errors.value = body.errors
    } else {
      toast.danger(message(failure))
    }
  } finally {
    saving.value = false
  }
}

/**
 * Another edit was saved since the editor opened — another tab, another manager. The order is
 * read again and the lines start from it; what was added and the words typed stay.
 */
async function reload(): Promise<void> {
  toast.warning(t('panel.items-stale'))

  try {
    const detail = await api.get(current.value.id)

    current.value = detail.order
    fill(detail.order)
    props.refreshed?.(detail)
    // The lines start from the order read again; what was added is counted against it.
    if (!empty.value) void count()
  } catch (failure) {
    toast.danger(message(failure))
  }
}

function take(row: Row): void {
  row.out = true
}

function back(row: Row): void {
  row.out = false
  row.qty = row.line.qty
}

/** A set: its parts follow its quantity. */
const isSet = (line: OrderLine) => line.children.some((child) => child.part !== false)
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="t('panel.items-edit-title', { number: current.number ?? '' })"
    :width="1040"
  >
    <div class="wx-orders-edit">
      <wx-text size="sm" tone="muted">{{ t('panel.items-edit-help') }}</wx-text>

      <div class="wx-orders-edit__body">
        <div class="wx-orders-edit__lines">
          <ul class="wx-orders-edit__list">
            <li
              v-for="row in rows"
              :key="row.line.id"
              class="wx-orders-edit__line"
              :class="{ 'is-out': row.out }"
              data-test="edit-line"
            >
              <div class="wx-orders-edit__what">
                <span class="wx-orders-edit__name">
                  <wx-text weight="medium" :class="{ 'is-struck': row.out }">
                    {{ row.line.name }}
                  </wx-text>
                  <wx-badge v-if="row.line.generated" size="sm" type="success" variant="soft">
                    {{ t('panel.items-gift') }}
                  </wx-badge>
                  <wx-badge v-if="isSet(row.line)" size="sm" variant="outline">
                    {{ t('panel.items-set') }}
                  </wx-badge>
                  <wx-badge v-if="row.out" size="sm" type="danger" variant="soft">
                    {{ t('panel.items-removed') }}
                  </wx-badge>
                </span>
                <wx-text v-if="row.line.sku || row.line.options" size="sm" tone="muted">
                  {{ [row.line.sku, row.line.options].filter(Boolean).join(' · ') }}
                </wx-text>
                <wx-text v-if="row.line.children.length > 0" size="sm" tone="muted">
                  {{ row.line.children.map((child) => `${child.name} × ${child.qty}`).join(', ') }}
                </wx-text>
                <wx-text v-if="row.line.generated" size="sm" tone="muted">
                  {{ t('panel.items-gift-help') }}
                </wx-text>
                <wx-text v-else-if="isSet(row.line)" size="sm" tone="muted">
                  {{ t('panel.items-set-help') }}
                </wx-text>
                <wx-text v-if="row.line.refunded_qty > 0" size="sm" tone="warning">
                  {{ t('panel.items-keeps-refunded', { count: row.line.refunded_qty }) }}
                </wx-text>
                <wx-text v-if="rowError(row)" size="sm" tone="danger" data-test="edit-line-error">
                  {{ rowError(row) }}
                </wx-text>
              </div>

              <div class="wx-orders-edit__controls">
                <wx-input-number
                  v-if="!row.line.generated && !row.out"
                  v-model="row.qty"
                  class="wx-orders-edit__qty"
                  size="sm"
                  :min="Math.max(1, row.line.refunded_qty)"
                  :step="1"
                  controls-position="sides"
                  :aria-label="t('panel.new-qty')"
                />
                <wx-text v-else-if="!row.out" size="sm">× {{ row.line.qty }}</wx-text>
                <wx-button
                  v-if="row.out"
                  size="sm"
                  variant="text"
                  icon="rotate-left"
                  data-test="edit-back"
                  @click="back(row)"
                >
                  {{ t('panel.items-back') }}
                </wx-button>
                <wx-button
                  v-else
                  size="sm"
                  variant="text"
                  icon="trash"
                  :aria-label="t('panel.new-remove')"
                  data-test="edit-take"
                  @click="take(row)"
                />
              </div>

              <div class="wx-orders-edit__money">
                <wx-text size="sm" tone="muted">{{ row.line.price }}</wx-text>
                <wx-text v-if="changeOf(row)" weight="medium" data-test="edit-line-total">
                  <s class="wx-orders-edit__was">{{ changeOf(row)?.total_from_formatted }}</s>
                  {{ changeOf(row)?.total_to_formatted }}
                </wx-text>
                <wx-text v-else weight="medium">{{ row.line.total }}</wx-text>
              </div>
            </li>

            <li
              v-for="line in added"
              :key="`n${line.uid}`"
              class="wx-orders-edit__line is-new"
              data-test="edit-added"
            >
              <div class="wx-orders-edit__what">
                <span class="wx-orders-edit__name">
                  <wx-text weight="medium">{{ line.choice.name }}</wx-text>
                  <wx-badge size="sm" type="primary" variant="soft">
                    {{ t('panel.items-added') }}
                  </wx-badge>
                </span>
                <wx-text v-if="line.choice.sku" size="sm" tone="muted">
                  {{ line.choice.sku }}
                </wx-text>
                <wx-text v-if="addedError(line)" size="sm" tone="danger">
                  {{ addedError(line) }}
                </wx-text>
              </div>

              <div class="wx-orders-edit__controls">
                <span
                  v-for="option in line.choice.options"
                  :key="option.key"
                  class="wx-orders-edit__option"
                >
                  <wx-text size="sm" tone="muted" as="span">{{ option.label }}</wx-text>
                  <wx-select
                    v-model="line.options[option.key]"
                    class="wx-orders-edit__select"
                    size="sm"
                    :options="optionItems(option)"
                    :placeholder="t('panel.new-choose')"
                    :aria-label="option.label"
                  />
                </span>
                <wx-input-number
                  v-model="line.qty"
                  class="wx-orders-edit__qty"
                  size="sm"
                  :min="1"
                  :step="1"
                  controls-position="sides"
                  :aria-label="t('panel.new-qty')"
                />
                <wx-button
                  size="sm"
                  variant="text"
                  icon="trash"
                  :aria-label="t('panel.new-remove')"
                  @click="drop(line)"
                />
              </div>

              <div class="wx-orders-edit__money">
                <wx-text size="sm" tone="muted">{{ line.choice.price_formatted }}</wx-text>
                <wx-text v-if="addedChange(line)" weight="medium">
                  {{ addedChange(line)?.total_to_formatted }}
                </wx-text>
              </div>
            </li>
          </ul>

          <wx-autocomplete
            v-model="term"
            :options="suggestions"
            remote
            :loading="searching"
            :placeholder="t('panel.new-find')"
            :aria-label="t('panel.new-find')"
            :empty-text="t('panel.new-find-empty')"
            data-test="edit-find"
            @search="find"
            @open="find(term)"
            @select="add"
          >
            <template #option="{ option }">
              <span class="wx-orders-edit__found">
                <span class="wx-orders-edit__found-name">{{ option.label }}</span>
                <wx-text size="sm" tone="muted" class="wx-orders-edit__found-meta">
                  {{ option.description }}
                </wx-text>
              </span>
            </template>
          </wx-autocomplete>
        </div>

        <aside class="wx-orders-edit__side" :aria-busy="counting" aria-live="polite">
          <wx-text weight="medium">{{ t('panel.items-after') }}</wx-text>

          <wx-text v-if="empty" size="sm" tone="muted">{{ t('panel.items-nothing') }}</wx-text>

          <dl v-else-if="after" class="wx-orders-edit__rows" data-test="edit-totals">
            <dt>{{ t('panel.subtotal') }}</dt>
            <dd>
              <s v-if="after.before.subtotal !== after.after.subtotal" class="wx-orders-edit__was">
                {{ after.before.subtotal_formatted }}
              </s>
              {{ after.after.subtotal_formatted }}
            </dd>
            <template v-for="one in after.adjustments" :key="`a${one.adjustment_id}`">
              <dt>{{ one.label }}</dt>
              <dd>
                <s class="wx-orders-edit__was">{{ one.amount_from_formatted }}</s>
                {{ one.amount_to_formatted }}
              </dd>
            </template>
            <template v-if="after.after.adjustments_total !== 0">
              <dt>{{ t('panel.adjustments') }}</dt>
              <dd>{{ after.after.adjustments_total_formatted }}</dd>
            </template>
            <dt class="is-strong">{{ t('panel.total') }}</dt>
            <dd class="is-strong">
              <s class="wx-orders-edit__was">{{ after.before.total_formatted }}</s>
              {{ after.after.total_formatted }}
            </dd>
            <template v-if="after.after.tenders_total !== 0">
              <dt>{{ t('panel.tenders') }}</dt>
              <dd>−{{ after.after.tenders_total_formatted }}</dd>
              <dt class="is-strong">{{ t('panel.payable') }}</dt>
              <dd class="is-strong">{{ after.after.payable_formatted }}</dd>
            </template>
          </dl>

          <wx-text v-else-if="counting" size="sm" tone="muted">…</wx-text>

          <wx-alert
            v-for="(line, index) in general"
            :key="`g${index}`"
            type="danger"
            :description="line"
            data-test="edit-refusal"
          />
          <wx-alert
            v-for="(warning, index) in warnings"
            :key="`w${index}`"
            type="warning"
            :description="warning.message"
          />
        </aside>
      </div>

      <wx-divider />

      <div class="wx-orders-edit__words">
        <wx-form-item
          :label="t('panel.items-reason')"
          :help="t('panel.items-reason-help')"
          :error="errors.reason?.[0]"
          required
        >
          <wx-textarea v-model="reason" :rows="2" :maxlength="500" data-test="edit-reason" />
        </wx-form-item>

        <wx-form-item :label="t('panel.items-message')" :help="t('panel.items-message-help')">
          <wx-textarea v-model="note" :rows="2" :maxlength="1000" />
        </wx-form-item>
      </div>

      <wx-checkbox v-model="notify">{{ t('panel.items-notify') }}</wx-checkbox>

      <!-- §42.2: before the save, what the edit does to the payment of a paid order. -->
      <wx-alert
        v-if="paidWarning"
        :type="(after?.balance.over ?? 0) > 0 ? 'danger' : 'warning'"
        :description="paidWarning.message"
        data-test="edit-paid"
      />
    </div>

    <template #footer>
      <wx-space size="sm" wrap>
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.dismiss') }}</wx-button>
        <wx-button
          type="primary"
          :loading="saving"
          :disabled="!canSave"
          data-test="edit-save"
          @click="save"
        >
          {{ t(paid ? 'panel.items-save-paid' : 'panel.items-save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-orders-edit {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  container-type: inline-size;
}

.wx-orders-edit__body {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--wx-space-16);
}

@container (min-width: 760px) {
  .wx-orders-edit__body {
    grid-template-columns: minmax(0, 1fr) 280px;
  }
}

.wx-orders-edit__lines {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-orders-edit__list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-orders-edit__line {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  grid-template-areas:
    'what money'
    'controls controls';
  align-items: start;
  gap: var(--wx-space-8) var(--wx-space-12);
  padding-block: var(--wx-space-10);
  border-top: 1px solid var(--wx-border-muted);
}

.wx-orders-edit__line:first-child {
  padding-top: 0;
  border-top: 0;
}

@container (min-width: 560px) {
  .wx-orders-edit__line {
    grid-template-columns: minmax(0, 1fr) auto auto;
    grid-template-areas: 'what controls money';
  }
}

.wx-orders-edit__what {
  display: flex;
  grid-area: what;
  flex-direction: column;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-orders-edit__name {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
  overflow-wrap: anywhere;
}

.wx-orders-edit__line.is-out .wx-orders-edit__what {
  opacity: 0.7;
}

.is-struck {
  text-decoration: line-through;
}

.wx-orders-edit__controls {
  display: flex;
  flex-wrap: wrap;
  grid-area: controls;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-orders-edit__option {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-6);
}

.wx-orders-edit__select {
  width: 140px;
}

.wx-orders-edit__qty {
  width: 120px;
}

.wx-orders-edit__money {
  display: flex;
  grid-area: money;
  flex-direction: column;
  align-items: flex-end;
  text-align: end;
  white-space: nowrap;
}

.wx-orders-edit__was {
  margin-inline-end: var(--wx-space-4);
  color: var(--wx-text-muted);
  font-weight: normal;
}

.wx-orders-edit__found {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-orders-edit__found-name,
.wx-orders-edit__found-meta {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-orders-edit__side {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  align-self: start;
  min-width: 0;
  padding: var(--wx-space-12);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-muted);
}

.wx-orders-edit__rows {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: var(--wx-space-8) var(--wx-space-12);
  margin: 0;
}

.wx-orders-edit__rows dt {
  min-width: 0;
  color: var(--wx-text-muted);
  overflow-wrap: anywhere;
}

.wx-orders-edit__rows dd {
  margin: 0;
  text-align: end;
  white-space: nowrap;
}

.wx-orders-edit__rows .is-strong {
  color: var(--wx-text-default);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-orders-edit__words {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--wx-space-12);
}

@container (min-width: 640px) {
  .wx-orders-edit__words {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
