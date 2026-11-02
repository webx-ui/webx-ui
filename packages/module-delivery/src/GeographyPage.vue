<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  confirm,
  toast,
  WxBadge,
  WxButton,
  WxEmpty,
  WxFormItem,
  WxInput,
  WxSelect,
  WxSkeleton,
  WxSortableList,
  WxSwitch,
  WxTable,
  type TabItem,
  type TabValue,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import {
  useAdmin,
  useDefaultLocale,
  useErrorText,
  WxListScreen,
  type ScreenAction,
} from '@webx-ui/module-admin'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import LocalityDialog from './LocalityDialog.vue'
import type { Countries, LocalityRow, Page, RegionRow } from './types'

/**
 * The shared geography by hand (decision 42, §14.2), three views of one screen:
 *
 * - **countries** — those the shop delivers to, in the order of the checkout's choice, each on or
 *   off, with the carrier that leads it (§4.4); a new one is switched on from the ISO list;
 * - **regions** of a country — made, renamed, deleted while empty;
 * - **localities** — a page at a time, by country, region, source and state, searched by any
 *   of their names; made by hand, edited, switched off or deleted.
 *
 * The view, the country and the filters are in the address.
 */
const context = useAdmin()
const api = createDeliveryApi(context)
const route = useRoute()
const router = useRouter()
const t = useDeliveryWords()
const message = useErrorText()
const main = useDefaultLocale()

const canManage = computed(() => context.can('delivery.manage'))
const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'delivery-geography')?.title ??
    t('sections.geography'),
)

const views = computed<TabItem[]>(() => [
  { value: 'localities', label: t('country.localities-tab') },
  { value: 'countries', label: t('country.title') },
  { value: 'regions', label: t('region.title') },
])

const view = computed<TabValue>({
  get: () =>
    route.query.view === 'countries' || route.query.view === 'regions'
      ? route.query.view
      : 'localities',
  set: (value) =>
    void router.replace({
      query: { ...route.query, view: value === 'localities' ? undefined : String(value) },
    }),
})

const country = computed<string>({
  get: () => (typeof route.query.country === 'string' ? route.query.country : ''),
  set: (value) =>
    void router.replace({
      query: { ...route.query, country: value || undefined, region: undefined },
    }),
})

/* -------------------------------------------------------------------------- countries ----- */

const data = ref<Countries | null>(null)
const loadingCountries = ref(false)
const adding = ref<string | null>(null)

async function loadCountries(): Promise<void> {
  loadingCountries.value = true

  try {
    data.value = await api.countries()

    // The first country on is where the other views start.
    if (country.value === '' && data.value.countries.length > 0) {
      country.value = (data.value.countries.find((one) => one.active) ??
        data.value.countries[0])!.code
    }
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loadingCountries.value = false
  }
}

void loadCountries()

const countries = ref<Countries['countries']>([])
watch(data, (now) => (countries.value = now?.countries ?? []))

const countryOptions = computed(() =>
  countries.value.map((one) => ({
    value: one.code,
    label: `${one.name} (${one.code})${one.active ? '' : ` · ${t('country.off')}`}`,
  })),
)

const choices = computed(() => {
  const known = new Set(countries.value.filter((one) => one.active).map((one) => one.code))

  return (data.value?.choices ?? [])
    .filter((one) => !known.has(one.code))
    .map((one) => ({ value: one.code, label: `${one.name} (${one.code})` }))
})

const leaderOptions = computed(() => [
  { value: '', label: t('country.leader-none') },
  ...(data.value?.carriers ?? []).map((key) => ({ value: key, label: key })),
])

