<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  toast,
  WxActionBar,
  WxAlert,
  WxAutocomplete,
  WxButton,
  WxCard,
  WxDivider,
  WxEmpty,
  WxForm,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxSelect,
  WxSkeleton,
  WxSwitch,
  WxText,
  WxTextarea,
  type AutocompleteOption,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate, WxScreenHead } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type {
  NewOrderContact,
  NewOrderLine,
  NewOrderMeta,
  NewOrderPreview,
  ProductChoice,
} from './types'

/**
 * «New order» (spec §29.2): a manager takes an order by phone. The products are found by a word
 * through whichever module sells them, a variant chosen as a buy button would choose it; the
 * totals on the side are the order's own count, made again on every change of a line, and the
 * contacts are checked when the order is created. Prices are never the manager's (§18), and
 * delivery and payment come into the order later.
 */
defineOptions({ name: 'WxOrdersNewPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/orders' })

const admin = useAdmin()
const api = createOrdersApi(admin)
const router = useRouter()
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const canManage = computed(() => admin.can('orders.manage'))

interface FormLine {
  uid: number
  choice: ProductChoice
  qty: number
  /** Each option of the product: null until the manager picks a value. */
  options: Record<string, string | number | null>
}

const meta = ref<NewOrderMeta | null>(null)
const locale = ref('')
const loading = ref(true)

const term = ref('')
const found = ref<ProductChoice[]>([])
const searching = ref(false)

const lines = ref<FormLine[]>([])
let uid = 0

const preview = ref<NewOrderPreview | null>(null)
const counting = ref(false)
/** The refusals of the count under the line they are about, by the line's uid. */
const lineErrors = ref<Record<number, string>>({})
const countError = ref('')

const customer = reactive<NewOrderContact>({ first_name: '', last_name: '', phone: '', email: '' })
const hasRecipient = ref(false)
const recipient = reactive<NewOrderContact>({ first_name: '', last_name: '', phone: '' })
const comment = ref('')
const errors = ref<Record<string, string[]>>({})
const creating = ref(false)

/* The form's token: a second press of «Create order» — a double click, a retry after a timeout —
 * is the order of the first, not a second one. */
const key = `panel-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`

onMounted(async () => {
  if (!canManage.value) {
    loading.value = false

    return
  }

  try {
    meta.value = await api.newMeta()
    locale.value = meta.value.locale
  } catch (failure) {
    toast.danger(message(failure))
  } finally {
    loading.value = false
  }
})

const localeOptions = computed(() =>
  (meta.value?.locales ?? []).map((one) => ({ value: one.code, label: one.name })),
)

let asked = 0

async function find(search: string): Promise<void> {
  const mine = ++asked

  searching.value = true

  try {
    const answer = await api.findProducts(search.trim(), locale.value)

    if (mine === asked) found.value = answer
  } catch (failure) {
    toast.danger(message(failure))
  } finally {
    if (mine === asked) searching.value = false
  }
}

/* The value keys the list, so it is unique — the name with the article number, or the record's
 * type and id when two say the same; the field is emptied after a pick for the next product. The
 * choice rides along untouched. */
const suggestions = computed<AutocompleteOption[]>(() => {
  const seen = new Set<string>()

  return found.value.map((choice) => {
    let value = `${choice.name} · ${choice.sku ?? `#${choice.ref}`}`

    if (seen.has(value)) value = `${value} (${choice.type}:${choice.ref})`
    seen.add(value)

    return suggestion(choice, value)
  })
})

const suggestion = (choice: ProductChoice, value: string): AutocompleteOption => ({
  value,
  label: choice.name,
  description: [choice.sku, choice.hint, choice.price_formatted ?? t('panel.new-unavailable')]
    .filter(Boolean)
    .join(' · '),
  disabled: !choice.available,
  choice,
})

function add(option: AutocompleteOption): void {
  const choice = option.choice as ProductChoice

  // After the pick has written its value into the field, not before.
  void nextTick(() => (term.value = ''))

  if (!choice.available) return

  // The same product without a choice to make is one line with one more unit, as in a cart.
  const same =
    choice.options.length === 0
      ? lines.value.find(
          (line) => line.choice.type === choice.type && line.choice.ref === choice.ref,
        )
      : undefined

  if (same) {
    same.qty += 1

    return
  }

  lines.value.push({
    uid: ++uid,
    choice,
    qty: 1,
    options: Object.fromEntries(choice.options.map((option) => [option.key, null])),
  })
}

function remove(line: FormLine): void {
  lines.value = lines.value.filter((one) => one.uid !== line.uid)
}

