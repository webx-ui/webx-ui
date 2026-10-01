<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxScreen,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  toast,
  WxAction,
  WxActionBar,
  WxButton,
  WxCard,
  WxEmpty,
  WxHeading,
  WxInput,
  WxSelect,
  WxSkeleton,
  WxText,
  type SelectOption,
} from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'
import {
  createExchangeApi,
  IMPORT_DEFAULTS,
  type ExchangeColumnInfo,
  type ExchangeDirection,
  type ExchangeProfile,
  type ExchangeProfileInput,
} from './exchange'
import { useCatalogMessages } from './i18n'

/**
 * One profile of the exchange (§8.1): its head (name, format, how a CSV is read) and, by
 * direction, either how an import writes and which header goes into which column, or the
 * columns an export writes in their order.
 *
 * The head and the import's settings are the two described screens the wizard uses too, so a
 * setting a project took away is gone from both. The columns are this page's own: a mapping is
 * a list of pairs the server checks against the columns the caller may write.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

const context = useAdmin()
const api = createExchangeApi(context)
const route = useRoute()
const router = useRouter()
useCatalogMessages()

const t = useTranslate('webx-catalog')
const message = useErrorText()

const list = computed(() => `${props.base}/exchange/profiles`)
const id = computed(() => (route.params.id === 'new' ? null : Number(route.params.id)))
const canManage = computed(() => context.can('catalog.manage'))

const loaded = ref(false)
const profile = ref<ExchangeProfile | null>(null)
const columns = ref<ExchangeColumnInfo[]>([])
const head = ref<ScreenModel>({})
const options = ref<ScreenModel>({ ...IMPORT_DEFAULTS })
const pairs = ref<{ header: string; code: string | null }[]>([])
const codes = ref<string[]>([])
const adding = ref<string | null>(null)
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)

const direction = computed<ExchangeDirection>(
  () => profile.value?.direction ?? (route.query.direction === 'export' ? 'export' : 'import'),
)

onMounted(async () => {
  try {
    const [found, current] = await Promise.all([
      api.columns(),
      id.value === null ? Promise.resolve(null) : api.profile(id.value),
    ])

    columns.value = found
    profile.value = current
    fill(current)
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loaded.value = true
  }
})

function fill(current: ExchangeProfile | null): void {
  const read = current?.options ?? {}

  head.value = {
    name: current?.name ?? '',
    format: current?.format ?? (direction.value === 'export' ? 'xlsx' : 'csv'),
    direction: direction.value,
    encoding: typeof read.encoding === 'string' ? read.encoding : null,
    delimiter: typeof read.delimiter === 'string' ? read.delimiter : null,
  }
  options.value = { ...IMPORT_DEFAULTS, ...read }

  const mapping = current?.mapping ?? (direction.value === 'export' ? [] : {})

  if (Array.isArray(mapping)) {
    codes.value = [...mapping]
  } else {
    pairs.value = Object.entries(mapping).map(([header, code]) => ({ header, code }))
  }

  if (direction.value === 'import' && pairs.value.length === 0) {
    pairs.value = [{ header: 'sku', code: 'sku' }]
  }
}

/** Every column, each translation a choice of its own: `name@de`. */
const targets = computed<SelectOption[]>(() =>
  columns.value.flatMap((column) => [
    { value: column.key, label: `${column.label} · ${column.key}` },
    ...column.locales.map((code) => ({
      value: `${column.key}@${code}`,
      label: `${column.label} (${code.toUpperCase()}) · ${column.key}@${code}`,
    })),
  ]),
)

function labelOf(code: string): string {
  return targets.value.find((one) => one.value === code)?.label ?? code
}

const spare = computed(() =>
  targets.value.filter((one) => !codes.value.includes(String(one.value))),
)

function addCode(value: unknown): void {
  if (typeof value === 'string' && !codes.value.includes(value))
    codes.value = [...codes.value, value]
  adding.value = null
}

