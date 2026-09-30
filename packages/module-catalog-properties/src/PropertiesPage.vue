<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  confirm,
  createModal,
  toast,
  WxAlert,
  WxEmpty,
  WxIcon,
  WxSelect,
  WxSkeleton,
  WxSortableList,
  WxText,
  WxTooltip,
  type IconName,
  type SelectOption,
  type TabItem,
  type TabValue,
} from '@webx-ui/core'
import {
  createCategoriesApi,
  useAdmin,
  useErrorText,
  useTranslate,
  WxListScreen,
  WxRowMenu,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
import { createPropertiesApi, GROUPS_API } from './api'
import { propertyName, wordsIn } from './format'
import { NAMESPACE, useCatalogPropertiesMessages } from './i18n'
import PropertyCreateDialog from './PropertyCreateDialog.vue'
import type { PropertyDetail, PropertyRow, PropertyType } from './types'

/**
 * The properties of the catalogue (§7.1 of the properties spec): every one on a line — name,
 * code, type, group, what it is used for as icons, how many products hold it — in the order they
 * are dragged into, which is the order of the filter's blocks where a category says nothing else.
 *
 * All of them at once rather than a page: a shop has tens of properties, a large one a couple of
 * hundred, and an order is dragged on the whole list or not at all. So the order is dragged only
 * while nothing narrows the list; a type or a group chosen, it is a list to find one in.
 *
 * The groups of the card are the button in the head — the panel's shared category screens.
 */
defineOptions({ name: 'WxCatalogPropertiesPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

const admin = useAdmin()
const api = createPropertiesApi(admin)
const groupsApi = createCategoriesApi(admin, GROUPS_API)
const router = useRouter()
useCatalogPropertiesMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const path = computed(() => `${props.base}/properties`)
const locale = computed(() => admin.i18n.state.locale)
const canManage = computed(() => admin.can('catalog.manage'))

const rows = ref<PropertyRow[]>([])
const loading = ref(true)
const view = ref<TabValue | undefined>('all')
const type = ref<PropertyType | null>(null)
const group = ref<number | null>(null)
const groups = ref<SelectOption[]>([])

const trashed = computed(() => view.value === 'deleted')
const narrowed = computed(() => type.value !== null || group.value !== null)

const title = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'catalog-properties')?.title ??
    t('module.title'),
)

const views = computed<TabItem[]>(() => [
  { value: 'all', label: t('panel.view-all') },
  { value: 'deleted', label: t('panel.view-deleted') },
])

const typeOptions = computed<SelectOption[]>(() =>
  (['select', 'number', 'text', 'bool'] as const).map((value) => ({
    value,
    label: t(`property.types.${value}`),
  })),
)

const create = createModal<PropertyDetail>(PropertyCreateDialog)

const actions = computed<ScreenAction[]>(() => {
  const list: ScreenAction[] = [
    {
      key: 'groups',
      label: t('panel.groups'),
      icon: 'folder',
      run: () => void router.push(`${path.value}/groups`),
    },
  ]

  if (canManage.value) {
    list.push({ key: 'new', label: t('panel.new'), icon: 'plus', primary: true, run: add })
  }

  return list
})

/** What a property is used for, as the icons of its line — the ones it has, each with its word. */
const FLAGS: { flag: keyof PropertyRow; icon: IconName }[] = [
  { flag: 'is_filterable', icon: 'filter' },
  { flag: 'is_searchable', icon: 'search' },
  { flag: 'in_card', icon: 'grid' },
  { flag: 'on_page', icon: 'file-text' },
  { flag: 'in_list', icon: 'list' },
]

function flagsOf(row: PropertyRow) {
  return FLAGS.filter(({ flag }) => row[flag] === true).map(({ flag, icon }) => ({
    icon,
    label: t(`property.${String(flag)}`),
  }))
}

async function load(): Promise<void> {
  loading.value = true

  try {
    const page = await api.list({
      trashed: trashed.value,
      type: type.value ?? undefined,
      group: group.value,
    })
    rows.value = page.data
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

async function loadGroups(): Promise<void> {
  try {
    groups.value = (await groupsApi.list()).data.map((row) => ({ value: row.id, label: row.name }))
  } catch {
    // The filter by group is a convenience; without its list the rest of the screen still works.
    groups.value = []
  }
}

watch([trashed, type, group], () => void load())
void load()
void loadGroups()

function open(row: PropertyRow): void {
  void router.push(`${path.value}/${row.id}`)
}

/** A name and a type in a dialog, and then the page: the type is chosen once (decision 1). */
async function add(): Promise<void> {
  const made = await create({})

  if (made !== undefined) void router.push(`${path.value}/${made.property.id}`)
}

async function remove(row: PropertyRow): Promise<void> {
  const name = propertyName(row, locale.value)
  const agreed = await confirm({
    title: t('panel.delete-title', { name }),
    message: t('panel.delete-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(t('panel.deleted'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function restore(row: PropertyRow): Promise<void> {
  try {
    await api.restore(row.id)
    toast.success(t('panel.restored'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

function menuOf(row: PropertyRow): RowAction[] {
  if (trashed.value) {
    return canManage.value
      ? [
          {
            key: 'restore',
            label: t('panel.restore'),
            icon: 'refresh',
            run: () => void restore(row),
          },
        ]
      : []
  }

  const items: RowAction[] = [
    { key: 'edit', label: t('panel.edit'), icon: 'edit', run: () => open(row) },
  ]

  if (canManage.value) {
    items.push({
      key: 'delete',
      label: t('panel.delete'),
      icon: 'trash',
      danger: true,
      run: () => void remove(row),
    })
  }

  return items
}

/** The whole order, every time; a failure puts the list back as the server has it. */
async function reorder(): Promise<void> {
  try {
    await api.reorder(rows.value.map((row) => row.id))
  } catch (error) {
    toast.danger(message(error, t('panel.reorder-failed')))
    await load()
  }
}
</script>

<template>
  <wx-list-screen
    v-model:view="view"
    class="wx-catalog-properties"
    :title="title"
    :views="views"
    :actions="actions"
    padding="sm"
  >
    <div class="wx-catalog-properties__filters">
      <wx-select
        v-model="type"
        :options="typeOptions"
        :placeholder="t('panel.type-any')"
        :aria-label="t('property.type')"
        clearable
        size="sm"
      />
      <wx-select
        v-model="group"
        :options="groups"
        :placeholder="t('panel.group-any')"
        :aria-label="t('property.group_id')"
        clearable
        filterable
        size="sm"
      />
    </div>

    <wx-skeleton v-if="loading" class="wx-catalog-properties__loading" :rows="5" />

    <wx-empty
      v-else-if="rows.length === 0"
      :title="narrowed || trashed ? t('panel.empty-found') : t('panel.empty')"
      :description="narrowed || trashed ? undefined : t('panel.empty-help')"
    />

    <template v-else>
      <wx-sortable-list
        v-model="rows"
        class="wx-catalog-properties__list"
        plain
        item-key="id"
        :item-label="(item: PropertyRow) => propertyName(item, locale)"
        :disabled="!canManage || narrowed || trashed"
        @move="reorder"
      >
        <template #default="{ item }">
          <component
            :is="trashed ? 'div' : 'router-link'"
            class="wx-catalog-property-row"
            :to="trashed ? undefined : `${path}/${(item as PropertyRow).id}`"
          >
            <span class="wx-catalog-property-row__name">
              <wx-text truncate weight="medium">{{
                propertyName(item as PropertyRow, locale)
              }}</wx-text>
              <span class="wx-catalog-property-row__flags">
                <wx-tooltip
                  v-for="flag in flagsOf(item as PropertyRow)"
                  :key="flag.icon"
                  :content="flag.label"
                >
                  <wx-icon :name="flag.icon" size="sm" :label="flag.label" />
                </wx-tooltip>
              </span>
            </span>
            <wx-text size="sm" tone="muted" truncate>
              {{
                [
                  wordsIn((item as PropertyRow).code, locale),
                  t(`property.types.${(item as PropertyRow).type}`),
                  (item as PropertyRow).group
                    ? wordsIn((item as PropertyRow).group!.title, locale)
                    : null,
                ]
                  .filter(Boolean)
                  .join(' · ')
              }}
            </wx-text>
          </component>
        </template>

        <template #actions="{ item }">
          <wx-text class="wx-catalog-property-row__count" size="sm" tone="muted">
            {{ t('panel.count', { count: (item as PropertyRow).products_count }) }}
          </wx-text>
          <wx-row-menu
            :actions="menuOf(item as PropertyRow)"
            :label="propertyName(item as PropertyRow, locale)"
          />
        </template>
      </wx-sortable-list>

      <wx-alert
        v-if="!trashed"
        class="wx-catalog-properties__note"
        type="info"
        :description="narrowed ? t('panel.order-narrowed') : t('panel.order')"
      />
    </template>
  </wx-list-screen>
</template>

<style>
.wx-catalog-properties__filters {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  padding: var(--wx-space-8);
}

.wx-catalog-properties__filters > * {
  flex: 0 1 220px;
  min-width: 0;
}

.wx-catalog-properties__loading {
  padding: var(--wx-space-8);
}

.wx-catalog-properties__list.is-plain .wx-sortable-list__row {
  padding-inline: var(--wx-space-8);
}

.wx-catalog-properties__list .wx-sortable-list__row:hover {
  background: var(--wx-bg-subtle);
  border-radius: var(--wx-radius-sm);
}

.wx-catalog-properties__note {
  margin-block-start: var(--wx-space-8);
}

.wx-catalog-property-row {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  align-items: flex-start;
  flex: 1 1 auto;
  min-width: 0;
  color: inherit;
  text-decoration: none;
}

.wx-catalog-property-row__name {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
  max-width: 100%;
}

.wx-catalog-property-row__flags {
  display: inline-flex;
  flex: none;
  gap: var(--wx-space-4);
  color: var(--wx-text-muted);
}

.wx-catalog-property-row__count {
  flex: none;
  font-variant-numeric: tabular-nums;
}
</style>