const chosen = (line: FormLine): boolean =>
  Object.values(line.options).every((value) => value !== null)

/** The lines as the server takes them; a variant not chosen yet is not one of them. */
const ready = computed(() => lines.value.filter(chosen))

function payload(line: FormLine): NewOrderLine {
  const options = line.options as Record<string, string | number>

  return {
    type: line.choice.type,
    ref: line.choice.ref,
    qty: line.qty,
    ...(line.choice.options.length > 0 ? { options } : {}),
  }
}

/** `items.1.options` of the answer, back under the line it was sent as. */
function spread(found: Record<string, string[]>, sent: FormLine[]): Record<string, string[]> {
  const rest: Record<string, string[]> = {}
  const mine: Record<number, string> = {}

  for (const [field, messages] of Object.entries(found)) {
    const index = /^items\.(\d+)\./.exec(field)?.[1]
    const line = index !== undefined ? sent[Number(index)] : undefined

    if (line) mine[line.uid] = messages[0] ?? ''
    else rest[field] = messages
  }

  lineErrors.value = mine

  return rest
}

let counted = 0
let timer: ReturnType<typeof setTimeout> | undefined

async function count(): Promise<void> {
  const sent = ready.value
  const mine = ++counted

  if (sent.length === 0) {
    preview.value = null
    lineErrors.value = {}
    countError.value = ''

    return
  }

  counting.value = true

  try {
    const answer = await api.previewNew(sent.map(payload), locale.value)

    if (mine === counted) {
      preview.value = answer
      lineErrors.value = {}
      countError.value = ''
    }
  } catch (failure) {
    if (mine !== counted) return

    const found = (failure as { errors?: Record<string, string[]> }).errors

    preview.value = null
    countError.value = found
      ? Object.values(spread(found, sent)).flat().join(' ')
      : message(failure)
  } finally {
    if (mine === counted) counting.value = false
  }
}

watch(
  () => [locale.value, ready.value.map((line) => JSON.stringify(payload(line)))],
  () => {
    clearTimeout(timer)
    timer = setTimeout(() => void count(), 250)
  },
  { deep: true },
)

const waiting = computed(() => lines.value.length > ready.value.length)

const canCreate = computed(
  () =>
    lines.value.length > 0 &&
    !waiting.value &&
    !counting.value &&
    preview.value?.orderable === true &&
    Object.keys(lineErrors.value).length === 0,
)

async function create(): Promise<void> {
  if (!canCreate.value || creating.value) return

  creating.value = true
  errors.value = {}

  const sent = ready.value

  try {
    const order = await api.create({
      lines: sent.map(payload),
      locale: locale.value,
      customer: { ...customer },
      recipient: hasRecipient.value ? { ...recipient } : null,
      comment: comment.value,
      key,
    })

    toast.success(t('panel.new-created', { number: order.number }))
    await router.push(`${props.base}/${order.id}`)
  } catch (failure) {
    const found = (failure as { errors?: Record<string, string[]> }).errors

    if (found) {
      const rest = spread(found, sent)

      errors.value = rest
      if (rest.items) countError.value = rest.items.join(' ')
    }

    toast.danger(message(failure))
  } finally {
    creating.value = false
  }
}

const optionItems = (option: ProductChoice['options'][number]) =>
  option.values.map((one) => ({ value: one.value, label: one.label }))
</script>

