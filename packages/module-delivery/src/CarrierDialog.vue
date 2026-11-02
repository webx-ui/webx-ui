<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  toast,
  WxBadge,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxSelect,
  WxSkeleton,
  WxSpace,
  WxSwitch,
  type SelectOption,
} from '@webx-ui/core'
import { useAdmin, useErrorText, WxDate } from '@webx-ui/module-admin'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import type { CarrierDetail, CarrierField, SettingOption } from './types'

/**
 * One carrier's settings and keys (§13.2), and its runs.
 *
 * A key is never sent to the panel: the dialog says whether each one is set, and an empty field
 * means «leave it» — typing a new value replaces it, «Clear» removes it. «Check the connection»
 * tries the stored keys with whatever is typed over them, without saving.
 *
 * A field with `options: 'carrier'` is a select whose options come from the carrier's account
 * (`ProvidesSettingOptions`): it waits for the key and for the fields it `depends` on, a change
 * empties the fields that depend on it, a `search` field asks as the admin types. The label of the
 * chosen value is kept beside it (`_labels`), so the field shows it even when the carrier is down.
 */
const props = defineProps<{ carrier: string | null }>()

const open = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{ saved: [] }>()

const LABELS = '_labels'

const context = useAdmin()
const api = createDeliveryApi(context)
const t = useDeliveryWords()
const message = useErrorText()

const detail = ref<CarrierDetail | null>(null)
const settings = ref<Record<string, unknown>>({})
const typed = ref<Record<string, string>>({})
const loading = ref(false)
const saving = ref(false)
const testing = ref(false)
const errors = ref<Record<string, string>>({})

/** Options from the carrier's account by field, what is being asked, and why a list did not come. */
const choices = ref<Record<string, SettingOption[]>>({})
const asking = ref<Record<string, boolean>>({})
const failures = ref<Record<string, string>>({})
const refreshing = ref(false)

const canManage = computed(() => context.can('delivery.manage'))

const fields = computed(() => detail.value?.settings.fields ?? [])
const fromAccount = computed(() => fields.value.filter(isFromAccount))

/** Options from the account are asked with the stored key: none set — nothing to ask with. */
const keyed = computed(
  () =>
    !detail.value ||
    detail.value.credentials.length === 0 ||
    detail.value.credentials.some((one) => one.set),
)

function isFromAccount(field: CarrierField): boolean {
  return field.options === 'carrier'
}

function label(field: CarrierField): string {
  if (typeof field.label === 'string') return field.label
  if (field.label && typeof field.label === 'object') {
    const locale = context.i18n.state.locale

    return field.label[locale] ?? Object.values(field.label)[0] ?? field.name
  }

  return field.name
}

function byName(name: string): CarrierField | undefined {
  return fields.value.find((one) => one.name === name)
}

const blank = (value: unknown) => value === null || value === undefined || value === ''

function labels(): Record<string, string> {
  const stored = settings.value[LABELS]

  return stored && typeof stored === 'object' ? (stored as Record<string, string>) : {}
}

/** The first field this one waits for, or null — it may be asked. */
function waitingFor(field: CarrierField): CarrierField | null {
  for (const parent of field.depends ?? []) {
    if (blank(settings.value[parent])) return byName(parent) ?? { name: parent }
  }

  return null
}

function dependsValues(field: CarrierField): Record<string, unknown> {
  return Object.fromEntries((field.depends ?? []).map((name) => [name, settings.value[name]]))
}

/** Why the field cannot be chosen now, in words; null — it can. */
function blocked(field: CarrierField): string | null {
  if (!keyed.value) return t('options.no-key')

  const parent = waitingFor(field)

  if (parent) return t('options.choose-first', { field: label(parent) })

  return failures.value[field.name] ?? null
}

/** The options to draw: what the account gave, and the chosen value by its stored label. */
function optionsOf(field: CarrierField): SelectOption[] {
  const list: SelectOption[] = (choices.value[field.name] ?? []).map((one) => ({
    value: one.value,
    label: one.hint ? `${one.label} · ${one.hint}` : one.label,
  }))
  const value = settings.value[field.name]

  if (!blank(value) && !list.some((one) => one.value === String(value))) {
    list.unshift({ value: String(value), label: labels()[field.name] ?? String(value) })
  }

  return list
}

async function ask(field: CarrierField, q?: string, fresh = false): Promise<void> {
  if (!props.carrier || !keyed.value || waitingFor(field)) return

  // A search field shows its chosen value by its label until the admin types.
  if (field.search && !q) return

  asking.value = { ...asking.value, [field.name]: true }

  try {
    const list = await api.carrierOptions(props.carrier, field.name, {
      values: dependsValues(field),
      q,
      fresh,
    })

    choices.value = { ...choices.value, [field.name]: list }
    failures.value = Object.fromEntries(
      Object.entries(failures.value).filter(([name]) => name !== field.name),
    )
  } catch (error) {
    failures.value = { ...failures.value, [field.name]: message(error) }
  } finally {
    asking.value = { ...asking.value, [field.name]: false }
  }
}

