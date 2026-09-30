<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  toast,
  WxAlert,
  WxButton,
  WxInput,
  WxInputNumber,
  WxSortableList,
  WxText,
  type LocalizedValue,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createPropertiesApi } from './api'
import { usePropertyEditor } from './editor'
import { NAMESPACE, useCatalogPropertiesMessages } from './i18n'
import type { PropertyInterval } from './types'

/**
 * `wx-catalog-property-intervals`: the steps a number is filtered by (§7.1, «Intervals») — a
 * label, where it starts, where it stops, and its slug in the address, per language.
 *
 * An interval is `[from, to)`: «1–2 kg» and «2–3 kg» do not share a product of exactly 2. Either
 * end may be empty — «up to 1 kg», «from 3 kg». They may overlap («up to 1 kg», «up to 2 kg»);
 * that is the admin's choice, and each counts honestly.
 *
 * The table is saved as a whole with a button of its own, because its rows are records the
 * property's save does not carry, and a half-typed row should not be written a keystroke at a
 * time. «Sort by bounds» puts them in the order a visitor reads them.
 */
defineOptions({ name: 'WxCatalogPropertyIntervals' })

interface Row {
  key: string
  id: number | null
  title: LocalizedValue
  slug: LocalizedValue
  min: number | null
  max: number | null
}

const admin = useAdmin()
const api = createPropertiesApi(admin)
const editor = usePropertyEditor()
useCatalogPropertiesMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const property = computed(() => editor?.property.value ?? null)
const locked = computed(() => editor?.locked.value ?? true)

const rows = ref<Row[]>([])
const snapshot = ref('')
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

let made = 0

const words = (map: PropertyInterval['title']): LocalizedValue =>
  map && !Array.isArray(map) ? { ...map } : {}

function rowOf(interval: PropertyInterval): Row {
  return {
    key: interval.id ? `i${interval.id}` : `n${++made}`,
    id: interval.id ?? null,
    title: words(interval.title),
    slug: words(interval.slug),
    min: interval.min,
    max: interval.max,
  }
}

const shape = (list: Row[]) =>
  JSON.stringify(list.map(({ id, title, slug, min, max }) => ({ id, title, slug, min, max })))

function take(intervals: PropertyInterval[]): void {
  rows.value = intervals.map(rowOf)
  snapshot.value = shape(rows.value)
}

watch(
  () => editor?.intervals.value,
  (intervals) => take(intervals ?? []),
  { immediate: true },
)

const dirty = computed(() => shape(rows.value) !== snapshot.value)

function add(): void {
  const last = rows.value[rows.value.length - 1]

  // The next step starts where the last one stops — the usual way a scale is written.
  rows.value = [
    ...rows.value,
    { key: `n${++made}`, id: null, title: {}, slug: {}, min: last?.max ?? null, max: null },
  ]
}

function remove(key: string): void {
  rows.value = rows.value.filter((row) => row.key !== key)
}

/** Open lower ends first, then by where each starts, then by where it stops, open last. */
function sortByBounds(): void {
  const low = (row: Row) => row.min ?? Number.NEGATIVE_INFINITY
  const high = (row: Row) => row.max ?? Number.POSITIVE_INFINITY

  rows.value = [...rows.value].sort((a, b) => low(a) - low(b) || high(a) - high(b))
}

function errorOf(index: number, field: string): string | undefined {
  const prefix = `intervals.${index}.${field}`
  const key = Object.keys(errors.value).find(
    (one) => one === prefix || one.startsWith(`${prefix}.`),
  )

  return key === undefined ? undefined : errors.value[key]?.[0]
}