<template>
  <div class="wx-orders-new">
    <wx-screen-head
      :title="t('panel.new-order')"
      :back="props.base"
      :back-label="t('module.orders')"
      divider
    />

    <wx-card v-if="loading"><wx-skeleton :rows="6" /></wx-card>

    <wx-empty v-else-if="!canManage || !meta" :description="t('panel.new-lines-empty')" />

    <div v-else class="wx-orders-new__body">
      <wx-card :title="t('panel.new-lines')" class="wx-orders-new__products">
        <div class="wx-orders-new__lines">
          <wx-autocomplete
            v-model="term"
            :options="suggestions"
            remote
            :loading="searching"
            :placeholder="t('panel.new-find')"
            :aria-label="t('panel.new-find')"
            :empty-text="t('panel.new-find-empty')"
            @search="find"
            @open="find(term)"
            @select="add"
          >
            <template #option="{ option }">
              <span class="wx-orders-new__found">
                <span class="wx-orders-new__found-name">{{ option.label }}</span>
                <wx-text size="sm" tone="muted" class="wx-orders-new__found-meta">
                  {{ option.description }}
                </wx-text>
              </span>
            </template>
          </wx-autocomplete>

          <wx-text v-if="lines.length === 0" tone="muted" size="sm">
            {{ t('panel.new-lines-empty') }}
          </wx-text>

          <ul v-else class="wx-orders-new__list">
            <li
              v-for="line in lines"
              :key="line.uid"
              class="wx-orders-new__line"
              :class="{ 'is-invalid': lineErrors[line.uid] }"
            >
              <div class="wx-orders-new__what">
                <span class="wx-orders-new__name">{{ line.choice.name }}</span>
                <wx-text size="sm" tone="muted">
                  {{ [line.choice.sku, line.choice.price_formatted].filter(Boolean).join(' · ') }}
                </wx-text>
              </div>

              <div class="wx-orders-new__controls">
                <span
                  v-for="option in line.choice.options"
                  :key="option.key"
                  class="wx-orders-new__option"
                >
                  <wx-text size="sm" tone="muted" as="span">{{ option.label }}</wx-text>
                  <wx-select
                    v-model="line.options[option.key]"
                    class="wx-orders-new__select"
                    size="sm"
                    :options="optionItems(option)"
                    :placeholder="t('panel.new-choose')"
                    :aria-label="option.label"
                  />
                </span>
                <wx-input-number
                  v-model="line.qty"
                  class="wx-orders-new__qty"
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
                  @click="remove(line)"
                />
              </div>

              <wx-text
                v-if="lineErrors[line.uid]"
                size="sm"
                tone="danger"
                class="wx-orders-new__error"
              >
                {{ lineErrors[line.uid] }}
              </wx-text>
            </li>
          </ul>
        </div>
      </wx-card>

      <aside class="wx-orders-new__side">
        <wx-card :title="t('panel.new-totals')">
          <div class="wx-orders-new__totals" :aria-busy="counting">
            <wx-skeleton v-if="counting && !preview" :rows="3" />

            <template v-else-if="preview">
              <ul class="wx-orders-new__counted">
                <li v-for="line in preview.totals.lines" :key="String(line.id)">
                  <span class="wx-orders-new__counted-name">
                    {{ line.name }}
                    <wx-text v-if="line.options_label" size="sm" tone="muted" as="span">
                      · {{ line.options_label }}
                    </wx-text>
                    <wx-text size="sm" tone="muted" as="span"> × {{ line.qty }}</wx-text>
                  </span>
                  <span class="wx-orders-new__amount">{{ line.line_total_formatted }}</span>
                </li>
              </ul>

              <wx-divider />

              <dl class="wx-orders-new__rows">
                <dt>{{ t('panel.subtotal') }}</dt>
                <dd>{{ preview.totals.subtotal_formatted }}</dd>
                <template v-for="(one, index) in preview.totals.adjustments" :key="`a${index}`">
                  <dt>{{ one.label }}</dt>
                  <dd>{{ one.amount_formatted }}</dd>
                </template>
                <dt class="is-strong">{{ t('panel.total') }}</dt>
                <dd class="is-strong">{{ preview.totals.total_formatted }}</dd>
                <template v-for="(one, index) in preview.totals.tenders" :key="`t${index}`">
                  <dt>{{ one.label }}</dt>
                  <dd>−{{ one.amount_formatted }}</dd>
                </template>
                <template v-if="preview.totals.tenders.length > 0">
                  <dt class="is-strong">{{ t('panel.payable') }}</dt>
                  <dd class="is-strong">{{ preview.totals.payable_formatted }}</dd>
                </template>
              </dl>
            </template>

            <wx-text v-else tone="muted" size="sm">{{ t('panel.new-lines-empty') }}</wx-text>

            <wx-alert
              v-for="(warning, index) in preview?.totals.warnings ?? []"
              :key="`w${index}`"
              type="warning"
              :description="warning.message"
            />
            <wx-alert
              v-if="preview && !preview.orderable && preview.message"
              type="danger"
              :description="preview.message"
            />
            <wx-alert v-if="countError" type="danger" :description="countError" />

            <wx-text size="sm" tone="muted">{{ t('panel.new-blocks-help') }}</wx-text>
          </div>
        </wx-card>
      </aside>

      <wx-card :title="t('panel.new-buyer')" class="wx-orders-new__buyer">
        <wx-form :errors="errors" class="wx-orders-new__form">
          <div class="wx-orders-new__pair">
            <wx-form-item :label="t('panel.new-first-name')" name="customer.first_name" required>
              <wx-input v-model="customer.first_name" autocomplete="off" />
            </wx-form-item>
            <wx-form-item :label="t('panel.new-last-name')" name="customer.last_name">
              <wx-input v-model="customer.last_name" autocomplete="off" />
            </wx-form-item>
          </div>
          <!-- Either of the two will do, so neither gets an asterisk: the pair gets a hint. -->
          <div class="wx-orders-new__contacts">
            <div class="wx-orders-new__pair">
              <wx-form-item :label="t('panel.new-phone')" name="customer.phone">
                <wx-input v-model="customer.phone" type="tel" autocomplete="off" />
              </wx-form-item>
              <wx-form-item :label="t('panel.new-email')" name="customer.email">
                <wx-input v-model="customer.email" type="email" autocomplete="off" />
              </wx-form-item>
            </div>
            <wx-text size="sm" tone="muted">{{ t('panel.new-contacts-help') }}</wx-text>
          </div>

          <wx-form-item>
            <wx-switch v-model="hasRecipient">{{ t('panel.new-recipient') }}</wx-switch>
          </wx-form-item>
          <template v-if="hasRecipient">
            <div class="wx-orders-new__pair">
              <wx-form-item :label="t('panel.new-first-name')" name="recipient.first_name" required>
                <wx-input v-model="recipient.first_name" autocomplete="off" />
              </wx-form-item>
              <wx-form-item :label="t('panel.new-last-name')" name="recipient.last_name">
                <wx-input v-model="recipient.last_name" autocomplete="off" />
              </wx-form-item>
            </div>
            <wx-form-item :label="t('panel.new-phone')" name="recipient.phone">
              <wx-input v-model="recipient.phone" type="tel" autocomplete="off" />
            </wx-form-item>
          </template>

          <wx-form-item :label="t('panel.new-comment')" name="comment">
            <wx-textarea v-model="comment" :rows="3" />
          </wx-form-item>

          <wx-form-item
            v-if="localeOptions.length > 1"
            :label="t('panel.new-locale')"
            name="locale"
            :help="t('panel.new-locale-help')"
          >
            <wx-select v-model="locale" :options="localeOptions" />
          </wx-form-item>
        </wx-form>
      </wx-card>

      <wx-action-bar class="wx-orders-new__actions">
        <wx-button type="primary" :disabled="!canCreate" :loading="creating" @click="create">
          {{ t('panel.new-create') }}
        </wx-button>
      </wx-action-bar>
    </div>
  </div>
