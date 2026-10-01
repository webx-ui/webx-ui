<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  useLocales,
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxProgress,
  WxSelect,
  WxSpace,
  WxSwitch,
  WxTable,
  WxText,
  WxTreeSelect,
  type SelectOption,
  type TableColumn,
  type TreeSelectValue,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { useCategoryTree } from '@webx-ui/module-catalog'
import { createLandingsApi } from './api'
import { NAMESPACE, useCatalogLandingsMessages } from './i18n'
import type { BaseFacet, GenerateParams, GeneratePreview, GenerateRow, GenerateRun } from './types'

/**
 * «Create in bulk» (§8.3 of the landings spec), three steps in one dialog: the bases, the facet and
 * its values, the templates — then the preview, a row per landing with the reason it is skipped —
 * then the run, done at once or polled while the queue makes it. Resolves with the run, or `null`
 * when nothing was made.
 */
const { open, resolve } = useModal<GenerateRun | null>()

const admin = useAdmin()
const api = createLandingsApi(admin)
const tree = useCategoryTree(admin)
const locales = useLocales()
useCatalogLandingsMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

type Step = 'form' | 'preview' | 'run'

const step = ref<Step>('form')
const categories = ref<number[]>([])
const subtree = ref(false)
const facet = ref<string | null>(null)
const values = ref<string[]>([])
const minProducts = ref<number | null>(1)
const templates = ref({ slug: '', name: '', h1: '', title: '', description: '' })
const publish = ref(false)

const facets = ref<BaseFacet[]>([])
const errors = ref<Record<string, string[]>>({})
const failure = ref('')
const busy = ref(false)
const preview = ref<GeneratePreview | null>(null)
const run = ref<GenerateRun | null>(null)

onMounted(() => void tree.load().catch(() => undefined))

/* The facets of values the first base offers: a range has no value to make a landing of. */
watch(
  () => categories.value[0] ?? null,
  async (first) => {
    if (first === null) {
      facets.value = []

      return
    }

    try {
      facets.value = (await api.facets(first)).filter((one) => one.kind !== 'range')
    } catch (error) {
      failure.value = message(error)
    }
  },
)

const facetOptions = computed<SelectOption[]>(() =>
  facets.value.map((one) => ({ value: one.key, label: one.label })),
)

const valueOptions = computed<SelectOption[]>(() =>
  (facets.value.find((one) => one.key === facet.value)?.values ?? []).map((one) => ({
    value: one.value,
    label: `${one.label} (${one.count})`,
  })),
)

watch(facet, () => (values.value = []))

const params = computed<GenerateParams>(() => ({
  categories: categories.value,
  subtree: subtree.value,
  facet: facet.value ?? '',
  values: values.value.length > 0 ? values.value : null,
  min_products: minProducts.value,
  ...templates.value,
  publish: publish.value,
}))

const ready = computed(() => categories.value.length > 0 && facet.value !== null)

function first(field: string): string | undefined {
  return errors.value[field]?.[0]
}

function refuse(error: unknown): void {
  const body = (error as { body?: { errors?: Record<string, string[]> } }).body

  if (body?.errors) errors.value = body.errors
  else failure.value = message(error)
}

async function toPreview(): Promise<void> {
  if (!ready.value || busy.value) return

  busy.value = true
  errors.value = {}
  failure.value = ''

  try {
    preview.value = await api.preview(params.value)
    step.value = 'preview'
  } catch (error) {
    refuse(error)
  } finally {
    busy.value = false
  }
}

let timer: ReturnType<typeof setTimeout> | undefined

async function poll(id: number, delay = 1500): Promise<void> {
  timer = setTimeout(async () => {
    try {
      run.value = await api.run(id)

      if (run.value.status === 'queued' || run.value.status === 'running') void poll(id)
    } catch {
      void poll(id, Math.min(delay * 2, 15000))
    }
  }, delay)
}

async function start(): Promise<void> {
  if (busy.value) return

  busy.value = true
  failure.value = ''

  try {
    run.value = await api.generate(params.value)
    step.value = 'run'

    if (run.value.id !== null && !finished.value) void poll(run.value.id)
  } catch (error) {
    refuse(error)
    step.value = 'form'
  } finally {
    busy.value = false
  }
}

onBeforeUnmount(() => clearTimeout(timer))

const finished = computed(() => run.value?.status === 'done' || run.value?.status === 'failed')

function close(): void {
  resolve(run.value && run.value.done > 0 ? run.value : null)
}

/* --------------------------------------------------------------- the preview --- */

const word = (record: Record<string, string>) =>
  record[locales.active.value] ?? Object.values(record)[0] ?? ''

const columns = computed<TableColumn<GenerateRow>[]>(() => [
  { key: 'address', label: t('generate.column-address'), minWidth: 180 },
  { key: 'title', label: t('generate.column-h1'), minWidth: 180, hideBelow: 560 },
  { key: 'count', label: t('generate.column-count'), width: 100, align: 'right' },
  { key: 'conflict', label: t('generate.column-conflict'), minWidth: 180 },
])

