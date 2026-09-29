<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { WxAlert, WxSkeleton, WxSortableList, WxSwitch, WxText } from '@webx-ui/core'
import { createCatalogApi } from './api'
import { useCatalogCategoryEditor } from './editor'
import { useCatalogMessages } from './i18n'
import { useFacetRegistry } from './store'
import type { FacetInfo, FacetSetting } from './types'

/**
 * `wx-catalog-facets`: which filters a category shows and in which order (§6.2).
 *
 * The value is `null` — the category takes the setting of its nearest ancestor that has one, or
 * every facet in the registry's order — or its own list. Off, the field says where the setting
 * comes from, because "inherited" alone leaves the reader to walk up the tree to find out what
 * that means. On, it is the list itself: dragged into order, each facet shown or not.
 *
 * Switching it on starts from what was inherited, not from a blank list: the reader wanted to
 * change one thing about the setting they were looking at.
 *
 * A facet registered after the setting was written is not in it, and is shown here switched off
 * — a new module does not get to change somebody else's filters on its own (§6.2). A key whose
 * module has gone is kept as it is: putting the module back brings the setting back with it.
 */
defineOptions({ name: 'WxCatalogFacetsField' })

const value = defineModel<FacetSetting[] | null>({ default: null })

const admin = useAdmin()
const api = createCatalogApi(admin)
const registry = useFacetRegistry(admin)
const editor = useCatalogCategoryEditor()
useCatalogMessages()

const t = useTranslate('webx-catalog')

const loading = ref(true)
const failed = ref(false)
/** The nearest ancestor with a setting of its own, or `null` — the registry's order. */
const source = ref<{ name: string; rows: FacetSetting[] } | null>(null)

const facets = computed<FacetInfo[]>(() => registry.facets.value ?? [])
const labels = computed(() => new Map(facets.value.map((facet) => [facet.key, facet.label])))

const own = computed(() => Array.isArray(value.value))
const locked = computed(() => editor?.locked.value ?? false)

interface Row extends FacetSetting {
  label: string
  known: boolean
}

/** The setting with every facet the registry has that it does not, switched off, at the end. */
function complete(rows: FacetSetting[]): FacetSetting[] {
  const named = new Set(rows.map((row) => row.key))

  return [
    ...rows.map((row) => ({ key: row.key, visible: row.visible })),
    ...facets.value
      .filter((facet) => !named.has(facet.key))
      .map((facet) => ({ key: facet.key, visible: false })),
  ]
}

const inherited = computed<FacetSetting[]>(() =>
  source.value === null
    ? facets.value.map((facet) => ({ key: facet.key, visible: true }))
    : complete(source.value.rows),
)

const rows = computed<Row[]>({
  get: () =>
    complete(value.value ?? []).map((row) => ({
      ...row,
      label: labels.value.get(row.key) ?? row.key,
      known: labels.value.has(row.key),
    })),
  set: (next) => {
    value.value = next.map((row) => ({ key: row.key, visible: row.visible }))
  },
})

/** What a visitor of the category sees while it inherits: the names, in order. */
const inheritedNames = computed(() =>
  inherited.value
    .filter((row) => row.visible && labels.value.has(row.key))
    .map((row) => labels.value.get(row.key))
    .join(', '),
)

function toggleOwn(on: boolean | string | number | null): void {
  value.value = on ? inherited.value.map((row) => ({ ...row })) : null
}

function show(key: string, visible: boolean | string | number | null): void {
  rows.value = rows.value.map((row) =>
    row.key === key ? { ...row, visible: Boolean(visible) } : row,
  )
}

/**
 * Whose setting this is. The server names the ancestor (`facets_from`), and one request reads
 * its rows — the setting is shown, not only named. A server that does not say is walked up the
 * tree from the parent until somebody has a setting of their own, one request per level.
 */
async function findSource(parent: number | null): Promise<void> {
  const from = editor?.facetsFrom.value

  source.value = null

  if (from === null) return

  if (from !== undefined) {
    const detail = await api.category(from.id)

    if (Array.isArray(detail.values.facets)) {
      source.value = { name: from.name, rows: detail.values.facets as FacetSetting[] }
    }

    return
  }

  let next = parent

  while (next !== null) {
    const detail = await api.category(next)
    const setting = detail.values.facets

    if (Array.isArray(setting)) {
      source.value = { name: detail.category.name, rows: setting as FacetSetting[] }

      return
    }

    next = detail.category.parent_id
  }
}

async function load(): Promise<void> {
  loading.value = true
  failed.value = false

  try {
    await Promise.all([registry.load(), findSource(editor?.category.value?.parent_id ?? null)])
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

/* A move or a save changes whose setting it is; the editor hands both over with the answer. */
watch(
  () => [editor?.category.value?.parent_id, JSON.stringify(editor?.facetsFrom.value)],
  (now, before) => {
    if (now[0] !== before[0] || now[1] !== before[1]) void load()
  },
)

onMounted(load)
</script>

<template>
  <div class="wx-catalog-facets">
    <wx-skeleton v-if="loading" :rows="3" />

    <wx-alert
      v-else-if="failed"
      type="warning"
      variant="soft"
      :description="t('panel.facets-failed')"
    />

    <template v-else>
      <div class="wx-catalog-facets__own">
        <wx-switch
          :model-value="own"
          :disabled="locked"
          :label="t('panel.facets-own')"
          @update:model-value="toggleOwn"
        />
        <wx-text size="sm" tone="muted">
          {{ own ? t('panel.facets-own-help') : t('panel.facets-inherited-help') }}
        </wx-text>
      </div>

      <div v-if="!own" class="wx-catalog-facets__inherited">
        <wx-text weight="medium">
          {{
            source ? t('panel.facets-inherited', { name: source.name }) : t('panel.facets-default')
          }}
        </wx-text>
        <wx-text v-if="inheritedNames" size="sm" tone="muted">{{ inheritedNames }}</wx-text>
        <wx-text v-else-if="facets.length === 0" size="sm" tone="muted">
          {{ t('panel.facets-empty') }}
        </wx-text>
      </div>

      <wx-sortable-list
        v-else
        v-model="rows"
        size="sm"
        item-key="key"
        item-label="label"
        :disabled="locked"
        :empty-text="t('panel.facets-empty')"
      >
        <template #default="{ item }">
          <span class="wx-catalog-facets__name" :class="{ 'is-off': !(item as Row).visible }">
            {{ (item as Row).label }}
          </span>
          <wx-text v-if="!(item as Row).known" size="sm" tone="muted">
            {{ t('panel.facets-unknown') }}
          </wx-text>
        </template>

        <template #actions="{ item }">
          <wx-switch
            size="sm"
            :model-value="(item as Row).visible"
            :disabled="locked || !(item as Row).known"
            :aria-label="`${t('panel.facets-shown')}: ${(item as Row).label}`"
            @update:model-value="(on) => show((item as Row).key, on)"
          />
        </template>
      </wx-sortable-list>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-facets {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-catalog-facets__own,
.wx-catalog-facets__inherited {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}

.wx-catalog-facets :deep(.wx-sortable-list__content) {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-facets__name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Hidden facets stay in the list — their place is part of the setting — but read as switched off. */
.wx-catalog-facets__name.is-off {
  color: var(--wx-text-muted);
}
</style>