/** Every field that waits for nothing, as the dialog opens or «Refresh lists» is pressed. */
async function askAll(fresh = false): Promise<void> {
  await Promise.all(fromAccount.value.map((field) => ask(field, undefined, fresh)))
}

async function refresh(): Promise<void> {
  refreshing.value = true
  failures.value = {}

  try {
    await askAll(true)
  } finally {
    refreshing.value = false
  }
}

/** The fields that depend on this one, and those that depend on them, in their order. */
function children(name: string): CarrierField[] {
  const found: CarrierField[] = []
  const walk = (parent: string) => {
    for (const field of fields.value) {
      if ((field.depends ?? []).includes(parent) && !found.includes(field)) {
        found.push(field)
        walk(field.name)
      }
    }
  }

  walk(name)

  return found
}

function choose(field: CarrierField, value: unknown): void {
  const picked = (choices.value[field.name] ?? []).find((one) => one.value === value)
  const nextLabels = { ...labels() }

  settings.value[field.name] = value

  if (picked) nextLabels[field.name] = picked.label
  else if (blank(value)) delete nextLabels[field.name]

  // A new sender has other contacts: whatever stood on the old one is emptied down the chain.
  for (const child of children(field.name)) {
    settings.value[child.name] = null
    delete nextLabels[child.name]
    choices.value = { ...choices.value, [child.name]: [] }
  }

  settings.value[LABELS] = nextLabels

  for (const child of children(field.name)) void ask(child)
}

const timers: Record<string, ReturnType<typeof setTimeout>> = {}

function search(field: CarrierField, q: string): void {
  clearTimeout(timers[field.name])

  if (q.trim().length < 2) return

  timers[field.name] = setTimeout(() => void ask(field, q.trim()), 250)
}

/** Under the field: why it is off, a value gone from the account, or nothing. */
function helpOf(field: CarrierField): string | undefined {
  if (!isFromAccount(field)) return undefined

  const missing = detail.value?.settings.missing?.[field.name]

  return (
    blocked(field) ??
    (missing ? t('options.missing', { label: missing }) : undefined) ??
    (field.search ? t('options.search') : undefined)
  )
}

async function load(): Promise<void> {
  if (!props.carrier) return

  loading.value = true
  typed.value = {}
  errors.value = {}
  choices.value = {}
  failures.value = {}

  try {
    detail.value = await api.carrier(props.carrier)
    settings.value = { ...detail.value.settings.values }
  } catch (error) {
    toast.danger(message(error))
    open.value = false

    return
  } finally {
    loading.value = false
  }

  void askAll()
}

watch(open, (now) => {
  if (now) void load()
})

function credentials(): Record<string, string> {
  return Object.fromEntries(Object.entries(typed.value).filter(([, value]) => value.trim() !== ''))
}

async function save(clear: string[] = []): Promise<void> {
  if (!props.carrier) return

  saving.value = true
  errors.value = {}

  try {
    // The labels are the server's to write: it takes them from the carrier's list.
    const values = Object.fromEntries(
      Object.entries(settings.value).filter(([name]) => name !== LABELS),
    )

    detail.value = await api.saveCarrier(props.carrier, {
      settings: values,
      credentials: credentials(),
      clear,
    })
    settings.value = { ...detail.value.settings.values }
    typed.value = {}
    toast.success(t('carrier.saved'))
    emit('saved')
    void askAll()
  } catch (error) {
    const found = (error as { errors?: Record<string, string[]> }).errors ?? {}

    errors.value = Object.fromEntries(
      Object.entries(found).map(([key, list]) => [key.replace(/^settings\./, ''), list[0] ?? '']),
    )
    toast.danger(message(error))
  } finally {
    saving.value = false
  }
}

async function test(): Promise<void> {
  if (!props.carrier) return

  testing.value = true

  try {
    const result = await api.testCarrier(props.carrier, credentials())

    if (result.ok) toast.success(result.message ?? t('carrier.test-ok'))
    else toast.danger(t('carrier.test-failed', { message: result.message ?? '' }))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    testing.value = false
  }
}

