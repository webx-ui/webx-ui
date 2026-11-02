<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  useModal,
  WxAlert,
  WxAutocomplete,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxRadioGroup,
  WxSkeleton,
  WxSpace,
  WxText,
  type AutocompleteOption,
} from '@webx-ui/core'
import { useAdmin, useErrorText } from '@webx-ui/module-admin'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import type {
  ChangeInput,
  ChangeMethod,
  ChangeOption,
  ChangeOutcome,
  ChangeTown,
  OrderDelivery,
  OrderDeliveryChangeState,
} from './types'

/**
 * «Change delivery» of the tab «Delivery» (decision of 02.11.2026): the buyer mistyped the
 * street, chose the wrong pick-up point, or one the carrier will not take the parcel to. The
 * town by the checkout's search with the popular towns under it, the methods priced for the
 * order's lines, the point by its number or address, the address by the method's rules — a
 * carrier's courier takes a street of its directory only. Before saving, «was → will be» of the
 * sum, from the server's own count (a dry run); the save answers the tab as it is after.
 */
defineOptions({ name: 'WxDeliveryOrderChangeDialog' })

const props = defineProps<{ orderId: number; start: OrderDeliveryChangeState }>()

const { open, resolve } = useModal<OrderDelivery>()

const api = createDeliveryApi(useAdmin())
const t = useDeliveryWords()
const message = useErrorText()

const town = ref<{ id: number; label: string } | null>(props.start.locality)
const townTerm = ref('')
const towns = ref<ChangeTown[]>([])
const popular = ref<ChangeTown[]>([])
const searchingTowns = ref(false)

const methods = ref<ChangeMethod[]>([])
const loadingMethods = ref(false)
const methodId = ref<number | null>(null)

const pointTerm = ref('')
const points = ref<ChangeOption[]>([])
const searchingPoints = ref(false)
const point = ref<{ id: number; label: string } | null>(null)

const streetTerm = ref(props.start.address?.street ?? '')
const streets = ref<ChangeOption[]>([])
const streetId = ref<string | null>(props.start.address?.street_id ?? null)
const house = ref(props.start.address?.house ?? '')
const apartment = ref(props.start.address?.apartment ?? '')
const note = ref(props.start.address?.note ?? '')
const reason = ref('')

const errors = ref<Record<string, string[]>>({})
const outcome = ref<ChangeOutcome | null>(null)
const checking = ref(false)
const saving = ref(false)

const method = computed(() => methods.value.find((one) => one.id === methodId.value) ?? null)
const ownStreets = computed(() => method.value?.carrier === 'store')

const methodOptions = computed(() =>
  methods.value.map((one) => ({
    value: one.id,
    label: one.price ? `${one.title} · ${one.price.text}` : one.title,
    disabled: one.disabled,
  })),
)

const errorOf = (key: string) => errors.value[key]?.[0]

const townOptions = computed<AutocompleteOption[]>(() =>
  towns.value.map((one) => ({ value: one.name, description: one.label, id: one.id })),
)

const pointOptions = computed<AutocompleteOption[]>(() =>
  points.value.map((one) => ({
    value: one.label,
    description: one.hint ?? undefined,
    id: Number(one.value),
  })),
)

const streetOptions = computed<AutocompleteOption[]>(() =>
  streets.value.map((one) => ({ value: one.label, id: one.value })),
)

let townAsked = 0

async function searchTowns(q: string): Promise<void> {
  const mine = ++townAsked

  searchingTowns.value = true

  try {
    const found = await api.changeTowns(props.orderId, q.trim())

    if (mine !== townAsked) return

    towns.value = found.localities
    popular.value = found.popular
  } catch {
    if (mine === townAsked) towns.value = []
  } finally {
    if (mine === townAsked) searchingTowns.value = false
  }
}

function pickTown(one: ChangeTown): void {
  town.value = { id: one.id, label: one.label }
  townTerm.value = ''
  towns.value = []
}

function selectTown(option: AutocompleteOption): void {
  const one = towns.value.find((row) => row.id === (option as { id?: number }).id)

  if (one) pickTown(one)
}

async function loadMethods(): Promise<void> {
  if (!town.value) return

  loadingMethods.value = true
  outcome.value = null

  try {
    const found = await api.changeMethods(props.orderId, town.value.id)

    methods.value = found.methods

    // The method the order has stays chosen where the new town offers it too.
    const kept =
      found.methods.find((one) => one.id === methodId.value && !one.disabled) ??
      found.methods.find((one) => one.key === props.start.method && !one.disabled)

    methodId.value = kept?.id ?? null
  } catch (error) {
    methods.value = []
    errors.value = { method: [message(error)] }
  } finally {
    loadingMethods.value = false
  }
}

let pointAsked = 0

async function searchPoints(q: string): Promise<void> {
  if (!town.value || !method.value) return

  const mine = ++pointAsked

  searchingPoints.value = true

  try {
    const found = await api.changePoints(props.orderId, town.value.id, method.value.id, q.trim())

    if (mine === pointAsked) points.value = found.options
  } catch {
    if (mine === pointAsked) points.value = []
  } finally {
    if (mine === pointAsked) searchingPoints.value = false
  }
}

