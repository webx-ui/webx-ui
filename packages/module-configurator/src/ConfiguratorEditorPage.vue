<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  useAdmin,
  useDates,
  useDefaultLocale,
  useEditing,
  useErrorText,
  useTranslate,
  WxEditingAlerts,
  WxSaveState,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
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
  WxTab,
  WxTabs,
} from '@webx-ui/core'
import ConfiguratorBuilder from './ConfiguratorBuilder.vue'
import ConfiguratorHistory from './ConfiguratorHistory.vue'
import ConfiguratorJson from './ConfiguratorJson.vue'
import ConfiguratorSettings from './ConfiguratorSettings.vue'
import { createConfiguratorsApi } from './api'
import { provideBuilder, useProblemWords, usePictures } from './builder'
import { provideConfiguratorEditor } from './editor'
import { validate, type Problem } from './engine/validator'
import { useConfiguratorsMessages } from './i18n'
import type {
  ConfiguratorConflict,
  ConfiguratorDetail,
  ConfiguratorValues,
  SchemaProblem,
} from './types'

/**
 * The editor of one configurator (§13.2): a head that stays put, four tabs — the constructor with
 * its live preview, the document as JSON, the settings, the history — and the bar along the
 * bottom where the draft is saved and published.
 *
 * Saving is by autosave into the draft, problems and all: a configurator half built is a draft,
 * and only a publication waits for the schema to be whole (§9). Edits are guarded by a revision:
 * a save over somebody else's is refused with a 409, merged with theirs — the document by its
 * places, a step by its key — and asked about only where both changed (decision 2).
 */
defineOptions({ name: 'WxConfiguratorEditorPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/configurators' })

const context = useAdmin()
const api = createConfiguratorsApi(context)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
const defaultLocale = useDefaultLocale()
const dates = useDates()
useConfiguratorsMessages()

const t = useTranslate('webx-configurator')
const panel = useTranslate('webx-admin')
const message = useErrorText()
const words = useProblemWords()

/** How long after the last change the draft goes to the server. */
const PAUSE = 1500

const id = computed(() => Number(route.params.id))
const list = computed(() => props.base)

const detail = ref<ConfiguratorDetail | null>(null)
const values = ref<ConfiguratorValues>(blank())
const revision = ref('')
const tab = ref<'builder' | 'json' | 'settings' | 'history'>('builder')

const loading = ref(true)
const saving = ref(false)
const working = ref(false)
const errors = ref<Record<string, string[]>>({})
/** What the server says the draft's problems are — `image.unknown` only it can know. */
const serverProblems = ref<SchemaProblem[]>([])

const snapshot = ref('')

const canManage = computed(() => context.can('configurators.manage'))

function blank(): ConfiguratorValues {
  return { key: '', title: {}, schema: { version: 1, currency: 'USD', steps: [] }, form: null }
}

/** A copy that shares nothing with what it was made from: the form edits its values in place. */
function copy<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T
}

const editing = useEditing<ConfiguratorValues>({
  entity: 'configurators',
  id: () => detail.value?.configurator.id,
  values,
  revision,
  read: async () => {
    const fresh = await api.get(id.value)

    return { values: fresh.values, revision: fresh.revision }
  },
  adopt: (theirs) => {
    snapshot.value = JSON.stringify(theirs.values)
  },
  refresh: () => refresh(),
  restore: async () => {
    if (detail.value) await api.restore(detail.value.configurator.id)
  },
  canWrite: () => canManage.value,
  busy: () => saving.value || flight !== undefined,
  field: (name) =>
    ({
      key: t('configurator.field-key'),
      title: t('configurator.field-title'),
      schema: t('configurator.tab-builder'),
      form: t('configurator.field-form'),
    })[name],
})

const conflict = editing.conflict

const locked = computed(() => !canManage.value || working.value || editing.stopped.value)

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value ? 'unsaved' : 'saved'
})

/* ------------------------------------------------------------------------- the constructor */

const schema = computed({
  get: () => values.value.schema,
  set: (next) => {
    values.value.schema = next
  },
})

/**
 * The problems as they are typed — the JS copy of the check — and the server's that only it can
 * know beside them: whether the media library has a picture (`image.unknown`).
 */
const problems = computed<Problem[]>(() => {
  const own = validate(values.value.schema, defaultLocale.value)
  const pictures = serverProblems.value
    .filter((problem) => problem.code === 'image.unknown')
    .map((problem) => ({
      path: problem.path,
      code: problem.code,
      params: {},
      message: problem.message,
    }))

  return [...own, ...pictures]
})

const open = reactive(new Set<number>())
const focus = ref<string | null>(null)

provideBuilder({
  schema,
  locale: defaultLocale,
  disabled: locked,
  problems,
  message: words,
  picture: usePictures(),
  isOpen: (row) => open.has(row),
  toggle: (row) => {
    if (open.has(row)) open.delete(row)
    else open.add(row)
  },
  focus,
})

provideConfiguratorEditor({ values, detail, errors, canManage, reload: () => load(true) })

/* ------------------------------------------------------------------------------- the head */