const statusType = {
  running: 'primary',
  succeeded: 'success',
  failed: 'danger',
  held: 'warning',
} as const
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="detail?.title ?? t('carrier.choose')"
    :width="640"
    scroll="body"
  >
    <wx-skeleton v-if="loading || !detail" :rows="5" />

    <div v-else class="wx-delivery-carrier-form">
      <section v-if="detail.settings.fields.length > 0">
        <div class="wx-delivery-carrier-form__head">
          <h4 class="wx-delivery-carrier-form__title">{{ t('carrier.settings') }}</h4>
          <wx-button
            v-if="fromAccount.length > 0 && keyed"
            size="sm"
            variant="text"
            icon="refresh"
            :loading="refreshing"
            data-test="options-refresh"
            @click="refresh"
          >
            {{ t('options.refresh') }}
          </wx-button>
        </div>
        <wx-form-item
          v-for="field in detail.settings.fields"
          :key="field.name"
          :label="label(field)"
          :help="helpOf(field)"
          :error="errors[field.name]"
          :data-test="`setting-${field.name}`"
        >
          <div v-if="isFromAccount(field)" class="wx-delivery-carrier-form__choice">
            <!-- A wrapper takes the row: the select's own root carries no scoped attribute. -->
            <div class="wx-delivery-carrier-form__select">
              <wx-select
                :model-value="(settings[field.name] as string | null) ?? null"
                :options="optionsOf(field)"
                :filterable="field.search === true"
                :disabled="!canManage || blocked(field) !== null || asking[field.name] === true"
                :status="detail.settings.missing?.[field.name] ? 'warning' : undefined"
                :placeholder="field.search ? t('options.search') : undefined"
                clearable
                @update:model-value="choose(field, $event)"
                @search="search(field, $event)"
              />
            </div>
            <wx-button
              v-if="failures[field.name] && canManage"
              size="sm"
              variant="outline"
              @click="ask(field, undefined, true)"
            >
              {{ t('options.retry') }}
            </wx-button>
          </div>
          <wx-switch
            v-else-if="field.type === 'boolean'"
            v-model="settings[field.name] as boolean"
            :disabled="!canManage"
          />
          <wx-input-number
            v-else-if="field.type === 'number'"
            v-model="settings[field.name] as number"
            :step="field.step"
            :precision="field.precision"
            :min="field.min"
            :max="field.max"
            :disabled="!canManage"
          />
          <wx-select
            v-else-if="field.type === 'select'"
            v-model="settings[field.name] as string"
            :options="Array.isArray(field.options) ? field.options : []"
            :disabled="!canManage"
          />
          <wx-input v-else v-model="settings[field.name] as string" :disabled="!canManage" />
        </wx-form-item>
      </section>

      <section v-if="detail.credentials.length > 0" data-test="credentials">
        <h4 class="wx-delivery-carrier-form__title">{{ t('carrier.credentials') }}</h4>
        <wx-form-item v-for="field in detail.credentials" :key="field.name" :label="label(field)">
          <div class="wx-delivery-carrier-form__secret">
            <wx-badge :type="field.set ? 'success' : 'default'" dot>
              {{ field.set ? t('carrier.set') : t('carrier.not-set') }}
            </wx-badge>
            <wx-input
              v-model="typed[field.name]"
              type="password"
              autocomplete="new-password"
              :placeholder="t('carrier.replace')"
              :disabled="!canManage"
              class="wx-delivery-carrier-form__key"
            />
            <wx-button
              v-if="field.set && canManage"
              size="sm"
              variant="text"
              @click="save([field.name])"
            >
              {{ t('carrier.clear') }}
            </wx-button>
          </div>
        </wx-form-item>
      </section>

      <section>
        <h4 class="wx-delivery-carrier-form__title">{{ t('carrier.runs') }}</h4>
        <p v-if="detail.runs.length === 0" class="wx-delivery-carrier-form__muted">
          {{ t('carrier.never') }}
        </p>
        <ul v-else class="wx-delivery-carrier-form__runs">
          <li v-for="run in detail.runs" :key="run.id">
            <wx-badge :type="statusType[run.status]" size="sm">
              {{ t(`carrier.statuses.${run.status}`) }}
            </wx-badge>
            <span>{{ t(`carrier.directories.${run.directory}`) }}</span>
            <wx-date v-if="run.started_at" :value="run.started_at" />
            <span class="wx-delivery-carrier-form__muted">
              {{ t('carrier.seen') }}: {{ run.seen }} · +{{ run.created }} · −{{ run.removed }}
            </span>
            <span v-if="run.error" class="wx-delivery-carrier-form__error">{{ run.error }}</span>
          </li>
        </ul>
      </section>
    </div>

    <template v-if="canManage && detail" #footer>
      <wx-space size="sm">
        <wx-button
          v-if="detail.credentials.length > 0"
          variant="outline"
          :loading="testing"
          @click="test"
        >
          {{ t('carrier.test') }}
        </wx-button>
        <wx-button type="primary" :loading="saving" data-test="carrier-save" @click="save()">
          {{ t('common.save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-delivery-carrier-form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
}

.wx-delivery-carrier-form__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
  margin-bottom: var(--wx-space-8);
}

.wx-delivery-carrier-form__head .wx-delivery-carrier-form__title {
  margin: 0;
}

.wx-delivery-carrier-form__title {
  margin: 0 0 var(--wx-space-8);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
}

.wx-delivery-carrier-form__choice {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  width: 100%;
}

.wx-delivery-carrier-form__select {
  flex: 1 1 200px;
  min-width: 0;
}

.wx-delivery-carrier-form__secret {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  width: 100%;
}

.wx-delivery-carrier-form__key {
  flex: 1 1 200px;
  min-width: 0;
}

.wx-delivery-carrier-form__runs {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-delivery-carrier-form__runs li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  font-size: var(--wx-font-size-sm);
}

.wx-delivery-carrier-form__muted {
  color: var(--wx-text-muted);
}

.wx-delivery-carrier-form__error {
  flex-basis: 100%;
  color: var(--wx-color-danger);
}
</style>
