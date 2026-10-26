<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { createRelationsApi, useAdmin, useTranslate } from '@webx-ui/module-admin'
import {
  WxAlert,
  WxCard,
  WxDatePicker,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxSelect,
  WxSwitch,
  type LocalizedValue,
  type SelectOption,
} from '@webx-ui/core'
import { errorsAt, useBuilder } from './builder'
import { localized, setText, written } from './document'
import { useConfiguratorEditor } from './editor'

/**
 * The settings of a configurator (§13.2): its name, its key — fixed once it has been on the site
 * (decision 11) — the Inbox form a request goes to, the currency, how the total is rounded, and
 * the discount (§5.1): a percent, the last day it is given and its name in the breakdown.
 *
 * The form is chosen from the forms the Inbox has, through its relation target, so this package
 * does not need the Inbox's: without the Inbox on the site there is no field, and a note says why.
 */
defineOptions({ name: 'WxConfiguratorSettings' })

const editor = useConfiguratorEditor()
const builder = useBuilder()
const t = useTranslate('webx-configurator')
const admin = useAdmin()

const values = editor.values
const schema = builder.schema
const disabled = builder.disabled
const locale = builder.locale

const keyLocked = computed(() => editor.detail.value?.configurator.key_locked ?? false)
const hasInbox = computed(() => editor.detail.value?.has_inbox ?? false)

function serverErrors(field: string): string[] {
  return editor.errors.value[field] ?? []
}

function setTitle(next: LocalizedValue | string | number | undefined): void {
  const text = written(typeof next === 'number' ? String(next) : (next ?? null))

  values.value.title = typeof text === 'string' ? { [locale.value]: text } : (text ?? {})
}

/* ------------------------------------------------------------------------------- the form */

const forms = ref<SelectOption[]>([])

onMounted(async () => {
  if (!hasInbox.value) return

  try {
    const rows = await createRelationsApi(admin, 'inbox-form').search('')

    forms.value = rows
      .map((row) => {
        const slug = (row as { slug?: string }).slug ?? ''

        return { value: slug, label: `${row.title} · ${slug}` }
      })
      .filter((row) => row.value !== '')
  } catch {
    // Without the list the slug already chosen is still shown, and the server checks a new one.
  }
})

const formOptions = computed<SelectOption[]>(() => {
  const chosen = values.value.form

  return chosen && !forms.value.some((one) => one.value === chosen)
    ? [{ value: chosen, label: chosen }, ...forms.value]
    : forms.value
})

function setForm(next: unknown): void {
  values.value.form = typeof next === 'string' && next !== '' ? next : null
}

/* ---------------------------------------------------------------------- currency, rounding */

function setCurrency(next: LocalizedValue | string | number | undefined): void {
  schema.value.currency = String(next ?? '')
    .toUpperCase()
    .trim()
}

function setRound(next: number | null | undefined): void {
  if (typeof next !== 'number' || !Number.isFinite(next)) delete schema.value.pricing
  else schema.value.pricing = { ...(schema.value.pricing ?? {}), round: next }
}

/* ------------------------------------------------------------------------------- discount */

const discount = computed(() => schema.value.discount ?? null)

function toggleDiscount(on: unknown): void {
  if (on) schema.value.discount = { percent: 10 }
  else delete schema.value.discount
}

function setPercent(next: number | null | undefined): void {
  if (discount.value) discount.value.percent = next ?? 0
}

function setUntil(next: unknown): void {
  if (!discount.value) return

  if (typeof next === 'string' && next !== '') discount.value.until = next
  else delete discount.value.until
}

function setLabel(next: LocalizedValue | string | number | undefined): void {
  if (discount.value)
    setText(discount.value, 'label', typeof next === 'number' ? String(next) : (next ?? null))
}
</script>

<template>
  <div class="wx-configurator-settings">
    <wx-card :title="t('configurator.general')">
      <div class="wx-configurator-settings__fields">
        <wx-form-item
          :label="t('configurator.field-title')"
          :error="serverErrors('title')"
          required
        >
          <wx-input
            :model-value="values.title"
            localized
            :disabled="disabled"
            @update:model-value="setTitle"
          />
        </wx-form-item>

        <wx-form-item
          :label="t('configurator.field-key')"
          :help="keyLocked ? t('configurator.key-locked') : t('configurator.key-help')"
          :error="serverErrors('key')"
          required
        >
          <wx-input v-model="values.key" :maxlength="64" :disabled="disabled || keyLocked" />
        </wx-form-item>

        <wx-form-item
          v-if="hasInbox"
          :label="t('configurator.field-form')"
          :help="t('configurator.field-form-help')"
          :error="serverErrors('form')"
        >
          <wx-select
            :model-value="values.form ?? ''"
            :options="formOptions"
            clearable
            filterable
            :placeholder="t('configurator.no-form')"
            :disabled="disabled"
            @update:model-value="setForm"
          />
        </wx-form-item>
        <wx-alert v-else type="info">{{ t('configurator.no-inbox') }}</wx-alert>

        <div class="wx-configurator-settings__pair">
          <wx-form-item
            :label="t('configurator.field-currency')"
            :help="t('configurator.field-currency-help')"
            :error="errorsAt(builder, 'currency')"
            required
          >
            <wx-input
              :model-value="schema.currency"
              :maxlength="3"
              :disabled="disabled"
              @update:model-value="setCurrency"
            />
          </wx-form-item>

          <wx-form-item
            :label="t('configurator.field-round')"
            :help="t('configurator.field-round-help')"
            :error="errorsAt(builder, 'pricing.round')"
          >
            <wx-input-number
              :model-value="schema.pricing?.round ?? 1"
              :min="0.01"
              :disabled="disabled"
              @update:model-value="setRound"
            />
          </wx-form-item>
        </div>
      </div>
    </wx-card>

    <wx-card :title="t('configurator.discount')">
      <div class="wx-configurator-settings__fields">
        <wx-switch
          :model-value="discount !== null"
          :label="t('configurator.discount-on')"
          :disabled="disabled"
          @update:model-value="toggleDiscount"
        />

        <template v-if="discount">
          <div class="wx-configurator-settings__pair">
            <wx-form-item
              :label="t('configurator.discount-percent')"
              :error="errorsAt(builder, 'discount.percent')"
              required
            >
              <wx-input-number
                :model-value="discount.percent"
                :min="0"
                :max="100"
                :disabled="disabled"
                @update:model-value="setPercent"
              />
            </wx-form-item>

            <wx-form-item
              :label="t('configurator.discount-until')"
              :help="t('configurator.discount-until-help')"
              :error="errorsAt(builder, 'discount.until')"
            >
              <wx-date-picker
                :model-value="discount.until ?? null"
                value-format="yyyy-MM-dd"
                clearable
                :disabled="disabled"
                @update:model-value="setUntil"
              />
            </wx-form-item>
          </div>

          <wx-form-item
            :label="t('configurator.discount-label')"
            :help="t('configurator.discount-label-help')"
            :error="errorsAt(builder, 'discount.label')"
          >
            <wx-input
              :model-value="localized(discount.label, locale)"
              localized
              :disabled="disabled"
              @update:model-value="setLabel"
            />
          </wx-form-item>
        </template>
      </div>
    </wx-card>
  </div>
</template>

<style scoped>
.wx-configurator-settings {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  max-width: 720px;
}

.wx-configurator-settings__fields {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
}

.wx-configurator-settings__pair {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
  gap: var(--wx-space-16);
}
</style>
