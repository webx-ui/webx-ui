<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  confirm,
  localizedValue,
  toast,
  useLocales,
  WxActionBar,
  WxBadge,
  WxBreadcrumb,
  WxBreadcrumbItem,
  WxButton,
  WxCard,
  WxSkeleton,
  type LocalizedValue,
} from '@webx-ui/core'
import {
  provideHistorySubject,
  useAdmin,
  useBodyKeys,
  useErrorText,
  useTranslate,
  WxSaveState,
  WxScreen,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import { createPropertiesApi } from './api'
import { providePropertyEditor } from './editor'
import { propertyName, wordsIn } from './format'
import { NAMESPACE, useCatalogPropertiesMessages } from './i18n'
import type { PropertyDetail, PropertyInterval, PropertyRow } from './types'

/**
 * One property: a head, `catalog.property-form` under it, and one button (§7.1).
 *
 * The button saves «Main» — the property's own fields. «Values» and «Intervals» are records of
 * their own and save themselves, as the gallery of a product does: a reference book of three
 * hundred colours is not a value of a form, and a value merged away cannot wait for a button.
 */
defineOptions({ name: 'WxCatalogPropertyEditor' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

const admin = useAdmin()
const api = createPropertiesApi(admin)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
useCatalogPropertiesMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const id = computed(() => Number(route.params.id))
const list = computed(() => `${props.base}/properties`)

const property = ref<PropertyRow | null>(null)
const values = ref<ScreenModel>({})
const intervals = ref<PropertyInterval[]>([])
const snapshot = ref('')

const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => admin.can('catalog.manage'))
const locked = computed(() => !canManage.value || saving.value)

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value ? 'unsaved' : 'saved'
})

const section = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'catalog-properties')?.title ??
    t('module.title'),
)

/** The name follows the field rather than the answer: a rename shows at the top as it is typed. */
const title = computed(() => {
  const written = values.value.title as LocalizedValue | string | null | undefined
  const saved = property.value ? propertyName(property.value, admin.i18n.state.locale) : ''

  return localizedValue(written, locales.active.value, saved) || t('panel.untitled')
})

const subtitle = computed(() => {
  const row = property.value

  if (!row) return undefined

  return [wordsIn(row.code, locales.active.value), t(`property.types.${row.type}`)]
    .filter(Boolean)
    .join(' · ')
})

providePropertyEditor({ property, values, intervals, locked })
provideHistorySubject({ id: computed(() => property.value?.id ?? null) })

function take(detail: PropertyDetail): void {
  property.value = detail.property
  values.value = detail.values
  intervals.value = detail.intervals
  snapshot.value = JSON.stringify(detail.values)
}

async function load(): Promise<void> {
  loading.value = true

  try {
    take(await api.get(id.value))
  } catch (error) {
    toast.danger(message(error))
    void router.push(list.value)
  } finally {
    loading.value = false
  }
}

async function save(): Promise<void> {
  if (!property.value || saving.value || !canManage.value) return

  saving.value = true
  errors.value = {}

  try {
    take(await api.save(property.value.id, values.value))
    toast.success(t('panel.saved'))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors && Object.keys(body.errors).length > 0) {
      errors.value = body.errors
      toast.danger(t('panel.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

async function remove(): Promise<void> {
  const row = property.value

  if (!row) return

  const agreed = await confirm({
    title: t('panel.delete-title', { name: title.value }),
    message: t('panel.delete-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(t('panel.deleted'))
    snapshot.value = current.value
    void router.push(list.value)
  } catch (error) {
    toast.danger(message(error))
  }
}

function leaveGuard(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

function onKeydown(event: KeyboardEvent): void {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
    event.preventDefault()
    if (dirty.value) void save()
  }
}

useBodyKeys(onKeydown)

watch(id, () => void load())

onMounted(() => {
  void load()
  window.addEventListener('beforeunload', leaveGuard)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', leaveGuard)
})

onBeforeRouteLeave(async () => {
  if (!dirty.value || !canManage.value) return true

  return await confirm({
    title: t('panel.leave-title'),
    message: t('panel.leave-text'),
    confirmText: t('panel.leave'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })
})

const actions = computed<ScreenAction[]>(() =>
  property.value && canManage.value && property.value.deleted_at === null
    ? [
        {
          key: 'delete',
          label: t('panel.delete'),
          icon: 'trash',
          danger: true,
          menu: true,
          run: () => void remove(),
        },
      ]
    : [],
)
</script>

<template>
  <div class="wx-catalog-property-editor" @keydown="onKeydown">
    <template v-if="loading || !property">
      <wx-skeleton class="wx-catalog-property-editor__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="6" /></wx-card>
    </template>

    <template v-else>
      <wx-screen-head
        divider
        :back="list"
        :back-label="section"
        :title="title"
        :subtitle="subtitle"
        :actions="actions"
      >
        <template #trail>
          <wx-breadcrumb size="sm" :label="t('panel.trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="list">{{ section }}</wx-breadcrumb-item>
            <wx-breadcrumb-item current>{{ title }}</wx-breadcrumb-item>
          </wx-breadcrumb>
        </template>

        <template v-if="property.deleted_at !== null" #title-after>
          <wx-badge dot>{{ t('panel.in-bin') }}</wx-badge>
        </template>
      </wx-screen-head>

      <wx-screen
        v-model="values"
        name="catalog.property-form"
        :errors="errors"
        :disabled="locked"
      />

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button type="primary" :loading="saving" :disabled="!dirty" @click="save">
          {{ t('panel.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-property-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-catalog-property-editor__ghost {
  max-width: 420px;
}
</style>