async function save(): Promise<void> {
  if (!property.value || saving.value) return

  saving.value = true
  errors.value = {}

  try {
    const saved = await api.saveIntervals(
      property.value.id,
      rows.value.map(({ id, title, slug, min, max }) => ({ id, title, slug, min, max })),
    )

    if (editor) editor.intervals.value = saved
    else take(saved)
    toast.success(t('panel.intervals-saved'))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('panel.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="wx-catalog-intervals">
    <wx-alert
      v-if="!property || property.filter_mode !== 'intervals'"
      type="info"
      variant="soft"
      :description="t('panel.intervals-unsaved')"
    />

    <template v-else>
      <wx-text size="sm" tone="muted">{{ t('panel.intervals-help') }}</wx-text>

      <div class="wx-catalog-intervals__head" aria-hidden="true">
        <span>{{ t('panel.interval-title') }}</span>
        <span>{{ t('panel.interval-min') }}</span>
        <span>{{ t('panel.interval-max') }}</span>
        <span>{{ t('panel.interval-slug') }}</span>
      </div>

      <wx-sortable-list
        v-model="rows"
        class="wx-catalog-intervals__list"
        item-key="key"
        :item-label="(row: Row) => row.title[admin.i18n.state.locale] ?? ''"
        :disabled="locked"
        :empty-text="t('panel.intervals-empty')"
      >
        <template #default="{ item, index }">
          <div class="wx-catalog-intervals__row">
            <wx-input
              v-model="(item as Row).title"
              localized
              size="sm"
              :disabled="locked"
              :aria-label="t('panel.interval-title')"
              :status="errorOf(index, 'title') ? 'error' : undefined"
            />
            <wx-input-number
              v-model="(item as Row).min"
              size="sm"
              :controls="false"
              :disabled="locked"
              :placeholder="t('panel.interval-open')"
              :aria-label="t('panel.interval-min')"
            />
            <wx-input-number
              v-model="(item as Row).max"
              size="sm"
              :controls="false"
              :disabled="locked"
              :placeholder="t('panel.interval-open')"
              :aria-label="t('panel.interval-max')"
              :status="errorOf(index, 'max') ? 'error' : undefined"
            />
            <wx-input
              v-model="(item as Row).slug"
              localized
              size="sm"
              :disabled="locked"
              :aria-label="t('panel.interval-slug')"
              :status="errorOf(index, 'slug') ? 'error' : undefined"
            />
            <wx-text
              v-for="line in ['title', 'max', 'slug']
                .map((field) => errorOf(index, field))
                .filter(Boolean)"
              :key="line"
              class="wx-catalog-intervals__error"
              size="sm"
            >
              {{ line }}
            </wx-text>
          </div>
        </template>

        <template #actions="{ item }">
          <wx-button
            size="sm"
            variant="text"
            icon="trash"
            :disabled="locked"
            :aria-label="t('panel.delete')"
            @click="remove((item as Row).key)"
          />
        </template>
      </wx-sortable-list>

      <div v-if="!locked" class="wx-catalog-intervals__bar">
        <wx-button size="sm" icon="plus" @click="add">{{ t('panel.interval-add') }}</wx-button>
        <wx-button size="sm" variant="outline" :disabled="rows.length < 2" @click="sortByBounds">
          {{ t('panel.intervals-sort') }}
        </wx-button>
        <span class="wx-catalog-intervals__spacer" />
        <wx-button type="primary" size="sm" :loading="saving" :disabled="!dirty" @click="save">
          {{ t('panel.intervals-save') }}
        </wx-button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-intervals {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  container-type: inline-size;
}

/* One grid for the head and every row, so the columns line up under their names. */
.wx-catalog-intervals__head,
.wx-catalog-intervals__row {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 2fr);
  gap: var(--wx-space-8);
  align-items: center;
}

.wx-catalog-intervals__head {
  /* Over the rows' grid, which starts after the list's frame and the grip and stops before the
     delete button: measured against a row, so the names stand over their fields. */
  padding-inline: calc(var(--wx-space-48) + var(--wx-space-4))
    calc(var(--wx-space-64) + var(--wx-space-2));
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-catalog-intervals__row {
  flex: 1 1 auto;
  min-width: 0;
}

.wx-catalog-intervals__error {
  grid-column: 1 / -1;
  color: var(--wx-color-danger);
}

.wx-catalog-intervals__bar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}

.wx-catalog-intervals__spacer {
  flex: 1 1 auto;
}

/* A phone has no room for four fields in a line: two by two, and the head goes. */
@container (max-width: 560px) {
  .wx-catalog-intervals__head {
    display: none;
  }

  .wx-catalog-intervals__row {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }
}
</style>
