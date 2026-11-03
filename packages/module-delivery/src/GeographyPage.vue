<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  confirm,
  toast,
  WxAlert,
  WxBadge,
  WxButton,
  WxEmpty,
  WxFormItem,
  WxSelect,
  WxSkeleton,
  WxSortableList,
  WxSwitch,
  WxTable,
  WxText,
  createModal,
  type TabItem,
  type TabValue,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import {
  rowMenuWidth,
  useAdmin,
  useErrorText,
  WxListScreen,
  WxRowMenu,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import LocalityDialog from './LocalityDialog.vue'
import RegionDialog from './RegionDialog.vue'
import type { Countries, CountryRow, LocalityPage, LocalityRow, RegionRow } from './types'

/**
 * The shared geography by hand (decision 42, §14.2), three views of one screen:
 *
 * - **countries** — those the shop delivers to, in the order of the checkout's choice, each on or
 *   off, with the carrier that leads it (§4.4), each removed by its `···`; a new one is switched
 *   on from the ISO list;
 * - **regions** of a country — a table sorted by name or by localities, made and edited in one
 *   dialog, deleted while empty;
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
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loadingCountries.value = false
  }
}

void loadCountries()

/*
 * The regions are of one country: they start at the first one on. The localities do not — there
 * no country is every country that is on, and a country picked for them once and kept in the
 * address would hide the towns of one switched on later.
 */
watch(
  [view, data],
  () => {
    const list = data.value?.countries ?? []

    if (view.value === 'regions' && country.value === '' && list.length > 0) {
      country.value = (list.find((one) => one.active) ?? list[0])!.code
    }
  },
  { immediate: true },
)

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
  ...(data.value?.carriers ?? []),
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

/*
 * Out of the site (decision 42). The dialog says beforehand which of the two it will be: a
 * country with regions or localities is only switched off, as points and orders may name them.
 */
async function removeCountry(row: CountryRow): Promise<void> {
  const held = row.regions > 0 || row.localities > 0
  const agreed = await confirm({
    title: t('country.remove-title', { name: row.name }),
    message: held ? t('country.remove-held') : t('country.remove-text'),
    confirmText: held ? t('country.disable') : t('country.remove'),
    cancelText: t('common.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    const removal = await api.removeCountry(row.code)

    toast.success(t(removal === 'deleted' ? 'country.removed' : 'country.deactivated'))
    if (removal === 'deleted' && country.value === row.code) country.value = ''
    await loadCountries()
  } catch (error) {
    toast.danger(message(error))
  }
}

function countryActions(row: CountryRow): RowAction[] {
  if (!canManage.value) return []

  return [
    {
      key: 'remove',
      icon: 'trash',
      label: t('country.remove'),
      danger: true,
      run: () => void removeCountry(row),
    },
  ]
}

/* ---------------------------------------------------------------------------- regions ----- */

const regions = ref<RegionRow[]>([])
const loadingRegions = ref(false)
const regionDialog = createModal<boolean, { country: string; region?: RegionRow | null }>(
  RegionDialog,
)

/*
 * A country's regions in its own order, dragged like the countries are: the selects of a
 * locality and the filter read them in it. The list is in its new order already; a refusal puts
 * the database's back on screen.
 */
async function orderRegions(): Promise<void> {
  try {
    await api.orderRegions(
      country.value,
      regions.value.map((one) => one.id),
    )
  } catch (error) {
    toast.danger(message(error, t('common.order-failed')))
    await loadRegions()
  }
}

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

async function editRegion(region: RegionRow | null): Promise<void> {
  if (country.value === '') return

  if (await regionDialog({ country: country.value, region })) {
    toast.success(t('region.saved'))
    await loadRegions()
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

/* A region with localities cannot go; the item stays, greyed, and says why (§20). */
function regionActions(region: RegionRow): RowAction[] {
  if (!canManage.value) return []

  return [
    { key: 'edit', icon: 'edit', label: t('region.edit'), run: () => void editRegion(region) },
    {
      key: 'delete',
      icon: 'trash',
      label: t('common.delete'),
      danger: true,
      disabled: region.localities > 0,
      hint: region.localities > 0 ? t('region.delete-held') : undefined,
      run: () => void removeRegion(region),
    },
  ]
}

/* ------------------------------------------------------------------------- localities ----- */

const page = ref<LocalityPage | null>(null)
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
  { key: 'actions', label: '', width: rowMenuWidth, align: 'right' },
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

/*
 * «No localities yet» only when there are none: a search or a filter finds «nothing», and says
 * where the same search does find towns — another country, or one switched off.
 */
const emptyText = computed(() => {
  const elsewhere = page.value?.elsewhere ?? []
  const names = (list: typeof elsewhere) => list.map((one) => one.name).join(', ')
  const on = elsewhere.filter((one) => one.active)
  const off = elsewhere.filter((one) => !one.active)
  const filtered =
    last.search !== '' || !!country.value || !!region.value || !!source.value || !!state.value
  const lines = [
    filtered ? t('locality.nothing-found') : t('locality.empty'),
    on.length > 0 ? t('locality.found-in', { countries: names(on) }) : '',
    off.length > 0 ? t('locality.found-off', { countries: names(off) }) : '',
  ]

  return lines.filter((line) => line !== '').join(' ')
})

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

async function deactivate(row: LocalityRow): Promise<void> {
  try {
    await api.deactivateLocality(row.id)
    toast.success(t('locality.switched-off'))
    await loadLocalities()
  } catch (error) {
    toast.danger(message(error))
  }
}

/*
 * «Delete» of a row by decision 42: the dialog says beforehand which of the two it will be, so
 * it asks the locality who holds it first — the list does not carry that for every row.
 */
async function removeLocality(row: LocalityRow): Promise<void> {
  let held: string[] = []

  try {
    held = (await api.locality(row.id)).locality.held_by
  } catch (error) {
    toast.danger(message(error))

    return
  }

  const agreed = await confirm({
    title: t('locality.delete-title', { name: row.name }),
    message:
      held.length > 0
        ? t('locality.held-text', { what: held.join(', ') })
        : t('locality.delete-text'),
    confirmText: held.length > 0 ? t('locality.deactivate') : t('common.delete'),
    cancelText: t('common.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    const { removal } = await api.removeLocality(row.id)

    toast.success(t(removal === 'deleted' ? 'locality.deleted' : 'locality.deactivated'))
    await loadLocalities()
  } catch (error) {
    toast.danger(message(error))
  }
}

/* The row's own actions, as every table of the panel has them; the click on the row edits. */
function localityActions(row: LocalityRow): RowAction[] {
  if (!canManage.value) return []

  return [
    { key: 'edit', icon: 'edit', label: t('common.edit'), run: () => edit(row.id) },
    row.active
      ? {
          key: 'off',
          icon: 'eye-off',
          label: t('locality.deactivate'),
          run: () => void deactivate(row),
        }
      : { key: 'on', icon: 'eye', label: t('locality.activate'), run: () => void activate(row) },
    {
      key: 'delete',
      icon: 'trash',
      label: t('common.delete'),
      danger: true,
      run: () => void removeLocality(row),
    },
  ]
}

function saved(): void {
  void loadLocalities()
}

/* The action of the open view, a word without an icon like every head of the panel. */
const actions = computed<ScreenAction[]>(() => {
  if (!canManage.value) return []
  if (view.value === 'localities') {
    return [{ key: 'new', label: t('locality.new'), primary: true, run: () => edit(null) }]
  }
  if (view.value === 'regions' && country.value !== '') {
    return [{ key: 'new', label: t('region.add'), primary: true, run: () => void editRegion(null) }]
  }

  return []
})
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
        :empty-text="emptyText"
        :filters-count="(country ? 1 : 0) + (region ? 1 : 0) + (source ? 1 : 0) + (state ? 1 : 0)"
        @row-click="(row: LocalityRow) => edit(row.id)"
        @state-change="loadLocalities"
      >
        <template #filters>
          <wx-form-item :label="t('locality.country')">
            <wx-select
              v-model="country"
              :options="[{ value: '', label: t('locality.any-country') }, ...countryOptions]"
              size="sm"
              filterable
              data-test="locality-country"
            />
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
        <template #cell-kind="{ row }">{{ row.kind_label }}</template>
        <template #cell-region="{ row }">
          {{ row.region?.name ?? '' }}
          <!-- Every country that is on: the code tells two of one name apart. -->
          <span v-if="!country" class="wx-delivery-geo__muted"> · {{ row.country }}</span>
        </template>
        <template #cell-source="{ row }">
          <wx-badge size="sm">{{ t(`locality.sources.${row.source}`) }}</wx-badge>
        </template>
        <template #cell-bindings="{ row }">
          <span class="wx-delivery-geo__muted">{{
            row.bindings.map((one) => one.carrier_title).join(', ')
          }}</span>
        </template>
        <template #cell-active="{ row }">
          <wx-badge v-if="row.active" type="success" dot>{{ t('common.active') }}</wx-badge>
          <wx-button v-else-if="canManage" size="sm" variant="outline" @click.stop="activate(row)">
            {{ t('locality.activate') }}
          </wx-button>
          <wx-badge v-else dot>{{ t('common.inactive') }}</wx-badge>
        </template>
        <template #cell-actions="{ row }">
          <wx-row-menu :actions="localityActions(row)" :label="row.name" />
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

      <!-- What «leading» means, once over the list rather than a tooltip on every row. -->
      <wx-text
        v-if="countries.length > 0 && leaderOptions.length > 1"
        class="wx-delivery-geo__note"
        tone="muted"
        size="sm"
        data-test="leader-help"
      >
        {{ t('country.leader-help') }}
      </wx-text>

      <wx-skeleton v-if="loadingCountries && countries.length === 0" :rows="3" />
      <wx-empty
        v-else-if="countries.length === 0"
        :title="t('country.empty')"
        :description="t('country.empty-help')"
      />
      <wx-sortable-list
        v-else
        v-model="countries"
        class="wx-delivery-geo__list"
        plain
        hoverable
        item-key="code"
        :handle="canManage ? 'grip' : 'row'"
        :item-label="(item: CountryRow) => item.name"
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
            <span v-if="leaderOptions.length > 1" class="wx-delivery-geo__leader">
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
          <span class="wx-delivery-geo__country-actions">
            <wx-switch
              :model-value="item.active"
              :disabled="!canManage"
              :aria-label="item.active ? t('country.disable') : t('country.enable')"
              @update:model-value="toggle(item.code, Boolean($event))"
            />
            <wx-row-menu :actions="countryActions(item)" :label="item.name" />
          </span>
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
          :aria-label="t('locality.country')"
          class="wx-delivery-geo__choose"
        />
      </div>

      <wx-empty v-if="country === ''" :title="t('region.country-first')" />
      <wx-skeleton v-else-if="loadingRegions && regions.length === 0" :rows="3" />
      <wx-empty v-else-if="regions.length === 0" :title="t('region.empty')" />
      <template v-else>
        <wx-sortable-list
          v-model="regions"
          class="wx-delivery-geo__list"
          plain
          hoverable
          item-key="id"
          :handle="canManage ? 'grip' : 'row'"
          :item-label="(item: RegionRow) => item.name"
          :disabled="!canManage"
          :aria-label="t('region.title')"
          data-test="regions"
          @move="orderRegions"
        >
          <template #default="{ item }">
            <component
              :is="canManage ? 'button' : 'div'"
              :type="canManage ? 'button' : undefined"
              class="wx-delivery-geo__region"
              @click="canManage && editRegion(item)"
            >
              <span class="wx-delivery-geo__name">{{ item.name }}</span>
              <span v-if="item.code" class="wx-delivery-geo__muted">{{ item.code }}</span>
              <span class="wx-delivery-geo__muted wx-delivery-geo__count">
                {{ t('country.localities') }}: {{ item.localities }}
              </span>
            </component>
          </template>
          <template #actions="{ item }">
            <wx-row-menu :actions="regionActions(item)" :label="item.name" />
          </template>
        </wx-sortable-list>

        <wx-alert
          v-if="canManage && regions.length > 1"
          class="wx-delivery-geo__hint"
          type="info"
          :description="t('common.drag')"
        />
      </template>
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

.wx-delivery-geo__note {
  display: block;
  max-width: 720px;
  margin-bottom: var(--wx-space-12);
}

/*
 * The rows stand off the card's edge and their own grip, like the panel's other dragged lists
 * (FAQ, team): the `···` at the end then has room around it instead of touching the border.
 */
.wx-delivery-geo__list :deep(.wx-sortable-list__row) {
  gap: var(--wx-space-8);
  padding-inline: var(--wx-space-4) var(--wx-space-8);
}

/* The switch and the `···` are two controls, not one: a step between them like a toolbar's. */
.wx-delivery-geo__country-actions {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-12);
}

.wx-delivery-geo__region {
  display: flex;
  flex: 1 1 auto;
  flex-wrap: wrap;
  align-items: baseline;
  gap: var(--wx-space-4) var(--wx-space-12);
  width: 100%;
  min-width: 0;
  padding-block: var(--wx-space-10);
  padding-inline: 0;
  border: 0;
  background: none;
  font: inherit;
  color: inherit;
  text-align: start;
}

button.wx-delivery-geo__region {
  cursor: pointer;
}

.wx-delivery-geo__count {
  margin-inline-start: auto;
}

.wx-delivery-geo__hint {
  margin-top: var(--wx-space-12);
}
</style>
