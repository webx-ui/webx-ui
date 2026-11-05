<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  WxAlert,
  WxButton,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxSelect,
  WxText,
  type SelectOption,
} from '@webx-ui/core'
import { useErrorText, useTranslate } from '@webx-ui/module-admin'
import { useFeedEditor } from './editor'
import { NAMESPACE, useCatalogFeedsMessages } from './i18n'
import type { LabelSource, MpnSource } from './types'

/**
 * `wx-catalog-feed-labels`: where each `custom_label_0…4` and `mpn` take their value (§3.3, §3.4 of
 * the feeds spec). Five rows of a source and what it needs; a source that needs what the site does
 * not have is not offered.
 */
defineOptions({ name: 'WxCatalogFeedLabels' })

const LABELS = ['0', '1', '2', '3', '4']

const PROPERTY = 'property.'

const editor = useFeedEditor()
useCatalogFeedsMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const locked = computed(() => editor.locked.value)
const labels = computed(() => editor.values.value.custom_labels ?? {})
const mpn = computed<MpnSource>(() => editor.values.value.sources?.mpn ?? { type: 'field' })

const codes = ref<SelectOption[]>([])
const failed = ref('')

const properties = computed<SelectOption[]>(() =>
  (editor.variables.value?.item ?? [])
    .filter((variable) => variable.name.startsWith(PROPERTY))
    .map((variable) => ({
      value: variable.name.slice(PROPERTY.length),
      label: variable.label ?? variable.name,
    })),
)

const sources = computed<SelectOption[]>(() =>
  (editor.meta.value?.label_sources ?? [])
    .filter((type) => type !== 'label' || codes.value.length > 0)
    .filter((type) => type !== 'property' || properties.value.length > 0)
    .map((type) => ({ value: type, label: t(`panel.label-source-${type}`) })),
)

const mpnSources = computed<SelectOption[]>(() => [
  { value: 'field', label: t('panel.mpn-field') },
  ...(properties.value.length > 0 ? [{ value: 'property', label: t('panel.mpn-property') }] : []),
])

watch(
  () => editor.values.value.locale,
  async (locale) => {
    try {
      const facets = await editor.api.facets(locale)

      codes.value = (facets.find((facet) => facet.key === 'label')?.values ?? []).map((one) => ({
        value: one.value,
        label: one.label,
      }))
    } catch (error) {
      failed.value = message(error)
    }
  },
  { immediate: true },
)

const sourceOf = (key: string): LabelSource => labels.value[key] ?? { type: 'none' }

function write(key: string, source: LabelSource): void {
  editor.values.value = {
    ...editor.values.value,
    custom_labels: { ...labels.value, [key]: source },
  }
}

/** A new source starts with what it needs, so a save names what is missing rather than a shape. */
function pick(key: string, type: unknown): void {
  const chosen = String(type)

  if (chosen === 'label') write(key, { type: chosen, labels: [] })
  else if (chosen === 'price_range') write(key, { type: chosen, ranges: [{ value: '' }] })
  else if (chosen === 'property') write(key, { type: chosen, property: '' })
  else if (chosen === 'constant') write(key, { type: chosen, value: '' })
  else write(key, { type: chosen })
}

function rangesOf(key: string): { to?: number | null; value: string }[] {
  return sourceOf(key).ranges ?? []
}

function range(key: string, index: number, change: { to?: number | null; value?: string }): void {
  const next = rangesOf(key).map((one, at) => (at === index ? { ...one, ...change } : one))

  write(key, { ...sourceOf(key), ranges: next })
}

function addRange(key: string): void {
  write(key, { ...sourceOf(key), ranges: [...rangesOf(key), { value: '' }] })
}

function dropRange(key: string, index: number): void {
  write(key, { ...sourceOf(key), ranges: rangesOf(key).filter((_, at) => at !== index) })
}

function writeMpn(source: MpnSource): void {
  editor.values.value = {
    ...editor.values.value,
    sources: { ...editor.values.value.sources, mpn: source },
  }
}

function pickMpn(type: unknown): void {
  writeMpn(type === 'property' ? { type: 'property', property: '' } : { type: 'field' })
}

const errorOf = (field: string) => editor.errors.value[field]?.[0]
</script>

