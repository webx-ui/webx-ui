<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import {
  toast,
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxRepeater,
  WxSelect,
  WxSpace,
  WxSwitch,
  type LocalizedValue,
} from '@webx-ui/core'
import {
  codify,
  useAdmin,
  useDefaultLocale,
  useErrorText,
  useTranslate,
  WxCodeField,
} from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type {
  CheckoutField,
  CheckoutFieldConsequences,
  CheckoutFieldInput,
  CheckoutFieldPlace,
  CheckoutFieldsData,
  CheckoutFieldType,
} from './types'

/**
 * One extra field of the checkout written (WEBX_UI_ORDER_FIELDS.md §7.2): its label and hint in
 * every language, the key and the type — fixed once saved — the options of a list, whether the
 * buyer must answer, the place, and the checkbox it is shown behind.
 *
 * A change the buyer will feel — required now, switched off, moved, options taken away — is said
 * first: the first «Save» asks the server what it does, the second does it. The server holds every
 * rule (§7.3) and refuses under the input it is about; this form only keeps the choices honest.
 */
const props = defineProps<{
  field: CheckoutField | null
  fields: CheckoutField[]
  places: CheckoutFieldPlace[]
  limits: CheckoutFieldsData['limits']
  /** The place a new field is added to. */
  place?: string
}>()

const { resolve, dismiss, open } = useModal<CheckoutFieldsData>()

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()
const locale = useDefaultLocale()

interface OptionRow {
  value: string
  label: LocalizedValue
  /** Saved before: its key stays, the orders that chose it name it so. */
  saved: boolean
  touched: boolean
}

const TYPES: CheckoutFieldType[] = ['checkbox', 'text', 'textarea', 'select']
const KEY = { separator: '_', lead: 'field', max: 32 } as const
const OPTION = { separator: '_', lead: 'option', max: 32 } as const

const saved = props.field !== null
const label = ref<LocalizedValue>({ ...(props.field?.label ?? {}) })
const key = ref(props.field?.key ?? '')
const keyTouched = ref(saved)
const type = ref<CheckoutFieldType>(props.field?.type ?? 'checkbox')
const options = ref<OptionRow[]>(
  (props.field?.options ?? []).map((one) => ({
    value: one.value,
    label: { ...one.label },
    saved: true,
    touched: true,
  })),
)
const hint = ref<LocalizedValue>({ ...(props.field?.hint ?? {}) })
const required = ref(props.field?.required ?? false)
const active = ref(props.field?.active ?? true)
const parent = ref<string>(props.field?.parent ?? '')
/** Where a new field goes when nobody says: before the comment (§2.1), else the first place. */
const DEFAULT_PLACE = 'extra-before-comment'
const place = ref<string>(
  props.field?.place ??
    props.place ??
    (props.places.some((one) => one.key === DEFAULT_PLACE)
      ? DEFAULT_PLACE
      : (props.places[0]?.key ?? '')),
)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const preview = ref<CheckoutFieldConsequences | null>(null)
const consequencesBox = ref<HTMLElement | null>(null)

const mainLabel = (value: LocalizedValue) =>
  typeof value === 'string' ? value : (value[locale.value] ?? Object.values(value)[0] ?? '')

/* The key follows the label until it is typed, and never once the field is saved. */
watch(
  () => mainLabel(label.value),
  (words) => {
    if (!keyTouched.value) key.value = codify(words, { ...KEY, locale: locale.value })
  },
)

const behind = computed(() =>
  props.field ? props.fields.filter((one) => one.parent === props.field?.key) : [],
)
const parentField = computed(() => props.fields.find((one) => one.key === parent.value) ?? null)

const typeOptions = TYPES.map((value) => ({
  value,
  label: t(`panel.checkout-fields.types.${value}`),
}))

const placeOptions = computed(() => {
  const known = props.places.map((one) => ({ value: one.key, label: one.label }))

  // A place the site no longer has stays readable, so the choice is made, not lost.
  return props.field && !props.field.place_known
    ? [
        ...known,
        {
          value: props.field.place,
          label: `${t('panel.checkout-fields.unknown-place')}: ${props.field.place}`,
          disabled: true,
        },
      ]
    : known
})

/** «Always», or a checkbox of the same place that is not shown behind another one itself. */
const parentOptions = computed(() => [
  { value: '', label: t('panel.checkout-fields.always') },
  ...props.fields
    .filter(
      (one) =>
        one.type === 'checkbox' &&
        one.parent === null &&
        one.key !== props.field?.key &&
        (one.place === place.value || one.key === parent.value),
    )
    .map((one) => ({ value: one.key, label: one.name })),
])

/* Behind a checkbox, a field stands where it does (§3.3). */
watch(parentField, (found) => {
  if (found) place.value = found.place
})

watch([type, required, active, place, parent, options], () => {
  preview.value = null
})

function input(): CheckoutFieldInput {
  const sent: CheckoutFieldInput = {
    label: label.value as Record<string, string>,
    hint: hint.value as Record<string, string>,
    options:
      type.value === 'select'
        ? options.value.map((one) => ({
            value: one.value,
            label: one.label as Record<string, string>,
          }))
        : [],
    required: required.value,
    active: active.value,
    place: place.value,
    parent: parent.value === '' ? null : parent.value,
  }

  return saved ? sent : { ...sent, key: key.value, type: type.value }
}