async function enable(code: string | null): Promise<void> {
  if (!code) return

  try {
    await api.enableCountry(code)
    adding.value = null
    country.value = code
    await loadCountries()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function toggle(code: string, on: boolean): Promise<void> {
  try {
    if (on) await api.enableCountry(code)
    else {
      await api.disableCountry(code)
      toast.info(t('country.disabled'))
    }
    await loadCountries()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function lead(code: string, carrier: string): Promise<void> {
  try {
    await api.lead(code, carrier === '' ? null : carrier)
    toast.success(t('common.saved'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await loadCountries()
  }
}

async function orderCountries(): Promise<void> {
  try {
    await api.orderCountries(countries.value.map((one) => one.code))
  } catch (error) {
    toast.danger(message(error, t('common.order-failed')))
    await loadCountries()
  }
}

/* ---------------------------------------------------------------------------- regions ----- */

const regions = ref<RegionRow[]>([])
const loadingRegions = ref(false)
const regionName = ref('')
const regionCode = ref('')
const regionErrors = ref<Record<string, string[]>>({})
const renaming = ref<number | null>(null)
const renamed = ref('')

async function loadRegions(): Promise<void> {
  if (country.value === '') {
    regions.value = []

    return
  }

  loadingRegions.value = true

  try {
    regions.value = await api.regions(country.value)
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loadingRegions.value = false
  }
}

watch(
  [country, view],
  () => {
    if (view.value === 'regions' || view.value === 'localities') void loadRegions()
  },
  { immediate: true },
)

async function addRegion(): Promise<void> {
  regionErrors.value = {}

  try {
    await api.createRegion(
      country.value,
      { [main.value]: regionName.value.trim() },
      regionCode.value.trim() || null,
    )
    regionName.value = ''
    regionCode.value = ''
    toast.success(t('region.saved'))
    await loadRegions()
  } catch (error) {
    regionErrors.value = (error as { errors?: Record<string, string[]> }).errors ?? {}
    if (Object.keys(regionErrors.value).length === 0) toast.danger(message(error))
  }
}

async function rename(region: RegionRow): Promise<void> {
  try {
    await api.saveRegion(region.id, {
      names: { ...region.names, [main.value]: renamed.value.trim() },
    })
    renaming.value = null
    toast.success(t('region.saved'))
    await loadRegions()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function removeRegion(region: RegionRow): Promise<void> {
  const agreed = await confirm({
    title: t('region.delete-title', { name: region.name }),
    message: t('region.delete-text'),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeRegion(region.id)
    toast.success(t('region.deleted'))
    await loadRegions()
  } catch (error) {
    toast.danger(message(error))
  }
}

/* ------------------------------------------------------------------------- localities ----- */

const page = ref<Page<LocalityRow> | null>(null)
const loadingLocalities = ref(false)
const region = ref<number | null>(null)
const source = ref('')
const state = ref('')
const editing = ref<number | null>(null)
const editorOpen = ref(false)

const columns = computed<TableColumn<LocalityRow>[]>(() => [
  { key: 'name', label: t('locality.names'), minWidth: 160 },
  { key: 'kind', label: t('locality.kind'), width: 120, hideBelow: 560 },
  { key: 'region', label: t('locality.region'), minWidth: 140, hideBelow: 640 },
  { key: 'source', label: t('locality.source'), width: 120, hideBelow: 760 },
  { key: 'bindings', label: t('locality.bindings'), minWidth: 120, hideBelow: 900 },
  { key: 'weight', label: t('locality.weight'), width: 90, align: 'right', hideBelow: 900 },
  { key: 'active', label: t('locality.active'), width: 110 },
])

const sourceOptions = computed(() => [
  { value: '', label: t('locality.any-source') },
  { value: 'manual', label: t('locality.sources.manual') },
  { value: 'import', label: t('locality.sources.import') },
  { value: 'carrier', label: t('locality.sources.carrier') },
])

const stateOptions = computed(() => [
  { value: '', label: t('common.any-state') },
  { value: 'active', label: t('common.active') },
  { value: 'inactive', label: t('common.inactive') },
])

const regionOptions = computed(() =>
  regions.value.map((one) => ({ value: one.id, label: one.name })),
)

let last: TableState = { page: 1, perPage: 25, sort: null, search: '' }

async function loadLocalities(next: TableState = last): Promise<void> {
  last = next
  loadingLocalities.value = true

  try {
    page.value = await api.localities({
      page: next.page,
      per_page: next.perPage,
      q: next.search,
      country: country.value,
      region: region.value,
      source: source.value,
      state: state.value,
    })
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loadingLocalities.value = false
  }
}

watch([country, region, source, state], () => {
  if (view.value === 'localities') void loadLocalities({ ...last, page: 1 })
})
watch(country, () => (region.value = null))

function edit(id: number | null): void {
  editing.value = id
  editorOpen.value = true
}

async function activate(row: LocalityRow): Promise<void> {
  try {
    await api.activateLocality(row.id)
    toast.success(t('locality.activated'))
    await loadLocalities()
  } catch (error) {
    toast.danger(message(error))
  }
}

function saved(): void {
  void loadLocalities()
}

const actions = computed<ScreenAction[]>(() =>
  canManage.value && view.value === 'localities'
    ? [{ key: 'new', label: t('locality.new'), icon: 'plus', primary: true, run: () => edit(null) }]
    : [],
)
</script>

<template>
  <wx-list-screen v-model:view="view" :title="title" :views="views" :actions="actions">
    <!-- Localities -->
    <template v-if="view === 'localities'">
      <wx-table
        :data="page"
        :columns="columns"
        row-key="id"
        searchable
        clickable
        hover
        flush
        :loading="loadingLocalities"
        :search-placeholder="t('locality.search')"
        :empty-text="t('locality.empty')"
        :filters-count="(region ? 1 : 0) + (source ? 1 : 0) + (state ? 1 : 0)"
        @row-click="(row: LocalityRow) => edit(row.id)"
        @state-change="loadLocalities"
      >
        <template #filters>
          <wx-form-item :label="t('locality.country')">
            <wx-select v-model="country" :options="countryOptions" size="sm" filterable />
          </wx-form-item>
          <wx-form-item :label="t('locality.region')">
            <wx-select
              v-model="region"
              :options="regionOptions"
              :placeholder="t('locality.any-region')"
              size="sm"
              filterable
              clearable
            />
          </wx-form-item>
          <wx-form-item :label="t('locality.source')">
            <wx-select v-model="source" :options="sourceOptions" size="sm" />
          </wx-form-item>
          <wx-form-item :label="t('locality.active')">
            <wx-select v-model="state" :options="stateOptions" size="sm" />
          </wx-form-item>
        </template>

        <template #cell-name="{ row }">
          <span class="wx-delivery-geo__name">{{ row.name }}</span>
          <span v-if="row.district" class="wx-delivery-geo__muted"> · {{ row.district }}</span>
        </template>
        <template #cell-kind="{ row }">{{ row.kind }}</template>
        <template #cell-region="{ row }">{{ row.region?.name ?? '' }}</template>
        <template #cell-source="{ row }">
          <wx-badge size="sm">{{ t(`locality.sources.${row.source}`) }}</wx-badge>
        </template>
        <template #cell-bindings="{ row }">
          <span class="wx-delivery-geo__muted">{{
            row.bindings.map((one) => one.carrier).join(', ')
          }}</span>
        </template>
        <template #cell-active="{ row }">
          <wx-badge v-if="row.active" type="success" dot>{{ t('common.active') }}</wx-badge>
          <wx-button v-else-if="canManage" size="sm" variant="outline" @click.stop="activate(row)">
            {{ t('locality.activate') }}
          </wx-button>
          <wx-badge v-else dot>{{ t('common.inactive') }}</wx-badge>
        </template>
      </wx-table>

      <locality-dialog
        :id="editing"
        v-model:open="editorOpen"
        :country="country || null"
        @saved="saved"
        @removed="loadLocalities()"
      />
    </template>

    <!-- Countries -->
    <template v-else-if="view === 'countries'">
      <div v-if="canManage" class="wx-delivery-geo__bar">
        <wx-select
          v-model="adding"
          :options="choices"
          filterable
          :placeholder="t('country.choose')"
          :aria-label="t('country.add')"
          class="wx-delivery-geo__choose"
          data-test="country-choice"
        />
        <wx-button
          type="primary"
          :disabled="!adding"
          data-test="country-enable"
          @click="enable(adding)"
        >
          {{ t('country.enable') }}
        </wx-button>
      </div>

      <wx-skeleton v-if="loadingCountries && countries.length === 0" :rows="3" />
      <wx-empty
        v-else-if="countries.length === 0"
        :title="t('country.empty')"
        :description="t('country.empty-help')"
      />
      <wx-sortable-list
        v-else
        v-model="countries"
        plain
        hoverable
        item-key="code"
        :handle="canManage ? 'grip' : 'row'"
        :item-label="(item: Countries['countries'][number]) => item.name"
        :disabled="!canManage"
        @move="orderCountries"
      >
        <template #default="{ item }">
          <div class="wx-delivery-geo__country">
            <span class="wx-delivery-geo__name">{{ item.name }}</span>
            <span class="wx-delivery-geo__muted">{{ item.code }}</span>
            <span class="wx-delivery-geo__muted">
              {{ t('country.regions') }}: {{ item.regions }} · {{ t('country.localities') }}:
              {{ item.localities }}
            </span>
            <span class="wx-delivery-geo__leader">
              <span class="wx-delivery-geo__muted">{{ t('country.leader') }}</span>
              <wx-select
                size="sm"
                :model-value="item.leader ?? ''"
                :options="leaderOptions"
                :disabled="!canManage || item.leader_fixed"
                :aria-label="t('country.leader')"
                @update:model-value="lead(item.code, String($event ?? ''))"
              />
            </span>
          </div>
        </template>
        <template #actions="{ item }">
          <wx-switch
            :model-value="item.active"
            :disabled="!canManage"
            :aria-label="item.active ? t('country.disable') : t('country.enable')"
            @update:model-value="toggle(item.code, Boolean($event))"
          />
        </template>
      </wx-sortable-list>
    </template>

    <!-- Regions -->
    <template v-else>
      <div class="wx-delivery-geo__bar">
        <wx-select
          v-model="country"
          :options="countryOptions"
          filterable
          :placeholder="t('country.choose')"
          class="wx-delivery-geo__choose"
        />
      </div>

      <div v-if="canManage && country" class="wx-delivery-geo__bar">
        <wx-form-item :label="t('region.name')" :error="regionErrors.names?.[0]">
          <wx-input v-model="regionName" :maxlength="120" data-test="region-name" />
        </wx-form-item>
        <wx-form-item :label="t('region.code')" :help="t('region.code-help')">
          <wx-input v-model="regionCode" :maxlength="8" />
        </wx-form-item>
        <wx-button
          type="primary"
          icon="plus"
          :disabled="regionName.trim() === ''"
          data-test="region-add"
          @click="addRegion"
        >
          {{ t('region.add') }}
        </wx-button>
      </div>

      <wx-skeleton v-if="loadingRegions" :rows="3" />
      <wx-empty v-else-if="country === ''" :title="t('region.country-first')" />
      <wx-empty v-else-if="regions.length === 0" :title="t('region.empty')" />
      <ul v-else class="wx-delivery-geo__regions">
        <li v-for="one in regions" :key="one.id" class="wx-delivery-geo__region">
          <template v-if="renaming === one.id">
            <wx-input v-model="renamed" size="sm" class="wx-delivery-geo__rename" />
            <wx-button size="sm" type="primary" @click="rename(one)">{{
              t('common.save')
            }}</wx-button>
            <wx-button size="sm" variant="text" @click="renaming = null">{{
              t('common.cancel')
            }}</wx-button>
          </template>
          <template v-else>
            <span class="wx-delivery-geo__name">{{ one.name }}</span>
            <span v-if="one.code" class="wx-delivery-geo__muted">{{ one.code }}</span>
            <span class="wx-delivery-geo__muted"
              >{{ t('country.localities') }}: {{ one.localities }}</span
            >
            <span class="wx-delivery-geo__gap" />
            <template v-if="canManage">
              <wx-button
                size="sm"
                variant="text"
                icon="edit"
                @click="((renaming = one.id), (renamed = one.name))"
              >
                {{ t('region.rename') }}
              </wx-button>
              <wx-button
                size="sm"
                variant="text"
                icon="trash"
                :disabled="one.localities > 0"
                :aria-label="t('common.delete')"
                @click="removeRegion(one)"
              />
            </template>
          </template>
        </li>
      </ul>
    </template>
  </wx-list-screen>
</template>

<style scoped>
.wx-delivery-geo__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: var(--wx-space-8) var(--wx-space-12);
  margin-bottom: var(--wx-space-16);
}

.wx-delivery-geo__bar > :deep(.wx-select),
.wx-delivery-geo__choose {
  flex: 0 1 320px;
  min-width: 0;
}

.wx-delivery-geo__bar :deep(.wx-form-item) {
  margin-bottom: 0;
}

.wx-delivery-geo__name {
  font-weight: var(--wx-font-weight-medium);
}

.wx-delivery-geo__muted {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-delivery-geo__country {
  display: flex;
  flex: 1 1 auto;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-12);
  min-width: 0;
  padding-block: var(--wx-space-8);
}

.wx-delivery-geo__leader {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-8);
  margin-inline-start: auto;
}

.wx-delivery-geo__leader > :deep(.wx-select) {
  width: 180px;
}

.wx-delivery-geo__regions {
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-delivery-geo__region {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-8) 0;
  border-bottom: 1px solid var(--wx-border-default);
}

.wx-delivery-geo__rename {
  flex: 1 1 200px;
}

.wx-delivery-geo__gap {
  flex: 1 1 auto;
}
</style>