const title = computed(() =>
  localizedValue(values.value.title, locales.active.value, detail.value?.configurator.title ?? ''),
)

const publication = computed(() => {
  const row = detail.value?.configurator

  if (!row || (row.status !== 'published' && row.status !== 'modified') || !row.published_at)
    return ''

  return t('configurator.live-since', { date: dates.short(row.published_at) })
})

function badge(): 'default' | 'success' | 'warning' {
  const status = detail.value?.configurator.status

  if (status === 'published' || status === 'modified') return 'success'

  return status === 'unpublished' ? 'warning' : 'default'
}

/* -------------------------------------------------------------------------- reading, saving */

function take(fresh: ConfiguratorDetail): void {
  detail.value = fresh
  values.value = copy(fresh.values)
  revision.value = fresh.revision
  serverProblems.value = fresh.errors
  snapshot.value = JSON.stringify(fresh.values)
  editing.opened(copy(fresh.values))
  conflict.value = null
}

async function load(silent = false): Promise<void> {
  if (!silent) loading.value = true

  try {
    take(await api.get(id.value))
  } catch (error) {
    toast.danger(message(error))
    void router.push(list.value)
  } finally {
    loading.value = false
  }
}

/** The record around the form, read again — not the values: they are somebody's work in progress. */
async function refresh(): Promise<void> {
  try {
    const fresh = await api.get(id.value)

    detail.value = { ...fresh, values: detail.value?.values ?? fresh.values }
    serverProblems.value = fresh.errors
  } catch {
    // The heartbeat says what happened; a record that could not be read again stays as it was.
  }
}

let timer: ReturnType<typeof setTimeout> | undefined

function schedule(): void {
  if (!canManage.value || conflict.value || !dirty.value || editing.stopped.value) return

  clearTimeout(timer)
  timer = setTimeout(() => void save(), PAUSE)
}

let flight: { carried: string; done: Promise<void> } | undefined

/** A save asked for while another is on its way waits for it rather than being dropped. */
async function save(): Promise<void> {
  while (flight) {
    const { carried, done } = flight

    await done

    if (!dirty.value || conflict.value || current.value === carried) return
  }

  const carried = current.value
  const done = write()

  flight = { carried, done }

  try {
    await done
  } finally {
    if (flight?.done === done) flight = undefined
  }
}

