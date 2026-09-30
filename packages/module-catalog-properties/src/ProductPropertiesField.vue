<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue'
import {
  confirm,
  toast,
  useLocales,
  WxAlert,
  WxButton,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxSkeleton,
  WxSwitch,
  WxText,
  type LocalizedValue,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createCatalogApi, useProductEditor } from '@webx-ui/module-catalog'
import { screenErrorsKey } from '@webx-ui/schema'
import { createPropertiesApi } from './api'
import { formatNumber, propertyName, wordsIn } from './format'
import { NAMESPACE, useCatalogPropertiesMessages } from './i18n'
import type { EffectiveSet, HeldValue, PropertyRow } from './types'
import ValueSelect from './ValueSelect.vue'
import ValueTreeSelect from './ValueTreeSelect.vue'

/**
 * `wx-catalog-product-properties`: the «Specifications» tab of a product (§7.2) — the field
 * `properties.values`, one control per property of the set of the product's **main** category,
 * by the groups of the card.
 *
 * The set is read again the moment the main category changes in the form, before anything is
 * saved: the server checks the set the product *will* have (§15, P2), so the tab has to show that
 * one. What the product holds of properties the new set does not have is dropped from what is
 * sent — not deleted: a key left out is not touched, and the value waits in the database for the
 * product to come back (decision 6).
 *
 * Those values are the collapsed block at the bottom, «Outside the category's set»: read only,
 * with one button that deletes them all — the bulk action `clear-outside-set` on this product.
 */
defineOptions({ name: 'WxCatalogProductProperties' })

type Values = Record<string, HeldValue>

const value = defineModel<Values | null>({ default: null })

/* The refusal of the last save, to put each property's line under that property. */
const refusal = inject(screenErrorsKey, ref(undefined))

const admin = useAdmin()
const api = createPropertiesApi(admin)
const catalogApi = createCatalogApi(admin)
const editor = useProductEditor()
const locales = useLocales()
useCatalogPropertiesMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const locale = computed(() => admin.i18n.state.locale)
const locked = computed(() => editor?.locked.value ?? false)
/* Units are words of the site, per language: the one being edited, else the panel's. */
const unitLocale = computed(() => locales.active.value || locale.value)

const category = computed<number | null>(() => {
  const id = editor?.values?.value.category_id

  return typeof id === 'number' ? id : id ? Number(id) : null
})

const set = ref<EffectiveSet | null>(null)
const loading = ref(false)
const failed = ref(false)

/** Everything the product held when the editor last read it, in the set and out of it. */
const held = ref<Values>({})
const outsideNames = ref(new Map<number, PropertyRow>())
const outsideWords = ref(new Map<number, string>())
const clearing = ref(false)

const inSet = computed(
  () =>
    new Set((set.value?.groups ?? []).flatMap((group) => group.properties.map((one) => one.id))),
)

/** What the product holds of properties the set does not have. */
const outside = computed(() =>
  Object.entries(held.value)
    .filter(([id]) => !inSet.value.has(Number(id)))
    .map(([id, one]) => ({ id: Number(id), value: one })),
)

/* The editor read or saved the product: what it holds, all of it, is known again. */
watch(
  () => editor?.product.value,
  () => {
    const beyond = (editor?.values?.value['properties.outside'] ?? {}) as Values

    held.value = { ...(Array.isArray(beyond) ? {} : beyond), ...(value.value ?? {}) }
  },
  { immediate: true },
)

/**
 * The form's value for a new set: what was there for the properties that stay, what the product
 * held of the ones that come back, nothing for the ones that go. Written only when it differs, so
 * opening the tab does not mark the product unsaved.
 */
function fit(): void {
  const next: Values = {}
  const now = value.value ?? {}

  for (const id of inSet.value) {
    const key = String(id)

    if (key in now) next[key] = now[key] ?? null
    else if (key in held.value) next[key] = held.value[key] ?? null
  }

  if (JSON.stringify(next) !== JSON.stringify(now)) value.value = next
}

let asked = 0

async function loadSet(): Promise<void> {
  const ticket = ++asked

  if (category.value === null) {
    set.value = { groups: [] }
    fit()

    return
  }

  loading.value = true
  failed.value = false

  try {
    const answer = await api.effectiveSet(category.value)

    if (ticket !== asked) return
    set.value = answer
    fit()
  } catch {
    if (ticket === asked) failed.value = true
  } finally {
    if (ticket === asked) loading.value = false
  }
}