function moveCode(index: number, by: -1 | 1): void {
  const next = [...codes.value]
  const [moved] = next.splice(index, 1)

  next.splice(index + by, 0, moved!)
  codes.value = next
}

function values(): ExchangeProfileInput {
  const read =
    head.value.format === 'csv'
      ? {
          ...(head.value.encoding ? { encoding: String(head.value.encoding) } : {}),
          ...(head.value.delimiter ? { delimiter: String(head.value.delimiter) } : {}),
        }
      : {}

  return {
    name: String(head.value.name ?? '').trim(),
    format: head.value.format as ExchangeProfileInput['format'],
    ...(profile.value === null ? { direction: direction.value } : {}),
    options:
      direction.value === 'import'
        ? { ...options.value, encoding: undefined, delimiter: undefined, ...read }
        : {},
    mapping:
      direction.value === 'import'
        ? Object.fromEntries(
            pairs.value
              .filter((pair) => pair.header.trim() !== '' && pair.code)
              .map((pair) => [pair.header.trim(), pair.code!]),
          )
        : codes.value,
  }
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    const saved =
      profile.value === null
        ? await api.createProfile(values())
        : await api.saveProfile(profile.value.id, values())

    toast.success(t('panel.exchange-profile-saved'))

    if (profile.value === null) {
      profile.value = saved
      void router.replace(`${list.value}/${saved.id}`)
    } else {
      profile.value = saved
    }
  } catch (error) {
    const refused = (error as { body?: { errors?: Record<string, string[]> } }).body?.errors

    if (refused) errors.value = refused
    toast.danger(message(error))
  } finally {
    saving.value = false
  }
}