function selectPoint(option: AutocompleteOption): void {
  const id = (option as { id?: number }).id

  if (typeof id === 'number') {
    point.value = { id, label: option.value }
    pointTerm.value = option.value
  }
}

async function searchStreets(q: string): Promise<void> {
  if (!town.value || !method.value) return

  try {
    streets.value = (
      await api.changeStreets(props.orderId, town.value.id, method.value.id, q.trim())
    ).options
  } catch {
    streets.value = []
  }
}

function selectStreet(option: AutocompleteOption): void {
  streetTerm.value = option.value
  // A carrier's street goes by its id; the shop's courier takes the name as text (§8.3).
  streetId.value = ownStreets.value ? null : String((option as { id?: string }).id ?? '')
}

function typedStreet(value: string): void {
  // Typing over a street of a directory drops its id: the name alone is not the carrier's street.
  if (!ownStreets.value) streetId.value = null
  streetTerm.value = value
}

const input = computed<ChangeInput | null>(() => {
  if (!town.value || !method.value) return null

  const body: ChangeInput = { locality: town.value.id, method: method.value.id }

  if (method.value.asks === 'point') body.point = point.value?.id ?? null

  if (method.value.asks === 'address') {
    body.address = {
      street: streetTerm.value.trim() || null,
      street_id: ownStreets.value ? null : streetId.value,
      house: house.value.trim() || null,
      apartment: apartment.value.trim() || null,
      note: note.value.trim() || null,
    }
  }

  return body
})

function refusal(error: unknown): void {
  errors.value = (error as { errors?: Record<string, string[]> }).errors ?? {}
  if (Object.keys(errors.value).length === 0) errors.value = { delivery: [message(error)] }
}

/** «Was → will be» from the server's count, before anything is written. */
async function check(): Promise<void> {
  const body = input.value

  if (!body || checking.value) return

  checking.value = true
  errors.value = {}
  outcome.value = null

  try {
    outcome.value = (await api.changeDelivery(props.orderId, { ...body, dry_run: true })).change
  } catch (error) {
    refusal(error)
  } finally {
    checking.value = false
  }
}

async function save(): Promise<void> {
  const body = input.value

  if (!body || saving.value) return

  saving.value = true
  errors.value = {}

  try {
    const done = await api.changeDelivery(props.orderId, {
      ...body,
      reason: reason.value.trim() || null,
    })

    if (done.delivery) resolve(done.delivery)
  } catch (error) {
    refusal(error)
  } finally {
    saving.value = false
  }
}

watch(
  town,
  (now, before) => {
    if (now?.id !== before?.id) {
      point.value = null
      pointTerm.value = ''
      points.value = []
      streets.value = []
    }

    void loadMethods()
  },
  { immediate: true },
)

watch(methodId, (now, before) => {
  outcome.value = null

  if (before !== null && now !== before) {
    point.value = null
    pointTerm.value = ''
    points.value = []
    streets.value = []
    if (!ownStreets.value) streetId.value = null
  }
})

// Any change of the choice makes the last «will be» stale.
watch([point, streetTerm, house, apartment], () => (outcome.value = null))

void searchTowns('')

// The point the order has, shown by its title when the town and the method are the order's.
watch(methods, (list) => {
  if (point.value || props.start.point === null) return

  const same = list.find((one) => one.key === props.start.method)

  if (!same || same.id !== methodId.value || town.value?.id !== props.start.locality?.id) return

  void (async () => {
    const found = await api.changePoints(props.orderId, town.value!.id, same.id, '')
    const mine = found.options.find((one) => Number(one.value) === props.start.point)

    if (mine) {
      point.value = { id: Number(mine.value), label: mine.label }
      pointTerm.value = mine.label
    }
  })().catch(() => undefined)
})

