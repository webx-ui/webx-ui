<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  confirm,
  localizedValue,
  toast,
  useLocales,
  WxActionBar,
  WxAlert,
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
import { blankValues, createCollectionsApi, formValues, payload, tagOf } from './api'
import { provideCollectionEditor } from './editor'
import { NAMESPACE, useCatalogCollectionsMessages } from './i18n'
import type { CollectionDetail, CollectionPreset } from './types'

/**
 * One collection (§6.2 of the collections spec): a head, `catalog.collection-form` under it — the
 * words and the look, the products with their preview, the history — and one button. A new one is
 * the same page at `…/collections/new`: its first save creates it and the address moves to its id.
 */
defineOptions({ name: 'WxCatalogCollectionEditor' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

const admin = useAdmin()
const api = createCollectionsApi(admin)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
useCatalogCollectionsMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const list = computed(() => `${props.base}/collections`)
const creating = computed(() => route.params.id === undefined || route.params.id === 'new')
const id = computed(() => (creating.value ? null : Number(route.params.id)))

const collection = ref<CollectionDetail | null>(null)
const values = ref<ScreenModel>({})
const snapshot = ref('')
const presets = ref<CollectionPreset[]>([])

const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => admin.can('catalog.manage'))
const trashed = computed(() => (collection.value?.deleted_at ?? null) !== null)
const locked = computed(() => !canManage.value || saving.value || trashed.value)

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value || creating.value ? 'unsaved' : 'saved'
})

const section = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'catalog-collections')?.title ??
    t('module.title'),
)

/** The title follows the field: a rename shows at the top as it is typed. */
const title = computed(() => {
  const written = values.value.title as LocalizedValue | string | null | undefined

  return localizedValue(written, locales.active.value, '') || t('panel.untitled')
})

provideCollectionEditor({ collection, values, locked, presets })
provideHistorySubject({ id: computed(() => collection.value?.id ?? null) })

function take(detail: CollectionDetail): void {
  collection.value = detail
  values.value = formValues(detail)
  snapshot.value = JSON.stringify(values.value)
}

function blank(): void {
  collection.value = null
  values.value = blankValues()
  snapshot.value = JSON.stringify(values.value)
}

async function load(): Promise<void> {
  errors.value = {}

  if (id.value === null) {
    blank()
    loading.value = false

    return
  }

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
  if (saving.value || !canManage.value) return

  saving.value = true
  errors.value = {}

  try {
    if (id.value === null) {
      const created = await api.create(payload(values.value))

      take(created)
      toast.success(t('panel.created'))
      await router.replace(`${list.value}/${created.id}`)
    } else {
      take(await api.save(id.value, payload(values.value)))
      toast.success(t('panel.saved'))
    }
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

async function copy(): Promise<void> {
  const tag = tagOf(collection.value?.key ?? '')

  try {
    await navigator.clipboard.writeText(tag)
    toast.success(t('panel.copied'))
  } catch {
    // No clipboard on this page: the words themselves are what the developer needs.
    toast.danger(tag)
  }
}

async function remove(): Promise<void> {
  const row = collection.value

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

async function restore(): Promise<void> {
  const row = collection.value

  if (!row) return

  try {
    take(await api.restore(row.id))
    toast.success(t('panel.restored'))
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
    if (dirty.value || creating.value) void save()
  }
}

useBodyKeys(onKeydown)

watch(id, (next, previous) => {
  // The first save of a new collection moves the address to its id: nothing to read again.
  if (previous === null && next !== null && collection.value?.id === next) return

  void load()
})

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

const actions = computed<ScreenAction[]>(() => {
  const row = collection.value

  if (!row) return []

  const copyTag: ScreenAction = {
    key: 'copy',
    label: t('panel.copy'),
    icon: 'copy',
    run: () => void copy(),
  }

  if (!canManage.value) return [copyTag]

  if (trashed.value) {
    return [
      { key: 'restore', label: t('panel.restore'), icon: 'refresh', run: () => void restore() },
    ]
  }

  return [
    copyTag,
    {
      key: 'delete',
      label: t('panel.delete'),
      icon: 'trash',
      danger: true,
      menu: true,
      run: () => void remove(),
    },
  ]
})
</script>

<template>
  <div class="wx-catalog-collection-editor" @keydown="onKeydown">
    <template v-if="loading">
      <wx-skeleton class="wx-catalog-collection-editor__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="6" /></wx-card>
    </template>

    <template v-else>
      <wx-screen-head
        divider
        :back="list"
        :back-label="section"
        :title="title"
        :subtitle="collection?.key"
        :actions="actions"
      >
        <template #trail>
          <wx-breadcrumb size="sm" :label="t('panel.trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="list">{{ section }}</wx-breadcrumb-item>
            <wx-breadcrumb-item current>{{ title }}</wx-breadcrumb-item>
          </wx-breadcrumb>
        </template>

        <template v-if="collection" #title-after>
          <wx-badge v-if="trashed" dot>{{ t('panel.in-bin') }}</wx-badge>
          <wx-badge v-else :type="collection.is_enabled ? 'success' : 'default'" dot size="sm">
            {{ t(collection.is_enabled ? 'panel.enabled' : 'panel.disabled') }}
          </wx-badge>
        </template>
      </wx-screen-head>

      <wx-alert v-if="collection?.attention" type="warning">
        {{ t(`collection.attention-${collection.attention}`) }}
      </wx-alert>

      <wx-screen
        v-model="values"
        name="catalog.collection-form"
        :errors="errors"
        :disabled="locked"
      />

      <wx-action-bar v-if="canManage && !trashed">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button type="primary" :loading="saving" :disabled="!dirty && !creating" @click="save">
          {{ t(creating ? 'panel.create' : 'panel.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-collection-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-catalog-collection-editor__ghost {
  max-width: 420px;
}
</style>
