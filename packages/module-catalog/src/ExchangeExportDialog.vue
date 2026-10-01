<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxButton,
  WxCheckboxGroup,
  WxDialog,
  WxFormItem,
  WxSegmented,
  WxSelect,
  WxSkeleton,
  WxSpace,
  WxText,
  type CheckboxOption,
  type SelectOption,
} from '@webx-ui/core'
import {
  columnCode,
  createExchangeApi,
  type ExchangeColumnInfo,
  type ExchangeFormat,
  type ExchangeProfile,
  type ExchangeRun,
} from './exchange'
import { useCatalogMessages } from './i18n'
import type { BulkSelection } from './types'

/**
 * An export (§8.1 of the exchange spec): a saved profile, or columns chosen here with the
 * languages their translations go out in, and a format. What goes out is the list's selection —
 * ticked rows or everything a filter finds — or, opened from «Exchange», the whole catalogue.
 *
 * The run is handed back as the server answered it; the opener decides where to follow it.
 */
const props = withDefaults(
  defineProps<{
    /** What the list picked; the whole catalogue outside «Deleted» when left out. */
    selection?: BulkSelection | null
    /** How many products that is, said above the choice; nothing is said without it. */
    count?: number | null
  }>(),
  { selection: null, count: null },
)

const { open, resolve, dismiss } = useModal<ExchangeRun>()

const admin = useAdmin()
const api = createExchangeApi(admin)
useCatalogMessages()

const t = useTranslate('webx-catalog')
const message = useErrorText()

const columns = ref<ExchangeColumnInfo[] | null>(null)
const profiles = ref<ExchangeProfile[]>([])
const profileId = ref<number | null>(null)
const chosen = ref<string[]>([])
const languages = ref<string[]>([])
const format = ref<ExchangeFormat>('xlsx')
const starting = ref(false)

onMounted(async () => {
  try {
    const [found, saved] = await Promise.all([api.columns(), api.profiles('export')])

    columns.value = found
    profiles.value = saved
    // Everything by default: unticking what is not wanted is quicker than finding what is.
    chosen.value = found.map((column) => column.key)
  } catch (error) {
    toast.danger(message(error))
    columns.value = []
  }
})

const profileOptions = computed<SelectOption[]>(() =>
  profiles.value.map((profile) => ({ value: profile.id, label: profile.name })),
)

const columnOptions = computed<CheckboxOption[]>(() =>
  (columns.value ?? []).map((column) => ({ value: column.key, label: column.label })),
)

/** Every other language a translated column has: the site's, as the server lists them. */
const languageOptions = computed<CheckboxOption[]>(() => {
  const codes = new Set((columns.value ?? []).flatMap((column) => column.locales))

  return [...codes].map((code) => ({ value: code, label: code.toUpperCase() }))
})

const formats = computed(() => [
  { value: 'xlsx', label: 'XLSX' },
  { value: 'csv', label: 'CSV' },
])

/** The codes in the order of the columns, each translation right after its column. */
const codes = computed<string[]>(() =>
  (columns.value ?? [])
    .filter((column) => chosen.value.includes(column.key))
    .flatMap((column) => [
      column.key,
      ...(column.localized
        ? column.locales
            .filter((code) => languages.value.includes(code))
            .map((code) => columnCode(column.key, code))
        : []),
    ]),
)

const ready = computed(() => profileId.value !== null || codes.value.length > 0)

async function start(): Promise<void> {
  if (!ready.value) {
    toast.warning(t('panel.exchange-export-none'))

    return
  }

  starting.value = true

  try {
    const run = await api.startExport(
      props.selection ?? { query: {} },
      profileId.value !== null
        ? { profile_id: profileId.value }
        : { columns: codes.value, format: format.value },
    )

    resolve(run)
  } catch (error) {
    toast.danger(message(error))
  } finally {
    starting.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.exchange-export-title')" :width="520">
    <wx-text tone="muted" size="sm">
      {{
        count !== null
          ? t('panel.exchange-export-selected', { count })
          : t('panel.exchange-export-all')
      }}
    </wx-text>

    <wx-skeleton v-if="columns === null" :rows="4" class="wx-catalog-export__body" />

    <div v-else class="wx-catalog-export__body">
      <wx-form-item v-if="profiles.length > 0" :label="t('panel.exchange-profile')">
        <wx-select
          v-model="profileId"
          :options="profileOptions"
          clearable
          :placeholder="t('panel.exchange-export-pick')"
          :aria-label="t('panel.exchange-profile')"
        />
      </wx-form-item>

      <template v-if="profileId === null">
        <wx-form-item
          :label="t('panel.exchange-export-columns')"
          :help="t('panel.exchange-export-columns-help')"
        >
          <div class="wx-catalog-export__columns">
            <wx-checkbox-group v-model="chosen" :options="columnOptions" />
          </div>
        </wx-form-item>

        <wx-form-item
          v-if="languageOptions.length > 0"
          :label="t('panel.exchange-export-languages')"
          :help="t('panel.exchange-export-languages-help')"
        >
          <wx-checkbox-group v-model="languages" inline :options="languageOptions" />
        </wx-form-item>

        <wx-form-item :label="t('panel.exchange-format')">
          <wx-segmented
            v-model="format"
            :options="formats"
            :aria-label="t('panel.exchange-format')"
          />
        </wx-form-item>
      </template>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button
          type="primary"
          icon="download"
          :loading="starting"
          :disabled="columns === null || !ready"
          @click="start"
        >
          {{ t('panel.exchange-export-start') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-catalog-export__body {
  display: grid;
  gap: var(--wx-space-16);
  margin-top: var(--wx-space-16);
}

/* Two columns of names where the dialog is wide enough: twenty ticks in one line is a scroll. */
.wx-catalog-export__columns > :deep(.wx-checkbox-group) {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: var(--wx-space-8) var(--wx-space-16);
}
</style>