<template>
  <div class="wx-catalog-feed-labels">
    <wx-alert v-if="failed" type="danger" variant="soft" :description="failed" />

    <wx-text size="sm" tone="muted">{{ t('panel.labels-help') }}</wx-text>

    <wx-alert
      v-if="errorOf('custom_labels')"
      type="danger"
      variant="soft"
      :description="errorOf('custom_labels')"
    />

    <div v-for="key in LABELS" :key="key" class="wx-catalog-feed-labels__row">
      <wx-text class="wx-catalog-feed-labels__name" size="sm" weight="medium">
        custom_label_{{ key }}
      </wx-text>

      <wx-select
        class="wx-catalog-feed-labels__source"
        :model-value="sourceOf(key).type"
        :options="sources"
        :disabled="locked"
        :aria-label="`custom_label_${key}`"
        @update:model-value="(type: unknown) => pick(key, type)"
      />

      <div class="wx-catalog-feed-labels__setting">
        <wx-select
          v-if="sourceOf(key).type === 'label'"
          :model-value="sourceOf(key).labels ?? []"
          :options="codes"
          multiple
          :disabled="locked"
          :aria-label="t('panel.label-source-label')"
          @update:model-value="
            (picked: unknown) =>
              write(key, {
                ...sourceOf(key),
                labels: Array.isArray(picked) ? picked.map(String) : [],
              })
          "
        />

        <wx-select
          v-else-if="sourceOf(key).type === 'property'"
          :model-value="sourceOf(key).property || null"
          :options="properties"
          filterable
          :disabled="locked"
          :aria-label="t('panel.label-source-property')"
          @update:model-value="
            (picked: unknown) => write(key, { ...sourceOf(key), property: String(picked ?? '') })
          "
        />

        <wx-input
          v-else-if="sourceOf(key).type === 'constant'"
          :model-value="sourceOf(key).value ?? ''"
          :maxlength="100"
          :disabled="locked"
          :aria-label="t('panel.label-source-constant')"
          @update:model-value="
            (text: unknown) => write(key, { ...sourceOf(key), value: String(text ?? '') })
          "
        />

        <div
          v-else-if="sourceOf(key).type === 'price_range'"
          class="wx-catalog-feed-labels__ranges"
        >
          <div
            v-for="(row, index) in rangesOf(key)"
            :key="index"
            class="wx-catalog-feed-labels__range"
          >
            <wx-input-number
              :model-value="row.to ?? null"
              :controls="false"
              :precision="2"
              :disabled="locked"
              :placeholder="t('panel.label-range-to')"
              :aria-label="t('panel.label-range-to')"
              @update:model-value="
                (picked: number | null | undefined) => range(key, index, { to: picked ?? null })
              "
            />
            <wx-input
              :model-value="row.value"
              :maxlength="100"
              :disabled="locked"
              :placeholder="t('panel.label-range-value')"
              :aria-label="t('panel.label-range-value')"
              @update:model-value="
                (text: unknown) => range(key, index, { value: String(text ?? '') })
              "
            />
            <wx-button
              icon="x"
              variant="text"
              size="sm"
              :disabled="locked || rangesOf(key).length === 1"
              :aria-label="t('panel.label-range-remove')"
              @click="dropRange(key, index)"
            />
          </div>
          <wx-button size="sm" variant="text" icon="plus" :disabled="locked" @click="addRange(key)">
            {{ t('panel.label-range-add') }}
          </wx-button>
        </div>
      </div>
    </div>

    <wx-form-item :label="t('panel.mpn')" :help="t('panel.mpn-help')" :error="errorOf('sources')">
      <div class="wx-catalog-feed-labels__mpn">
        <wx-select
          :model-value="mpn.type"
          :options="mpnSources"
          :disabled="locked"
          :aria-label="t('panel.mpn')"
          @update:model-value="pickMpn"
        />
        <wx-select
          v-if="mpn.type === 'property'"
          :model-value="mpn.property || null"
          :options="properties"
          filterable
          :disabled="locked"
          :aria-label="t('panel.mpn-property')"
          @update:model-value="
            (picked: unknown) => writeMpn({ type: 'property', property: String(picked ?? '') })
          "
        />
      </div>
    </wx-form-item>
  </div>
</template>

<style scoped>
.wx-catalog-feed-labels {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
  container-type: inline-size;
}

.wx-catalog-feed-labels__row {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--wx-space-6);
}

@container (min-width: 640px) {
  .wx-catalog-feed-labels__row {
    grid-template-columns: 130px minmax(0, 1fr) minmax(0, 2fr);
    gap: var(--wx-space-12);
    align-items: start;
  }
}

.wx-catalog-feed-labels__name {
  font-family: var(--wx-font-family-mono);
}

.wx-catalog-feed-labels__setting,
.wx-catalog-feed-labels__source {
  min-width: 0;
}

.wx-catalog-feed-labels__ranges {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-6);
}

.wx-catalog-feed-labels__range {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
  gap: var(--wx-space-6);
  width: 100%;
}

.wx-catalog-feed-labels__mpn {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: var(--wx-space-8);
}
</style>