async function remove(): Promise<void> {
  if (profile.value === null) return

  const sure = await confirm({
    title: t('panel.exchange-profile-delete-title', { name: profile.value.name }),
    message: t('panel.exchange-profile-delete-text'),
    confirmText: t('panel.exchange-delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!sure) return

  try {
    await api.removeProfile(profile.value.id)
    toast.success(t('panel.exchange-profile-deleted'))
    void router.push(list.value)
  } catch (error) {
    toast.danger(message(error))
  }
}

const title = computed(() => profile.value?.name || t('panel.exchange-profile-new'))

const actions = computed<ScreenAction[]>(() => {
  const all: ScreenAction[] = []

  if (profile.value !== null && profile.value.direction === 'import' && canManage.value) {
    all.push({
      key: 'run',
      label: t('panel.exchange-profile-run'),
      icon: 'upload',
      run: () =>
        void router.push({
          path: `${props.base}/exchange/import`,
          query: { profile: String(profile.value!.id) },
        }),
    })
  }

  if (profile.value !== null && canManage.value) {
    all.push({
      key: 'delete',
      label: t('panel.exchange-delete'),
      icon: 'trash',
      danger: true,
      menu: true,
      run: () => void remove(),
    })
  }

  return all
})
</script>

<template>
  <div class="wx-catalog-profile">
    <template v-if="!loaded">
      <wx-skeleton title :rows="1" class="wx-catalog-profile__ghost" />
      <wx-card><wx-skeleton :rows="6" /></wx-card>
    </template>

    <template v-else>
      <wx-screen-head
        divider
        :back="list"
        :back-label="t('panel.exchange-profiles-title')"
        :title="title"
        :subtitle="t(`panel.exchange-${direction}`)"
        :actions="actions"
      />

      <wx-screen
        v-model="head"
        name="catalog.exchange-profile"
        :errors="errors"
        :disabled="!canManage"
      />

      <template v-if="direction === 'import'">
        <wx-screen
          v-model="options"
          name="catalog.exchange-import"
          :errors="errors"
          :disabled="!canManage"
        />

        <wx-card>
          <wx-heading :level="3" size="md">{{ t('panel.exchange-profile-mapping') }}</wx-heading>
          <wx-text size="sm" tone="muted">{{ t('panel.exchange-profile-mapping-help') }}</wx-text>

          <div class="wx-catalog-profile__pairs">
            <div v-for="(pair, index) in pairs" :key="index" class="wx-catalog-profile__pair">
              <wx-input
                v-model="pair.header"
                :placeholder="t('panel.exchange-profile-header')"
                :aria-label="t('panel.exchange-profile-header')"
                :disabled="!canManage"
              />
              <wx-select
                v-model="pair.code"
                :options="targets"
                filterable
                clearable
                :placeholder="t('panel.exchange-map-skip')"
                :aria-label="t('panel.exchange-map-catalog')"
                :disabled="!canManage"
              />
              <wx-action
                v-if="canManage"
                icon="close"
                :label="t('panel.exchange-profile-remove')"
                @click="pairs.splice(index, 1)"
              />
            </div>
          </div>

          <wx-button
            v-if="canManage"
            size="sm"
            variant="outline"
            icon="plus"
            @click="pairs.push({ header: '', code: null })"
          >
            {{ t('panel.exchange-profile-add') }}
          </wx-button>
        </wx-card>
      </template>

      <wx-card v-else>
        <wx-heading :level="3" size="md">{{ t('panel.exchange-export-columns') }}</wx-heading>
        <wx-text size="sm" tone="muted">{{ t('panel.exchange-export-columns-help') }}</wx-text>

        <wx-empty v-if="codes.length === 0" :description="t('panel.exchange-export-none')" />
        <ol v-else class="wx-catalog-profile__codes">
          <li v-for="(code, index) in codes" :key="code" class="wx-catalog-profile__code">
            <span class="wx-catalog-profile__code-label">{{ labelOf(code) }}</span>
            <template v-if="canManage">
              <wx-action
                icon="arrow-up"
                :label="t('panel.exchange-profile-up')"
                :disabled="index === 0"
                @click="moveCode(index, -1)"
              />
              <wx-action
                icon="arrow-down"
                :label="t('panel.exchange-profile-down')"
                :disabled="index === codes.length - 1"
                @click="moveCode(index, 1)"
              />
              <wx-action
                icon="close"
                :label="t('panel.exchange-profile-remove')"
                @click="codes.splice(index, 1)"
              />
            </template>
          </li>
        </ol>

        <div v-if="canManage" class="wx-catalog-profile__add">
          <wx-select
            :model-value="adding"
            :options="spare"
            filterable
            :placeholder="t('panel.exchange-profile-add')"
            :aria-label="t('panel.exchange-profile-add')"
            @update:model-value="addCode"
          />
        </div>
      </wx-card>

      <wx-action-bar v-if="canManage">
        <wx-button type="primary" :loading="saving" @click="save">
          {{ t('panel.exchange-save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-profile {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
  container-type: inline-size;
}

.wx-catalog-profile__ghost {
  max-width: 420px;
}

.wx-catalog-profile__pairs {
  display: grid;
  gap: var(--wx-space-8);
  margin: var(--wx-space-16) 0 var(--wx-space-12);
}

.wx-catalog-profile__pair {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-catalog-profile__codes {
  display: grid;
  gap: var(--wx-space-4);
  margin: var(--wx-space-16) 0 var(--wx-space-12);
  padding: 0;
  list-style: none;
  counter-reset: code;
}

.wx-catalog-profile__code {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
  padding: var(--wx-space-4) var(--wx-space-8);
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-subtle);
  counter-increment: code;
}

.wx-catalog-profile__code::before {
  content: counter(code);
  min-width: var(--wx-space-24);
  color: var(--wx-text-muted);
  font-variant-numeric: tabular-nums;
}

.wx-catalog-profile__code-label {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-catalog-profile__add {
  max-width: 360px;
}

@container (max-width: 520px) {
  .wx-catalog-profile__pair {
    grid-template-columns: minmax(0, 1fr) auto;
  }

  .wx-catalog-profile__pair > :deep(.wx-select) {
    grid-column: 1;
  }
}
</style>
