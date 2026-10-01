<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useAdmin,
  useChunkedUpload,
  useErrorText,
  useTranslate,
  WxScreen,
  WxScreenHead,
} from '@webx-ui/module-admin'
import {
  toast,
  WxAlert,
  WxButton,
  WxCard,
  WxFormItem,
  WxInput,
  WxProgress,
  WxSegmented,
  WxSelect,
  WxStep,
  WxSteps,
  WxText,
  type SelectOption,
} from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'
import {
  createExchangeApi,
  EXCHANGE_PURPOSE,
  IMPORT_DEFAULTS,
  type ExchangeColumnInfo,
  type ExchangeInspection,
  type ExchangeProfile,
  type ExchangeSource,
} from './exchange'
import { useCatalogMessages } from './i18n'

/**
 * The import wizard (§8.1 of the exchange spec, screen `catalog.exchange-import`): a file — an
 * upload in pieces or an address the server downloads — then its columns matched to the
 * catalogue's with five rows of the file under each, then how the rows are written. The last
 * step checks the whole file without writing, or runs it; either way the run is followed in
 * «Exchange».
 *
 * The settings are a described screen: a project can take a setting away or fix it with a
 * patch, the way it does any form of the panel. A profile fills all three steps at once — its
 * columns are laid over the header of the file, and what the file has that the profile does not
 * know keeps the server's guess.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

const context = useAdmin()
const api = createExchangeApi(context)
const upload = useChunkedUpload({ admin: context })
const route = useRoute()
const router = useRouter()
useCatalogMessages()

const t = useTranslate('webx-catalog')
const message = useErrorText()

const step = ref(0)
const way = ref<'upload' | 'url'>('upload')
const url = ref('')
const source = ref<ExchangeSource | null>(null)
const fileName = ref('')
const inspection = ref<ExchangeInspection | null>(null)
const reading = ref(false)
const encoding = ref<string | null>(null)
const delimiter = ref<string | null>(null)

const columns = ref<ExchangeColumnInfo[]>([])
const profiles = ref<ExchangeProfile[]>([])
const profileId = ref<number | null>(null)
const mapping = ref<Record<string, string | null>>({})
const options = ref<ScreenModel>({ ...IMPORT_DEFAULTS })
const profileName = ref('')
const saving = ref(false)
const starting = ref<'run' | 'check' | null>(null)
const picker = ref<HTMLInputElement | null>(null)

const profile = computed(() => profiles.value.find((one) => one.id === profileId.value) ?? null)

onMounted(async () => {
  try {
    const [found, saved] = await Promise.all([api.columns(), api.profiles('import')])

    columns.value = found
    profiles.value = saved
  } catch (error) {
    toast.danger(message(error))
  }

  const wanted = Number(route.query.profile)

  if (Number.isInteger(wanted) && profiles.value.some((one) => one.id === wanted)) {
    chooseProfile(wanted)
  }
})

function chooseProfile(id: number | null): void {
  profileId.value = id

  const chosen = profiles.value.find((one) => one.id === id)

  options.value = { ...IMPORT_DEFAULTS, ...(chosen?.options ?? {}) }
  encoding.value = typeof chosen?.options.encoding === 'string' ? chosen.options.encoding : null
  delimiter.value = typeof chosen?.options.delimiter === 'string' ? chosen.options.delimiter : null

  if (inspection.value) mapping.value = mapped(inspection.value)
}

/* ------------------------------------------------------------------ file ----- */

const ways = computed(() => [
  { value: 'upload', label: t('panel.exchange-source-upload'), icon: 'upload' as const },
  { value: 'url', label: t('panel.exchange-source-url'), icon: 'link' as const },
])

const uploading = computed(
  () => upload.state.value === 'uploading' || upload.state.value === 'paused',
)