</template>

<style scoped>
.wx-orders-new {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-orders-new__body {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--wx-space-16);
}

/* Narrow, the totals come right after the products they count; wide, they stand beside the form
 * and stay in sight while it scrolls. */
@container (min-width: 900px) {
  .wx-orders-new__body {
    grid-template-columns: minmax(0, 1fr) minmax(280px, 360px);
    align-items: start;
  }

  .wx-orders-new__products,
  .wx-orders-new__buyer {
    grid-column: 1;
  }

  .wx-orders-new__side {
    position: sticky;
    top: var(--wx-space-16);
    grid-column: 2;
    grid-row: 1 / span 2;
  }

  .wx-orders-new__actions {
    grid-column: 1 / -1;
  }
}

.wx-orders-new__lines,
.wx-orders-new__totals {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-orders-new__found {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.wx-orders-new__found-name,
.wx-orders-new__name {
  color: var(--wx-text-strong);
  overflow-wrap: anywhere;
}

.wx-orders-new__list {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-orders-new__line {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8) var(--wx-space-12);
  padding-block: var(--wx-space-12);
  border-top: 1px solid var(--wx-border-muted);
}

.wx-orders-new__line:first-child {
  border-top: 0;
}

.wx-orders-new__what {
  display: flex;
  flex: 1 1 200px;
  flex-direction: column;
  min-width: 0;
}

.wx-orders-new__controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-orders-new__option {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-orders-new__select {
  width: 96px;
}

.wx-orders-new__qty {
  width: 120px;
}

.wx-orders-new__error {
  flex-basis: 100%;
}

.wx-orders-new__form {
  min-width: 0;
}

.wx-orders-new__pair {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 0 var(--wx-space-16);
}

.wx-orders-new__contacts {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
}

@container (min-width: 560px) {
  .wx-orders-new__pair {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.wx-orders-new__counted {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-orders-new__counted li {
  display: flex;
  justify-content: space-between;
  gap: var(--wx-space-12);
}

.wx-orders-new__counted-name {
  min-width: 0;
  overflow-wrap: anywhere;
}

.wx-orders-new__amount {
  white-space: nowrap;
}

.wx-orders-new__rows {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: var(--wx-space-8) var(--wx-space-16);
  margin: 0;
}

.wx-orders-new__rows dt {
  min-width: 0;
  color: var(--wx-text-muted);
}

.wx-orders-new__rows dd {
  margin: 0;
  text-align: end;
  white-space: nowrap;
}

.wx-orders-new__rows .is-strong {
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-semibold);
}
</style>