const rows = computed(() =>
  (preview.value?.rows ?? []).map((row, index) => ({ ...row, id: index })),
)
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="t('generate.title')"
    :width="step === 'form' ? 600 : 860"
    @update:open="(on: boolean) => !on && close()"
  >
    <wx-alert v-if="failure" type="danger">{{ failure }}</wx-alert>

    <div v-if="step === 'form'" class="wx-catalog-landings-generate">
      <wx-form-item
        :label="t('generate.bases')"
        :help="t('generate.bases-help')"
        :error="first('categories')"
        required
      >
        <wx-tree-select
          :model-value="categories"
          :nodes="tree.nodes.value ?? []"
          node-key="id"
          label-key="name"
          children-key="children"
          multiple
          check-strictly
          filterable
          clearable
          @update:model-value="
            (picked: TreeSelectValue) =>
              (categories = Array.isArray(picked)
                ? picked.map(Number)
                : picked
                  ? [Number(picked)]
                  : [])
          "
        />
      </wx-form-item>
      <wx-form-item>
        <wx-switch v-model="subtree">{{ t('generate.subtree') }}</wx-switch>
      </wx-form-item>

      <wx-form-item :label="t('generate.facet')" :error="first('facet')" required>
        <wx-select v-model="facet" :options="facetOptions" :disabled="categories.length === 0" />
      </wx-form-item>
      <wx-form-item :label="t('generate.values')" :help="t('generate.values-help')">
        <wx-select
          v-model="values"
          :options="valueOptions"
          multiple
          filterable
          clearable
          :disabled="facet === null"
        />
      </wx-form-item>
      <wx-form-item :label="t('generate.min-products')" :help="t('generate.min-products-help')">
        <wx-input-number v-model="minProducts" :min="0" :disabled="values.length > 0" />
      </wx-form-item>

      <wx-text weight="medium">{{ t('generate.templates') }}</wx-text>
      <wx-text size="sm" tone="muted">{{ t('generate.templates-help') }}</wx-text>
      <wx-form-item :label="t('generate.slug')" :error="first('slug')">
        <wx-input v-model="templates.slug" placeholder="{category}-{value}" />
      </wx-form-item>
      <wx-form-item :label="t('generate.name')">
        <wx-input v-model="templates.name" placeholder="{category} {value}" />
      </wx-form-item>
      <wx-form-item :label="t('generate.h1')">
        <wx-input v-model="templates.h1" :placeholder="t('generate.template-empty')" />
      </wx-form-item>
      <wx-form-item :label="t('generate.seo-title')">
        <wx-input v-model="templates.title" :placeholder="t('generate.template-empty')" />
      </wx-form-item>
      <wx-form-item :label="t('generate.seo-description')">
        <wx-input v-model="templates.description" :placeholder="t('generate.template-empty')" />
      </wx-form-item>
      <wx-form-item>
        <wx-switch v-model="publish">{{ t('generate.publish') }}</wx-switch>
      </wx-form-item>
    </div>

    <div v-else-if="step === 'preview' && preview" class="wx-catalog-landings-generate">
      <wx-text>
        {{
          preview.free > 0
            ? t('generate.summary', { free: preview.free, skipped: preview.total - preview.free })
            : t('generate.nothing')
        }}
      </wx-text>
      <wx-table :data="rows" :columns="columns" row-key="id" flush layout="fixed">
        <template #cell-address="{ row }">
          <span class="wx-catalog-landings-generate__mono">/{{ word(row.slug) }}/</span>
        </template>
        <template #cell-title="{ row }">
          {{ word(Object.keys(row.h1).length > 0 ? row.h1 : row.name) }}
        </template>
        <template #cell-conflict="{ row }">
          <wx-text v-if="row.conflict" size="sm" tone="warning">{{ row.message }}</wx-text>
        </template>
      </wx-table>
    </div>

    <div v-else-if="step === 'run' && run" class="wx-catalog-landings-generate">
      <template v-if="!finished">
        <wx-text>{{ t('generate.running') }}</wx-text>
        <wx-progress :value="run.done + run.failed" :max="Math.max(1, run.total - run.skipped)" />
      </template>
      <wx-alert v-else :type="run.status === 'failed' ? 'danger' : 'success'">
        {{
          run.status === 'failed'
            ? t('generate.failed')
            : t('generate.done', { done: run.done, skipped: run.skipped, failed: run.failed })
        }}
      </wx-alert>
      <ul v-if="run.errors.length > 0" class="wx-catalog-landings-generate__errors">
        <li v-for="(one, index) in run.errors" :key="index">
          <strong>/{{ one.slug }}/</strong> — {{ one.message }}
        </li>
      </ul>
    </div>

    <template #footer>
      <wx-space size="sm">
        <template v-if="step === 'form'">
          <wx-button variant="outline" @click="close">{{ t('panel.cancel') }}</wx-button>
          <wx-button type="primary" :loading="busy" :disabled="!ready" @click="toPreview">
            {{ t('generate.preview') }}
          </wx-button>
        </template>
        <template v-else-if="step === 'preview'">
          <wx-button variant="outline" @click="step = 'form'">{{ t('generate.back') }}</wx-button>
          <wx-button
            type="primary"
            :loading="busy"
            :disabled="(preview?.free ?? 0) === 0"
            @click="start"
          >
            {{ t('generate.run', { count: preview?.free ?? 0 }) }}
          </wx-button>
        </template>
        <wx-button v-else type="primary" :disabled="!finished" @click="close">
          {{ t('generate.close') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-catalog-landings-generate {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-catalog-landings-generate__mono {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
}

.wx-catalog-landings-generate__errors {
  margin: 0;
  padding-inline-start: var(--wx-space-16);
  font-size: var(--wx-font-size-sm);
}
</style>