async function write(): Promise<void> {
  if (!detail.value || !canManage.value) return

  clearTimeout(timer)

  const sending = current.value
  const sent = copy(values.value)

  saving.value = true
  errors.value = {}

  try {
    const fresh = await api.save(detail.value.configurator.id, sent, revision.value)

    detail.value = { ...fresh, values: sent }
    revision.value = fresh.revision
    serverProblems.value = fresh.errors
    conflict.value = null

    if (current.value === sending) snapshot.value = sending

    editing.opened(sent)
  } catch (error) {
    const failure = error as {
      status?: number
      body?: ConfiguratorConflict & { errors?: Record<string, string[]> }
    }

    if (failure.status === 409 && failure.body?.data) {
      const theirs = failure.body.data

      if (
        editing.refused({
          values: theirs.values,
          revision: theirs.revision,
          changed: failure.body.changed,
        })
      ) {
        detail.value = theirs
        void save()
      }
    } else if (failure.body?.errors) {
      errors.value = failure.body.errors
      toast.danger(t('configurator.save-failed'))

      // A refused key or name is on the settings tab: that is where it is said and fixed.
      if (errors.value.key || errors.value.title || errors.value.form) tab.value = 'settings'
    } else if (!(await editing.failed(error))) {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

async function takeTheirs(): Promise<void> {
  await load(true)
}

/* -------------------------------------------------------------------------- the site */

async function publish(): Promise<void> {
  if (dirty.value) await save()
  if (conflict.value || dirty.value || !detail.value) return

  if (problems.value.length > 0) {
    toast.danger(t('configurator.publish-refused', { count: problems.value.length }))
    tab.value = 'builder'

    return
  }

  const held = await editing.beforePublish()

  if (!held) return

  const agreed =
    held.asked ||
    (await confirm({
      title: t('configurator.publish-title', {
        name: title.value || detail.value.configurator.key,
      }),
      message: t('configurator.publish-text'),
      confirmText: t('configurator.publish'),
      cancelText: t('configurator.cancel'),
    }))

  if (!agreed) return

  working.value = true

  try {
    take(await api.publish(detail.value.configurator.id, held.revision))
    toast.success(t('configurator.published'))
  } catch (error) {
    const failure = error as {
      status?: number
      body?: { problems?: SchemaProblem[]; message?: string }
    }

    if (failure.status === 422 && failure.body?.problems) {
      serverProblems.value = failure.body.problems
      toast.danger(failure.body.message ?? t('configurator.save-failed'))
      tab.value = 'builder'
    } else if (!(await editing.failed(error))) {
      toast.danger(message(error))
    }
  } finally {
    working.value = false
  }
}

async function unpublish(): Promise<void> {
  if (!detail.value) return

  const agreed = await confirm({
    title: t('configurator.unpublish-title'),
    message: t('configurator.unpublish-text'),
    confirmText: t('configurator.unpublish'),
    cancelText: panel('editor.keep-published'),
  })

  if (!agreed) return

  working.value = true

  try {
    const fresh = await api.unpublish(detail.value.configurator.id)

    detail.value = { ...fresh, values: detail.value.values }
    revision.value = fresh.revision
    toast.success(t('configurator.unpublished'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

async function discard(): Promise<void> {
  if (!detail.value) return

  const agreed = await confirm({
    title: panel('editor.discard-title'),
    message: panel('editor.discard-text'),
    confirmText: t('configurator.discard'),
    cancelText: panel('editor.keep-changes'),
    tone: 'danger',
  })

  if (!agreed) return

  working.value = true

  try {
    take(await api.discard(detail.value.configurator.id))
    toast.success(t('configurator.discarded'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

/** A problem pointed at from the JSON tab: the constructor unfolds it. */
function reveal(path: string): void {
  tab.value = 'builder'
  focus.value = path
}

/* --------------------------------------------------------------------------- leaving */

function leaveGuard(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

watch(current, schedule)
watch(id, () => void load())

onMounted(() => {
  void load()
  window.addEventListener('beforeunload', leaveGuard)
})

onBeforeUnmount(() => {
  clearTimeout(timer)
  window.removeEventListener('beforeunload', leaveGuard)
})

onBeforeRouteLeave(async () => {
  if (!dirty.value || !canManage.value) return true

  await save()

  if (!dirty.value) return true

  return await confirm({
    title: t('configurator.leave-title'),
    message: t('configurator.leave-text'),
    confirmText: t('configurator.leave'),
    cancelText: t('configurator.cancel'),
    tone: 'danger',
  })
})

const actions = computed<ScreenAction[]>(() => {
  const leads: ScreenAction[] = []
  const status = detail.value?.configurator.status

  if (canManage.value && (status === 'published' || status === 'modified')) {
    leads.push({
      key: 'unpublish',
      label: t('configurator.unpublish'),
      icon: 'eye-off',
      run: () => void unpublish(),
    })
  }

  if (canManage.value && status === 'modified') {
    leads.push({
      key: 'discard',
      label: t('configurator.discard'),
      icon: 'refresh',
      danger: true,
      menu: true,
      run: () => void discard(),
    })
  }

  return leads
})
</script>

<template>
  <div class="wx-configurator-editor">
    <template v-if="loading || !detail">
      <wx-skeleton class="wx-configurator-editor__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="8" /></wx-card>
    </template>

    <template v-else>
      <wx-screen-head
        divider
        :back="list"
        :back-label="t('module.title')"
        :title="title || t('configurator.untitled')"
        :subtitle="publication || detail.configurator.key"
        :actions="actions"
      >
        <template #trail>
          <wx-breadcrumb size="sm" :label="t('configurator.trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="list">{{
              t('module.title')
            }}</wx-breadcrumb-item>
            <wx-breadcrumb-item current>{{
              title || t('configurator.untitled')
            }}</wx-breadcrumb-item>
          </wx-breadcrumb>
        </template>

        <template #title-after>
          <wx-badge :type="badge()" dot>
            {{ t(`configurator.status-${detail.configurator.status}`) }}
          </wx-badge>
          <wx-badge v-if="detail.configurator.status === 'modified'" type="primary" round>
            {{ t('configurator.edits') }}
          </wx-badge>
        </template>
      </wx-screen-head>

      <wx-editing-alerts :editing="editing" @save="save" @theirs="takeTheirs" />

      <wx-tabs v-model="tab" class="wx-configurator-editor__tabs" keep-alive>
        <wx-tab
          value="builder"
          :label="t('configurator.tab-builder')"
          :badge="problems.length > 0 ? problems.length : undefined"
        >
          <configurator-builder :locale="locales.active.value || defaultLocale" />
        </wx-tab>
        <wx-tab value="json" :label="t('configurator.tab-json')">
          <configurator-json @reveal="reveal" />
        </wx-tab>
        <wx-tab value="settings" :label="t('configurator.tab-settings')">
          <configurator-settings />
        </wx-tab>
        <wx-tab value="history" :label="t('configurator.tab-history')">
          <configurator-history />
        </wx-tab>
      </wx-tabs>

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state v-if="!editing.stopped.value" :state="state" />
        </template>

        <wx-button
          variant="outline"
          :loading="saving"
          :disabled="!dirty || editing.stopped.value"
          :title="editing.blocked.value"
          @click="save"
        >
          {{ t('configurator.save') }}
        </wx-button>

        <wx-button
          type="primary"
          :loading="working"
          :disabled="
            editing.stopped.value || (detail.configurator.status === 'published' && !dirty)
          "
          :title="editing.blocked.value"
          @click="publish"
        >
          {{ t('configurator.publish') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-configurator-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-height: 0;
  container-type: inline-size;
}

.wx-configurator-editor__ghost {
  max-width: 420px;
}
</style>