/** What the buyer will feel and should be told before it happens. */
const FELT = ['required_on', 'hidden', 'moved', 'options_removed']

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    if (props.field && preview.value === null) {
      const said = await api.previewCheckoutField(props.field.id, input())

      if (said.refusal) {
        errors.value = { [said.field ?? 'key']: [said.message ?? ''] }

        return
      }

      if (said.effects.some((effect) => FELT.includes(effect))) {
        preview.value = said
        // Under the last field, where a long form leaves it out of sight.
        await nextTick()
        consequencesBox.value?.scrollIntoView?.({ block: 'nearest' })

        return
      }
    }

    resolve(await api.saveCheckoutField(props.field?.id ?? null, input()))
  } catch (failure) {
    const found = (failure as { errors?: Record<string, string[]> }).errors

    if (found && Object.keys(found).length > 0) errors.value = found
    else toast.danger(message(failure))
  } finally {
    saving.value = false
  }
}

/** The first error under this input, or under any of its parts (`label.en`, `options.0.value`). */
function errorOf(name: string): string | undefined {
  const found = Object.keys(errors.value).find((one) => one === name || one.startsWith(`${name}.`))

  return found ? errors.value[found]?.[0] : undefined
}

/* ---------------------------------------------------------------------------- options ----- */

const newOption = (): OptionRow => ({ value: '', label: {}, saved: false, touched: false })

function optionLabel(
  row: OptionRow,
  words: LocalizedValue,
  update: (patch: Partial<OptionRow>) => void,
) {
  update(
    row.touched
      ? { label: words }
      : { label: words, value: codify(mainLabel(words), { ...OPTION, locale: locale.value }) },
  )
}

const optionName = (row: OptionRow, index: number) => {
  const words = mainLabel(row.label)

  return words === '' ? `#${index + 1}` : `#${index + 1} · ${words}`
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="
      field
        ? t('panel.checkout-fields.edit-title', { field: field.name })
        : t('panel.checkout-fields.new')
    "
    :width="560"
  >
    <div class="wx-orders-field-form">
      <wx-form-item :label="t('panel.checkout-fields.label')" :error="errorOf('label')" required>
        <wx-input v-model="label" localized :maxlength="limits.label" />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.checkout-fields.key')"
        :help="t('panel.checkout-fields.key-help')"
        :error="errorOf('key')"
        required
      >
        <wx-code-field
          v-model="key"
          separator="_"
          lead="field"
          :maxlength="32"
          :readonly="saved"
          @update:model-value="keyTouched = true"
        />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.checkout-fields.type')"
        :help="t('panel.checkout-fields.type-help')"
        :error="errorOf('type')"
        required
      >
        <wx-select v-model="type" :options="typeOptions" :disabled="saved" />
      </wx-form-item>

      <wx-form-item
        v-if="type === 'select'"
        :label="t('panel.checkout-fields.options')"
        :error="errorOf('options')"
        required
      >
        <wx-repeater
          v-model="options"
          size="sm"
          :item-label="optionName"
          :new-item="newOption"
          :max="limits.options"
          :add-label="t('panel.checkout-fields.add-option')"
          :remove-label="t('panel.checkout-fields.remove-option')"
          :drag-label="t('panel.checkout-fields.reorder')"
          sortable
        >
          <template #default="{ item, update }">
            <div class="wx-orders-field-form__option">
              <wx-form-item :label="t('panel.checkout-fields.option-label')">
                <wx-input
                  :model-value="item.label"
                  localized
                  :maxlength="limits.label"
                  @update:model-value="
                    (words) => optionLabel(item, words as LocalizedValue, update)
                  "
                />
              </wx-form-item>
              <wx-form-item :label="t('panel.checkout-fields.option-value')">
                <wx-code-field
                  :model-value="item.value"
                  separator="_"
                  lead="option"
                  :maxlength="32"
                  :readonly="item.saved"
                  @update:model-value="(value) => update({ value: String(value), touched: true })"
                />
              </wx-form-item>
            </div>
          </template>
        </wx-repeater>
      </wx-form-item>

      <wx-form-item
        :label="t('panel.checkout-fields.hint')"
        :help="t('panel.checkout-fields.hint-help')"
        :error="errorOf('hint')"
      >
        <wx-input v-model="hint" localized :maxlength="limits.hint" />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.checkout-fields.place')"
        :help="parentField ? t('panel.checkout-fields.place-follows') : undefined"
        :error="errorOf('place')"
        required
      >
        <wx-select v-model="place" :options="placeOptions" :disabled="parentField !== null" />
      </wx-form-item>

      <wx-form-item :label="t('panel.checkout-fields.parent')" :error="errorOf('parent')">
        <wx-select
          v-model="parent"
          :options="parentOptions"
          :disabled="type === 'checkbox' && behind.length > 0"
        />
      </wx-form-item>

      <wx-switch
        v-model="required"
        :label="
          type === 'checkbox'
            ? t('panel.checkout-fields.must-tick')
            : t('panel.checkout-fields.required')
        "
      />
      <wx-switch v-model="active" :label="t('panel.checkout-fields.active')" />

      <div ref="consequencesBox" aria-live="polite" class="wx-orders-field-form__said">
        <wx-alert v-if="preview && preview.lines.length > 0" type="info">
          <p v-for="(line, index) in preview.lines" :key="index" class="wx-orders-field-form__line">
            {{ line }}
          </p>
        </wx-alert>
      </div>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.dismiss') }}</wx-button>
        <wx-button type="primary" :loading="saving" @click="save">{{ t('panel.save') }}</wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-orders-field-form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-orders-field-form__option {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--wx-space-8);
}

.wx-orders-field-form__said:empty {
  display: none;
}

.wx-orders-field-form__line {
  margin: 0;
}

.wx-orders-field-form__line + .wx-orders-field-form__line {
  margin-top: var(--wx-space-4);
}
</style>