/** The radios speak in any value; the method is its id. */
const methodChoice = computed<string | number | boolean | null>({
  get: () => methodId.value,
  set: (value) => (methodId.value = value === null ? null : Number(value)),
})
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('order.change-title')" :width="600">
    <div class="wx-delivery-change">
      <wx-text tone="muted" size="sm">{{ t('order.change-help') }}</wx-text>

      <wx-alert v-if="errorOf('delivery')" type="danger" :description="errorOf('delivery')" />

      <wx-form-item :label="t('order.locality')" :error="errorOf('locality')" required>
        <div class="wx-delivery-change__town">
          <span v-if="town" class="wx-delivery-change__chosen" data-test="change-town">
            {{ town.label }}
          </span>
          <wx-autocomplete
            v-model="townTerm"
            :options="townOptions"
            remote
            teleport
            :loading="searchingTowns"
            :empty-text="t('locality.nothing-found')"
            :placeholder="t('order.change-town-search')"
            :aria-label="t('order.change-town-search')"
            data-test="change-town-search"
            @search="searchTowns"
            @select="selectTown"
          />
          <wx-space v-if="popular.length > 0" :size="8" wrap>
            <wx-button
              v-for="one in popular"
              :key="one.id"
              size="sm"
              variant="outline"
              :type="town?.id === one.id ? 'primary' : undefined"
              data-test="change-popular"
              @click="pickTown(one)"
            >
              {{ one.name }}
            </wx-button>
          </wx-space>
        </div>
      </wx-form-item>

      <wx-form-item v-if="town" :label="t('order.method')" :error="errorOf('method')" required>
        <wx-skeleton v-if="loadingMethods" :rows="2" />
        <wx-text v-else-if="methods.length === 0" tone="muted">
          {{ t('order.change-no-methods') }}
        </wx-text>
        <div v-else class="wx-delivery-change__methods">
          <wx-radio-group
            v-model="methodChoice"
            :options="methodOptions"
            data-test="change-methods"
          />
          <wx-text
            v-for="one in methods.filter((row) => row.disabled && row.hint)"
            :key="one.id"
            size="sm"
            tone="muted"
          >
            {{ one.title }}: {{ one.hint }}
          </wx-text>
        </div>
      </wx-form-item>

      <wx-form-item
        v-if="method?.asks === 'point'"
        :label="t('order.point')"
        :error="errorOf('point')"
        required
      >
        <wx-autocomplete
          v-model="pointTerm"
          :options="pointOptions"
          remote
          teleport
          open-on-focus
          :loading="searchingPoints"
          :empty-text="t('order.change-no-points')"
          :placeholder="t('order.change-point-search')"
          :aria-label="t('order.change-point-search')"
          data-test="change-point"
          @search="searchPoints"
          @select="selectPoint"
        />
      </wx-form-item>

      <template v-if="method?.asks === 'address'">
        <wx-form-item
          :label="t('order.change-street')"
          :help="ownStreets ? undefined : t('order.change-street-help')"
          :error="errorOf('address.street') ?? errorOf('address.street_id')"
          required
        >
          <wx-autocomplete
            :model-value="streetTerm"
            :options="streetOptions"
            remote
            teleport
            :aria-label="t('order.change-street')"
            data-test="change-street"
            @update:model-value="typedStreet"
            @search="searchStreets"
            @select="selectStreet"
          />
        </wx-form-item>
        <div class="wx-delivery-change__pair">
          <wx-form-item :label="t('order.change-house')" :error="errorOf('address.house')" required>
            <wx-input v-model="house" :maxlength="32" data-test="change-house" />
          </wx-form-item>
          <wx-form-item :label="t('order.change-apartment')" :error="errorOf('address.apartment')">
            <wx-input v-model="apartment" :maxlength="32" />
          </wx-form-item>
        </div>
        <wx-form-item :label="t('order.note')" :error="errorOf('address.note')">
          <wx-input v-model="note" :maxlength="500" />
        </wx-form-item>
      </template>

      <wx-form-item :label="t('order.change-reason')" :help="t('order.change-reason-help')">
        <wx-input v-model="reason" :maxlength="300" data-test="change-reason" />
      </wx-form-item>

      <div v-if="outcome" class="wx-delivery-change__outcome" data-test="change-outcome">
        <div class="wx-delivery-change__row">
          <span class="wx-delivery-change__label">{{ t('order.cost') }}</span>
          <span>{{
            t('order.change-from-to', { from: outcome.amount_from, to: outcome.amount_to })
          }}</span>
        </div>
        <div class="wx-delivery-change__row">
          <span class="wx-delivery-change__label">{{ t('order.change-total') }}</span>
          <span v-if="outcome.total_changes" class="wx-delivery-change__strong">
            {{ t('order.change-from-to', { from: outcome.total_from, to: outcome.total_to }) }}
          </span>
          <span v-else>{{ t('order.change-same', { amount: outcome.total_to }) }}</span>
        </div>
        <wx-alert
          v-if="outcome.balance"
          :type="outcome.balance.kind === 'over' ? 'danger' : 'warning'"
          :description="
            t(outcome.balance.kind === 'over' ? 'order.change-over' : 'order.change-due', {
              amount: outcome.balance.amount,
            })
          "
        />
      </div>
    </div>

    <template #footer>
      <wx-button @click="open = false">{{ t('common.cancel') }}</wx-button>
      <wx-button
        v-if="!outcome"
        type="primary"
        :disabled="!input"
        :loading="checking"
        data-test="change-check"
        @click="check"
      >
        {{ t('order.change-check') }}
      </wx-button>
      <wx-button v-else type="primary" :loading="saving" data-test="change-save" @click="save">
        {{ t('order.change-save') }}
      </wx-button>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-delivery-change {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-delivery-change__town,
.wx-delivery-change__methods {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-delivery-change__chosen,
.wx-delivery-change__strong {
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-delivery-change__pair {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: var(--wx-space-16);
}

.wx-delivery-change__outcome {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-subtle);
}

.wx-delivery-change__row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: var(--wx-space-4) var(--wx-space-12);
  min-width: 0;
}

.wx-delivery-change__label {
  color: var(--wx-text-muted);
}
</style>