async function onFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  input.value = ''

  if (!file) return

  fileName.value = file.name
  inspection.value = null

  try {
    const id = await upload.start(file, EXCHANGE_PURPOSE)

    if (id === null) return

    source.value = { upload_id: id }
    await inspect()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function readUrl(): Promise<void> {
  const address = url.value.trim()

  if (address === '') return

  fileName.value = address
  source.value = { url: address }
  await inspect()
}

/** The header of the file as a profile names it, the server's guess for the rest. */
function mapped(found: ExchangeInspection): Record<string, string | null> {
  const saved = profile.value?.mapping

  if (!saved || Array.isArray(saved)) return { ...found.mapping }

  return Object.fromEntries(
    found.header
      .filter((cell) => cell.trim() !== '')
      .map((cell) => [cell, saved[cell] ?? found.mapping[cell] ?? null]),
  )
}

async function inspect(): Promise<void> {
  if (source.value === null) return

  reading.value = true

  try {
    const found = await api.inspect(source.value, {
      encoding: encoding.value ?? undefined,
      delimiter: delimiter.value ?? undefined,
    })

    inspection.value = found
    mapping.value = mapped(found)
    if (step.value === 0) step.value = 1
  } catch (error) {
    toast.danger(message(error))
  } finally {
    reading.value = false
  }
}

/* ------------------------------------------------------------------ columns ----- */

const encodings = computed<SelectOption[]>(() =>
  ['UTF-8', 'Windows-1252', 'Windows-1251', 'ISO-8859-1'].map((code) => ({
    value: code,
    label: code,
  })),
)

const delimiters = computed<SelectOption[]>(() => [
  { value: ',', label: ',' },
  { value: ';', label: ';' },
  { value: '\t', label: t('panel.exchange-delimiter-tab') },
])

/** Every column the caller may map, each translation a choice of its own: `name@de`. */
const targets = computed<SelectOption[]>(() =>
  columns.value.flatMap((column) => [
    { value: column.key, label: `${column.label} · ${column.key}` },
    ...column.locales.map((code) => ({
      value: `${column.key}@${code}`,
      label: `${column.label} (${code.toUpperCase()}) · ${column.key}@${code}`,
    })),
  ]),
)

const headers = computed(() =>
  (inspection.value?.header ?? [])
    .map((cell, index) => ({ cell: cell.trim(), index }))
    .filter((one) => one.cell !== ''),
)

/** A column of the catalogue goes into one header only: the ones taken elsewhere are off. */
function targetsFor(cell: string): SelectOption[] {
  const taken = new Set(
    Object.entries(mapping.value)
      .filter(([header, code]) => header !== cell && code)
      .map(([, code]) => code),
  )

  return targets.value.map((one) => ({ ...one, disabled: taken.has(String(one.value)) }))
}

function samples(index: number): string[] {
  return (inspection.value?.sample ?? []).map((row) => row[index] ?? '').filter((one) => one !== '')
}

const mappedCount = computed(() => Object.values(mapping.value).filter(Boolean).length)

const keyMissing = computed(
  () => !Object.values(mapping.value).includes(String(options.value.key ?? 'sku')),
)

function setTarget(cell: string, value: unknown): void {
  mapping.value = {
    ...mapping.value,
    [cell]: value === null || value === '' ? null : String(value),
  }
}

/* ------------------------------------------------------------------ settings ----- */

function plan(): {
  profile_id?: number
  mapping: Record<string, string | null>
  options: ScreenModel
} {
  return {
    ...(profileId.value !== null ? { profile_id: profileId.value } : {}),
    mapping: mapping.value,
    options: {
      ...options.value,
      ...(inspection.value?.format === 'csv'
        ? { encoding: encoding.value ?? undefined, delimiter: delimiter.value ?? undefined }
        : {}),
      format: inspection.value?.format,
    },
  }
}

async function start(dryRun: boolean): Promise<void> {
  if (source.value === null) return

  starting.value = dryRun ? 'check' : 'run'

  try {
    const run = await api.startImport(source.value, plan(), dryRun)

    toast.success(dryRun ? t('panel.exchange-checked') : t('panel.exchange-started'))
    void router.push({ path: `${props.base}/exchange`, query: { run: String(run.id) } })
  } catch (error) {
    toast.danger(message(error))
  } finally {
    starting.value = null
  }
}

async function saveProfile(): Promise<void> {
  const name = profileName.value.trim()

  if (name === '' || inspection.value === null) return

  saving.value = true

  try {
    const rest = plan()
    const created = await api.createProfile({
      name,
      direction: 'import',
      format: inspection.value.format,
      options: rest.options,
      mapping: Object.fromEntries(
        Object.entries(rest.mapping).filter((entry): entry is [string, string] => !!entry[1]),
      ),
    })

    profiles.value = [...profiles.value, created]
    profileId.value = created.id
    profileName.value = ''
    toast.success(t('panel.exchange-profile-saved'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    saving.value = false
  }
}

const profileOptions = computed<SelectOption[]>(() =>
  profiles.value.map((one) => ({ value: one.id, label: one.name })),
)

/** A profile that never ran is checked first: the button that writes is not the loud one. */
const checkFirst = computed(() => profile.value !== null && profile.value.last_run_id === null)

function go(index: number): void {
  if (index < step.value) step.value = index
}
</script>

<template>
  <div class="wx-catalog-import">
    <wx-screen-head
      :back="`${props.base}/exchange`"
      :back-label="t('panel.exchange-title')"
      :title="t('panel.exchange-import-title')"
      :subtitle="t('panel.exchange-import-help')"
    />

    <wx-card>
      <wx-steps
        :current="step"
        clickable
        :min-step-width="140"
        :aria-label="t('panel.exchange-import-title')"
        @change="go"
      >
        <wx-step
          :title="t('panel.exchange-step-file')"
          :description="t('panel.exchange-step-file-help')"
        />
        <wx-step
          :title="t('panel.exchange-step-mapping')"
          :description="t('panel.exchange-step-mapping-help')"
        />
        <wx-step
          :title="t('panel.exchange-step-settings')"
          :description="t('panel.exchange-step-settings-help')"
        />
      </wx-steps>
    </wx-card>

    <!-- 1. The file. -->
    <wx-card v-if="step === 0">
      <div class="wx-catalog-import__file">
        <wx-form-item v-if="profiles.length > 0" :label="t('panel.exchange-use-profile')">
          <wx-select
            :model-value="profileId"
            :options="profileOptions"
            clearable
            :placeholder="t('panel.exchange-use-profile-none')"
            :aria-label="t('panel.exchange-use-profile')"
            @update:model-value="
              (value: unknown) => chooseProfile((value as number | null) ?? null)
            "
          />
        </wx-form-item>

        <wx-segmented v-model="way" :options="ways" :aria-label="t('panel.exchange-step-file')" />

        <div v-if="way === 'upload'" class="wx-catalog-import__upload">
          <input ref="picker" type="file" accept=".csv,.txt,.xlsx" hidden @change="onFile" />
          <div v-if="uploading" class="wx-catalog-import__progress" role="status">
            <wx-text size="sm" weight="medium" truncate>{{ fileName }}</wx-text>
            <wx-progress
              :value="Math.round(upload.progress.value * 100)"
              :aria-label="t('panel.exchange-uploading')"
            />
            <div class="wx-catalog-import__row">
              <wx-button
                v-if="upload.state.value === 'uploading'"
                size="sm"
                variant="outline"
                @click="upload.pause()"
              >
                {{ t('panel.exchange-pause') }}
              </wx-button>
              <wx-button v-else size="sm" variant="outline" @click="upload.resume()">
                {{ t('panel.exchange-resume') }}
              </wx-button>
              <wx-button size="sm" variant="text" @click="upload.cancel()">
                {{ t('panel.exchange-cancel') }}
              </wx-button>
            </div>
          </div>
          <template v-else>
            <wx-button icon="upload" :loading="reading" @click="picker?.click()">
              {{ t('panel.exchange-choose-file') }}
            </wx-button>
            <wx-text size="sm" tone="muted">{{ t('panel.exchange-file-help') }}</wx-text>
          </template>
        </div>

        <form v-else class="wx-catalog-import__url" @submit.prevent="readUrl">
          <wx-form-item :label="t('panel.exchange-url')" :help="t('panel.exchange-url-help')">
            <wx-input v-model="url" type="url" placeholder="https://" />
          </wx-form-item>
          <wx-button
            native-type="submit"
            :loading="reading"
            :disabled="url.trim() === ''"
            class="wx-catalog-import__read"
          >
            {{ t('panel.exchange-read') }}
          </wx-button>
        </form>

        <wx-text v-if="inspection" size="sm" tone="muted">
          {{ fileName }} · {{ inspection.format.toUpperCase() }}
        </wx-text>
      </div>

      <div class="wx-catalog-import__nav">
        <span />
        <wx-button type="primary" :disabled="inspection === null" @click="step = 1">
          {{ t('panel.exchange-next') }}
        </wx-button>
      </div>
    </wx-card>

    <!-- 2. Its columns. -->
    <wx-card v-else-if="step === 1 && inspection">
      <div v-if="inspection.format === 'csv'" class="wx-catalog-import__read-as">
        <wx-text size="sm" tone="muted">{{ t('panel.exchange-read-as') }}</wx-text>
        <wx-select
          v-model="encoding"
          :options="encodings"
          clearable
          size="sm"
          :placeholder="`${t('panel.exchange-encoding')}: ${inspection.options.encoding ?? t('panel.exchange-detect')}`"
          :aria-label="t('panel.exchange-encoding')"
          class="wx-catalog-import__read-select"
        />
        <wx-select
          v-model="delimiter"
          :options="delimiters"
          clearable
          size="sm"
          :placeholder="`${t('panel.exchange-delimiter')}: ${inspection.options.delimiter === '\t' ? t('panel.exchange-delimiter-tab') : (inspection.options.delimiter ?? t('panel.exchange-detect'))}`"
          :aria-label="t('panel.exchange-delimiter')"
          class="wx-catalog-import__read-select"
        />
        <wx-button size="sm" variant="outline" icon="refresh" :loading="reading" @click="inspect">
          {{ t('panel.exchange-reread') }}
        </wx-button>
      </div>

      <wx-alert v-if="headers.length === 0" type="warning" :title="t('panel.exchange-map-empty')" />

      <div
        v-else
        class="wx-catalog-import__map"
        role="table"
        :aria-label="t('panel.exchange-step-mapping')"
      >
        <div class="wx-catalog-import__map-head" role="row">
          <span role="columnheader">{{ t('panel.exchange-map-file') }}</span>
          <span role="columnheader">{{ t('panel.exchange-map-catalog') }}</span>
        </div>
        <div v-for="one in headers" :key="one.cell" class="wx-catalog-import__map-row" role="row">
          <div class="wx-catalog-import__header" role="cell">
            <span class="wx-catalog-import__cell">
              {{ one.cell }}
              <span
                v-if="mapping[one.cell] && mapping[one.cell] === options.key"
                class="wx-catalog-import__key"
              >
                {{ t('panel.exchange-map-key') }}
              </span>
            </span>
            <ul v-if="samples(one.index).length > 0" class="wx-catalog-import__samples">
              <li v-for="(value, at) in samples(one.index)" :key="at">{{ value }}</li>
            </ul>
          </div>
          <div class="wx-catalog-import__target" role="cell">
            <wx-select
              :model-value="mapping[one.cell] ?? null"
              :options="targetsFor(one.cell)"
              clearable
              filterable
              :placeholder="t('panel.exchange-map-skip')"
              :aria-label="one.cell"
              @update:model-value="(value: unknown) => setTarget(one.cell, value)"
            />
          </div>
        </div>
      </div>

      <wx-alert
        v-if="headers.length > 0 && keyMissing"
        type="warning"
        :title="t('panel.exchange-map-key-missing', { key: String(options.key ?? 'sku') })"
      />

      <div class="wx-catalog-import__nav">
        <wx-button variant="outline" @click="step = 0">{{ t('panel.exchange-back') }}</wx-button>
        <wx-text size="sm" tone="muted">
          {{ t('panel.exchange-map-mapped', { count: mappedCount }) }}
        </wx-text>
        <wx-button type="primary" :disabled="mappedCount === 0" @click="step = 2">
          {{ t('panel.exchange-next') }}
        </wx-button>
      </div>
    </wx-card>

    <!-- 3. How it is written. -->
    <template v-else-if="step === 2 && inspection">
      <wx-alert v-if="checkFirst" type="info" :title="t('panel.exchange-check-first')" />
      <wx-alert
        v-if="keyMissing"
        type="warning"
        :title="t('panel.exchange-map-key-missing', { key: String(options.key ?? 'sku') })"
      />

      <wx-screen v-model="options" name="catalog.exchange-import" />

      <wx-card>
        <form class="wx-catalog-import__profile" @submit.prevent="saveProfile">
          <wx-form-item :label="t('panel.exchange-save-profile')">
            <wx-input
              v-model="profileName"
              :maxlength="120"
              :placeholder="t('panel.exchange-profile-name')"
            />
          </wx-form-item>
          <wx-button
            native-type="submit"
            variant="outline"
            :loading="saving"
            :disabled="profileName.trim() === ''"
            class="wx-catalog-import__read"
          >
            {{ t('panel.exchange-save') }}
          </wx-button>
        </form>

        <div class="wx-catalog-import__nav">
          <wx-button variant="outline" @click="step = 1">{{ t('panel.exchange-back') }}</wx-button>
          <div class="wx-catalog-import__row">
            <wx-button
              :type="checkFirst ? 'primary' : 'default'"
              :variant="checkFirst ? undefined : 'outline'"
              :loading="starting === 'check'"
              :disabled="starting !== null || keyMissing"
              @click="start(true)"
            >
              {{ t('panel.exchange-run-check') }}
            </wx-button>
            <wx-button
              :type="checkFirst ? 'default' : 'primary'"
              :variant="checkFirst ? 'outline' : undefined"
              :loading="starting === 'run'"
              :disabled="starting !== null || keyMissing"
              @click="start(false)"
            >
              {{ t('panel.exchange-run') }}
            </wx-button>
          </div>
        </div>
      </wx-card>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-import {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
  container-type: inline-size;
}

.wx-catalog-import__file {
  display: grid;
  gap: var(--wx-space-16);
  justify-items: start;
  max-width: 560px;
}

.wx-catalog-import__file > :deep(.wx-form-item),
.wx-catalog-import__url {
  justify-self: stretch;
}

.wx-catalog-import__upload {
  display: grid;
  gap: var(--wx-space-8);
  justify-items: start;
  justify-self: stretch;
}

.wx-catalog-import__progress {
  display: grid;
  gap: var(--wx-space-8);
  justify-self: stretch;
  min-width: 0;
}

.wx-catalog-import__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-catalog-import__url,
.wx-catalog-import__profile {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: var(--wx-space-8) var(--wx-space-12);
}

.wx-catalog-import__url > :deep(.wx-form-item),
.wx-catalog-import__profile > :deep(.wx-form-item) {
  flex: 1 1 240px;
  min-width: 0;
  margin-bottom: 0;
}

/* The field's help sits under the input; the button lines up with the input, not with it. */
.wx-catalog-import__read {
  margin-bottom: var(--wx-space-24);
}

.wx-catalog-import__profile .wx-catalog-import__read {
  margin-bottom: 0;
}

.wx-catalog-import__read-as {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  margin-bottom: var(--wx-space-16);
}

.wx-catalog-import__read-as > :deep(.wx-select) {
  width: 200px;
}

.wx-catalog-import__map {
  display: grid;
  min-width: 0;
}

.wx-catalog-import__map-head,
.wx-catalog-import__map-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: var(--wx-space-8) var(--wx-space-16);
  padding: var(--wx-space-10) 0;
  border-bottom: 1px solid var(--wx-border-muted);
}

.wx-catalog-import__map-head {
  padding-top: 0;
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
  color: var(--wx-text-muted);
}

.wx-catalog-import__header {
  display: grid;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-catalog-import__cell {
  font-family: var(--wx-font-family-mono);
  font-weight: var(--wx-font-weight-medium);
  overflow-wrap: anywhere;
}

.wx-catalog-import__samples {
  display: grid;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
}

.wx-catalog-import__samples li {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-catalog-import__target {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-import__target > :deep(.wx-select) {
  flex: 1 1 auto;
  min-width: 0;
}

.wx-catalog-import__key {
  display: inline-block;
  margin-inline-start: var(--wx-space-6);
  font-family: var(--wx-font-family-sans);
  vertical-align: middle;
  padding: var(--wx-space-2) var(--wx-space-8);
  border-radius: var(--wx-radius-full);
  background: var(--wx-color-primary-soft);
  color: var(--wx-color-primary);
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-medium);
}

.wx-catalog-import__nav {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
  margin-top: var(--wx-space-16);
}

/* A phone: the header of the file over its choice, not beside it. */
@container (max-width: 520px) {
  .wx-catalog-import__map-head {
    display: none;
  }

  .wx-catalog-import__map-row {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
