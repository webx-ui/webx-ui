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
import { cleanSet, createLandingsApi, formValues } from './api'
import { provideLandingEditor } from './editor'
import { NAMESPACE, useCatalogLandingsMessages } from './i18n'
import type { LandingDetail, LandingFilters } from './types'

/**
 * One landing (§8.2 of the landings spec): a head, `catalog.landing-form` under it — base, set,
 * address, texts, order, recommended, SEO, history — and one button. A new landing is the same
 * page at `…/landings/new`: its first save creates it and the address moves to its id.
 */
defineOptions({ name: 'WxCatalogLandingEditor' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

const admin = useAdmin()
const api = createLandingsApi(admin)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
useCatalogLandingsMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const list = computed(() => `${props.base}/landings`)
const creating = computed(() => route.params.id === undefined || route.params.id === 'new')
const id = computed(() => (creating.value ? null : Number(route.params.id)))

const landing = ref<LandingDetail | null>(null)
const values = ref<ScreenModel>({})
const snapshot = ref('')
const suggested = ref<Record<string, string>>({})

const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => admin.can('catalog.manage'))
const trashed = computed(() => (landing.value?.deleted_at ?? null) !== null)
const locked = computed(() => !canManage.value || saving.value || trashed.value)

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value || creating.value ? 'unsaved' : 'saved'
})

const section = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'catalog-landings')?.title ??
    t('module.title'),
)

/** The name follows the field: a rename shows at the top as it is typed. */
const title = computed(() => {
  const written = values.value.name as LocalizedValue | string | null | undefined

  return localizedValue(written, locales.active.value, '') || t('panel.untitled')
})

const subtitle = computed(() => {
  const row = landing.value

  if (!row) return undefined

  return [row.category ?? t('landing.whole-catalog'), row.url ?? ''].filter(Boolean).join(' · ')
})

provideLandingEditor({ landing, values, locked, suggested, list: list.value })
provideHistorySubject({ id: computed(() => landing.value?.id ?? null) })

function take(detail: LandingDetail): void {
  landing.value = detail
  values.value = formValues(detail)
  snapshot.value = JSON.stringify(values.value)
}

function blank(): void {
  landing.value = null
  values.value = {
    category_id: route.query.category ? Number(route.query.category) : null,
    filters: {},
    name: {},
    slug: {},
    h1: {},
    text_above: {},
    text_below: {},
    sort: null,
    on_category: false,
    position: 0,
    recommended: [],
    seo: {},
  }
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

/** What a save sends: the form's fields, the set without its half-built facets. */
function payload(): Record<string, unknown> {
  return { ...values.value, filters: cleanSet(values.value.filters as LandingFilters) }
}

async function save(): Promise<void> {
  if (saving.value || !canManage.value) return

  saving.value = true
  errors.value = {}

  try {
    if (id.value === null) {
      const created = await api.create(payload())

      take(created)
      toast.success(t('panel.created'))
      await router.replace(`${list.value}/${created.id}`)
    } else {
      take(await api.save(id.value, payload()))
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

async function publish(on: boolean): Promise<void> {
  const row = landing.value

  if (!row) return

  try {
    const answer = await api.publish(row.id, on)

    // Only the state: what is being typed in the form stays.
    landing.value = { ...row, ...answer }
    toast.success(t(on ? 'panel.published-toast' : 'panel.unpublished-toast'))
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(): Promise<void> {
  const row = landing.value

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
  const row = landing.value

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
  // The first save of a new landing moves the address to its id — nothing to read again.
  if (previous === null && next !== null && landing.value?.id === next) return

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
  const row = landing.value

  if (!row || !canManage.value) return []

  if (trashed.value) {
    return [
      { key: 'restore', label: t('panel.restore'), icon: 'refresh', run: () => void restore() },
    ]
  }

  const leads: ScreenAction[] = []

  if (row.url) {
    leads.push({
      key: 'site',
      label: t('panel.open-site'),
      icon: 'external-link',
      run: () => void window.open(row.url ?? '', '_blank', 'noopener'),
    })
  }

  leads.push(
    row.is_published
      ? {
          key: 'unpublish',
          label: t('panel.unpublish'),
          icon: 'eye-off',
          run: () => void publish(false),
        }
      : { key: 'publish', label: t('panel.publish'), icon: 'eye', run: () => void publish(true) },
    {
      key: 'delete',
      label: t('panel.delete'),
      icon: 'trash',
      danger: true,
      menu: true,
      run: () => void remove(),
    },
  )

  return leads
})
</script>

<template>
  <div class="wx-catalog-landing-editor" @keydown="onKeydown">
    <template v-if="loading">
      <wx-skeleton class="wx-catalog-landing-editor__ghost" title :rows="1" />
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

        <template v-if="landing" #title-after>
          <wx-badge v-if="trashed" dot>{{ t('panel.in-bin') }}</wx-badge>
          <wx-badge v-else :type="landing.is_published ? 'success' : 'default'" dot size="sm">
            {{ t(landing.is_published ? 'panel.published' : 'panel.unpublished') }}
          </wx-badge>
        </template>
      </wx-screen-head>

      <wx-alert v-if="landing?.attention" type="warning">
        {{ t(`landing.attention-${landing.attention}`) }}
      </wx-alert>

      <wx-screen v-model="values" name="catalog.landing-form" :errors="errors" :disabled="locked" />

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
.wx-catalog-landing-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-catalog-landing-editor__ghost {
  max-width: 420px;
}
</style>