watch(category, () => void loadSet(), { immediate: true })

/* The block below names what it lists: the properties by one request, the values by one each. */
watch(
  outside,
  async (rows) => {
    const missing = rows.map((row) => row.id).filter((id) => !outsideNames.value.has(id))

    if (missing.length === 0) return

    try {
      const page = await api.list({ ids: missing })
      const names = new Map(outsideNames.value)

      for (const one of page.data) names.set(one.id, one)
      outsideNames.value = names

      const words = new Map(outsideWords.value)

      for (const row of rows) {
        const property = names.get(row.id)

        if (property) words.set(row.id, await wordsOf(property, row.value))
      }

      outsideWords.value = words
    } catch {
      // Named by number; the block is read only, and the button still does what it says.
    }
  },
  { immediate: true },
)

async function wordsOf(property: PropertyRow, one: HeldValue): Promise<string> {
  if (one === null) return ''

  switch (property.type) {
    case 'select': {
      const ids = Array.isArray(one) ? (one as number[]) : [one as number]
      const values = await api.valuesById(property.id, ids)

      return values.map((row) => wordsIn(row.title, locale.value, `#${row.id}`)).join(', ')
    }
    case 'number':
      return formatNumber(
        Number(one),
        {
          precision: property.precision,
          prefix: wordsIn(property.unit_prefix, locale.value),
          suffix: wordsIn(property.unit_suffix, locale.value),
        },
        locale.value,
      )
    case 'bool':
      return t('product.yes')
    default:
      return wordsIn(one as LocalizedValue, locale.value)
  }
}

function read(id: number): HeldValue {
  return value.value?.[String(id)] ?? null
}

function write(id: number, next: HeldValue): void {
  const empty =
    next === null ||
    (Array.isArray(next) && next.length === 0) ||
    (typeof next === 'object' &&
      !Array.isArray(next) &&
      Object.values(next as LocalizedValue).every((line) => (line ?? '').trim() === ''))

  value.value = { ...(value.value ?? {}), [String(id)]: empty ? null : next }
}

/** A reference book's value: an id or a list of them. */
function chosen(id: number): number | number[] | null {
  const one = read(id)

  return typeof one === 'number' || Array.isArray(one) ? (one as number | number[]) : null
}

function number(one: HeldValue): number | null {
  return typeof one === 'number' ? one : null
}

function text(one: HeldValue): LocalizedValue {
  return one && typeof one === 'object' && !Array.isArray(one) ? (one as LocalizedValue) : {}
}

function errorOf(id: number): string | undefined {
  const prefix = `properties.values.${id}`
  const errors = refusal.value ?? {}
  const key = Object.keys(errors).find((one) => one === prefix || one.startsWith(`${prefix}.`))

  return key === undefined ? undefined : errors[key]?.[0]
}

