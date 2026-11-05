<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  confirm,
  toast,
  WxActionBar,
  WxAlert,
  WxBadge,
  WxBreadcrumb,
  WxBreadcrumbItem,
  WxButton,
  WxCard,
  WxSkeleton,
} from '@webx-ui/core'
import {
  useAdmin,
  useBodyKeys,
  useDefaultLocale,
  useErrorText,
  useTranslate,
  WxSaveState,
  WxScreen,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import { createFeedsApi, formValues, payloadOf } from './api'
import { provideFeedEditor } from './editor'
import { NAMESPACE, useCatalogFeedsMessages } from './i18n'
import type { FeedDetail, FeedsMeta, FeedValues, FeedVariables, FeedWarning } from './types'

/**
 * One feed (§11 of the feeds spec): a head, `catalog.feed-form` under it and one button. A new feed
 * is the same page at `…/feeds/new`, filled from the preset in its address: the form shows the copy
 * unsaved, and the first save creates the feed and moves the address to its id.
 *
 * A save the file would not survive is refused with the form still open (§8.1): the words go under
 * the part they are about, on whichever tab that is.
 */
defineOptions({ name: 'WxCatalogFeedEditor' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

const admin = useAdmin()
const api = createFeedsApi(admin)
const route = useRoute()
const router = useRouter()
const defaultLocale = useDefaultLocale()
useCatalogFeedsMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const list = computed(() => `${props.base}/feeds`)
const creating = computed(() => route.params.id === undefined || route.params.id === 'new')
const id = computed(() => (creating.value ? null : Number(route.params.id)))

const feed = ref<FeedDetail | null>(null)
const values = ref<FeedValues>(blankValues())
const meta = ref<FeedsMeta | null>(null)
const variables = ref<FeedVariables | null>(null)
const snapshot = ref('')
const warnings = ref<FeedWarning[]>([])

const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => admin.can('catalog.manage'))
const locked = computed(() => !canManage.value || saving.value)

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value || creating.value ? 'unsaved' : 'saved'
})

const section = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'catalog-feeds')?.title ??
    t('module.title'),
)

const title = computed(() => values.value.name || t('panel.untitled'))

/** The screen speaks of a model; the fields of the page know it is a feed. */
const model = computed<ScreenModel>({
  get: () => values.value as unknown as ScreenModel,
  set: (next) => {
    values.value = next as unknown as FeedValues
  },
})

function blankValues(): FeedValues {
  return {
    name: '',
    preset: null,
    format: 'xml',
    locale: '',
    head: '',
    item: '',
    foot: '',
    separator: '\n',
    filter: {},
    stock_map: {},
    taxonomy: null,
    custom_labels: {},
    sources: { mpn: { type: 'field' } },
    options: {},
    schedule: 'manual',
    schedule_at: null,
    rebuild_on_change: false,
    is_active: true,
  }
}

/** A copy of the preset, in the form and not saved: nothing exists until the first save. */
function fromPreset(found: FeedsMeta): FeedValues {
  const key = String(route.query.preset ?? '')
  const preset = found.presets.find((one) => one.key === key)
  return {
    ...blankValues(),
    locale: defaultLocale.value,
    currency: found.currency,
    ...(preset ? JSON.parse(JSON.stringify(preset.values)) : {}),
    ...(preset ? { name: preset.name, preset: preset.key } : {}),
  }
}

function take(detail: FeedDetail): void {
  feed.value = detail
  values.value = formValues(detail as unknown as FeedValues)
  snapshot.value = JSON.stringify(values.value)
}

/** What the server changed by itself; what is being typed in the form stays. */
function took(detail: FeedDetail): void {
  feed.value = detail
}

provideFeedEditor({ api, base: props.base, feed, values, locked, errors, meta, variables, took })

async function load(): Promise<void> {
  errors.value = {}
  loading.value = true

  try {
    const [found, words, detail] = await Promise.all([
      api.meta(),
      api.variables(),
      id.value === null ? Promise.resolve(null) : api.get(id.value),
    ])

    meta.value = found
    variables.value = words

    if (detail === null) {
      feed.value = null
      values.value = fromPreset(found)
      snapshot.value = JSON.stringify(values.value)
    } else {
      take(detail)
    }
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
    const payload = payloadOf(values.value)
    const answer = id.value === null ? await api.create(payload) : await api.save(id.value, payload)

    take(answer.data)
    warnings.value = answer.warnings ?? []
    toast.success(t(id.value === null ? 'panel.created' : 'panel.saved'))

    if (id.value === null) await router.replace(`${list.value}/${answer.data.id}`)
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

async function rebuild(): Promise<void> {
  const row = feed.value

  if (!row) return

  try {
    await api.rebuild(row.id)
    toast.success(t('panel.rebuild-queued'))
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(): Promise<void> {
  const row = feed.value

  if (!row) return

  const agreed = await confirm({
    title: t('panel.delete-title', { name: row.name }),
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
    if (dirty.value || creating.value) void save()
  }
}

useBodyKeys(onKeydown)

watch(id, (next, previous) => {
  // The first save of a new feed moves the address to its id: nothing to read again.
  if (previous === null && next !== null && feed.value?.id === next) return

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
  const row = feed.value

  if (!row || !canManage.value) return []

  return [
    {
      key: 'rebuild',
      label: t('panel.rebuild'),
      icon: 'refresh',
      disabled: !row.is_active,
      hint: row.is_active ? undefined : t('panel.rebuild-inactive'),
      run: () => void rebuild(),
    },
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

const said = computed(() => warnings.value.filter((warning) => warning.message !== null))
</script>

<template>
  <div class="wx-catalog-feed-editor" @keydown="onKeydown">
    <template v-if="loading">
      <wx-skeleton class="wx-catalog-feed-editor__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="6" /></wx-card>
    </template>

    <template v-else>
      <wx-screen-head divider :back="list" :back-label="section" :title="title" :actions="actions">
        <template #trail>
          <wx-breadcrumb size="sm" :label="t('panel.trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="list">{{ section }}</wx-breadcrumb-item>
            <wx-breadcrumb-item current>{{ title }}</wx-breadcrumb-item>
          </wx-breadcrumb>
        </template>

        <template v-if="feed && !feed.is_active" #title-after>
          <wx-badge dot size="sm">{{ t('panel.inactive') }}</wx-badge>
        </template>
      </wx-screen-head>

      <wx-alert v-for="warning in said" :key="warning.code" type="warning">
        {{ warning.message }}
      </wx-alert>

      <wx-screen v-model="model" name="catalog.feed-form" :errors="errors" :disabled="locked" />

      <wx-action-bar v-if="canManage">
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
.wx-catalog-feed-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-catalog-feed-editor__ghost {
  max-width: 420px;
}
</style>