async function clearOutside(): Promise<void> {
  const product = editor?.product.value

  if (!product) return

  const agreed = await confirm({
    title: t('panel.outside-clear-title'),
    message: t('panel.outside-clear-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  clearing.value = true

  try {
    await catalogApi.startBulk('clear-outside-set', {}, { ids: [product.id] })

    const kept: Values = {}

    for (const [id, one] of Object.entries(held.value))
      if (inSet.value.has(Number(id))) kept[id] = one
    held.value = kept
    toast.success(t('panel.outside-cleared'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    clearing.value = false
  }
}

const groupTitle = (group: EffectiveSet['groups'][number]) =>
  group.group ? wordsIn(group.group.title, locale.value) : ''
</script>

<template>
  <div class="wx-catalog-product-properties">
    <wx-skeleton v-if="loading && !set" :rows="4" />

    <wx-alert
      v-else-if="failed"
      type="warning"
      variant="soft"
      :description="t('panel.set-failed')"
    />

    <template v-else-if="set">
      <wx-alert
        v-if="category === null"
        type="info"
        variant="soft"
        :description="t('panel.product-no-category')"
      />
      <wx-alert
        v-else-if="set.groups.length === 0"
        type="info"
        variant="soft"
        :description="t('panel.product-empty-set')"
      />

      <section
        v-for="(group, index) in set.groups"
        :key="group.group?.id ?? `none-${index}`"
        class="wx-catalog-product-properties__group"
      >
        <wx-text
          v-if="groupTitle(group)"
          class="wx-catalog-product-properties__heading"
          weight="semibold"
        >
          {{ groupTitle(group) }}
        </wx-text>

        <wx-form-item
          v-for="property in group.properties"
          :key="property.id"
          :label="propertyName(property, locale)"
          :error="errorOf(property.id)"
        >
          <value-tree-select
            v-if="property.type === 'select' && property.is_tree"
            :property="property"
            :model-value="chosen(property.id)"
            :disabled="locked"
            :aria-label="propertyName(property, locale)"
            @update:model-value="(next) => write(property.id, next)"
          />
          <value-select
            v-else-if="property.type === 'select'"
            :property="property"
            :model-value="chosen(property.id)"
            :disabled="locked"
            :aria-label="propertyName(property, locale)"
            @update:model-value="(next) => write(property.id, next)"
          />
          <span
            v-else-if="property.type === 'number'"
            class="wx-catalog-product-properties__number"
          >
            <span
              v-if="wordsIn(property.unit_prefix, unitLocale)"
              class="wx-catalog-product-properties__unit"
            >
              {{ wordsIn(property.unit_prefix, unitLocale) }}
            </span>
            <wx-input-number
              :model-value="number(read(property.id))"
              :controls="false"
              :disabled="locked"
              :aria-label="propertyName(property, locale)"
              @update:model-value="(next) => write(property.id, next ?? null)"
            />
            <span
              v-if="wordsIn(property.unit_suffix, unitLocale)"
              class="wx-catalog-product-properties__unit"
            >
              {{ wordsIn(property.unit_suffix, unitLocale) }}
            </span>
          </span>
          <wx-switch
            v-else-if="property.type === 'bool'"
            :model-value="read(property.id) === true"
            :disabled="locked"
            :aria-label="propertyName(property, locale)"
            @update:model-value="(on) => write(property.id, on ? true : null)"
          />
          <wx-input
            v-else
            :model-value="text(read(property.id))"
            localized
            :disabled="locked"
            :aria-label="propertyName(property, locale)"
            @update:model-value="(next) => write(property.id, next as LocalizedValue)"
          />
        </wx-form-item>
      </section>

      <details v-if="outside.length > 0" class="wx-catalog-product-properties__outside">
        <summary>
          <wx-text weight="medium">{{ t('panel.outside', { count: outside.length }) }}</wx-text>
        </summary>

        <wx-text size="sm" tone="muted">{{ t('panel.outside-help') }}</wx-text>

        <dl class="wx-catalog-product-properties__list">
          <template v-for="row in outside" :key="row.id">
            <dt>
              {{
                outsideNames.get(row.id)
                  ? propertyName(outsideNames.get(row.id)!, locale)
                  : `#${row.id}`
              }}
            </dt>
            <dd>{{ outsideWords.get(row.id) ?? '…' }}</dd>
          </template>
        </dl>

        <wx-button
          v-if="!locked && admin.can('catalog.manage')"
          size="sm"
          variant="outline"
          icon="trash"
          :loading="clearing"
          @click="clearOutside"
        >
          {{ t('panel.outside-clear') }}
        </wx-button>
      </details>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-product-properties {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
}

.wx-catalog-product-properties__group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}

.wx-catalog-product-properties__heading {
  padding-block-end: var(--wx-space-4);
  border-block-end: 1px solid var(--wx-border-muted);
  margin-block-end: var(--wx-space-8);
}

.wx-catalog-product-properties__number {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  max-width: var(--wx-field-max-width, 480px);
}

.wx-catalog-product-properties__unit {
  flex: none;
  color: var(--wx-text-muted);
  white-space: pre;
}

.wx-catalog-product-properties__outside {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-md);
}

.wx-catalog-product-properties__outside > summary {
  cursor: pointer;
}

.wx-catalog-product-properties__outside[open] > summary {
  margin-block-end: var(--wx-space-8);
}

.wx-catalog-product-properties__list {
  display: grid;
  grid-template-columns: minmax(0, max-content) minmax(0, 1fr);
  gap: var(--wx-space-4) var(--wx-space-16);
  margin: var(--wx-space-8) 0;
  color: var(--wx-text-muted);
}

.wx-catalog-product-properties__list dd {
  margin: 0;
}
</style>
