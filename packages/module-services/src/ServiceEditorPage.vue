<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  provideRecordAddress,
  useAdmin,
  useDates,
  useErrorText,
  useTranslate,
  WxSaveState,
  WxScreen,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
import { provideBlocksPreview } from '@webx-ui/module-blocks'
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
import type { ScreenModel } from '@webx-ui/schema'
import { createServicesApi } from './api'
import { provideServiceEditor } from './editor'
import { useServicesMessages } from './i18n'
import type { ServiceConflict, ServiceDetail, ServiceRow } from './types'

/**
 * The editor of one service: a head that stays put, the described screen under it, and the bar
 * along the bottom where it is saved and published (§4.6).
 *
 * Everything between the head and the bar is `services.form`, so `module-seo` adds its card and a
 * project its price with a patch rather than a fork of this file. Saving is by autosave — a pause
 * after the last keystroke, and the moment a field is left — into the draft; the site changes
 * only on "Publish". Edits are guarded by a revision: a save over somebody else's is refused with
 * a 409 and the question of which version the site gets.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/services' })

const context = useAdmin()
const api = createServicesApi(context)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
const dates = useDates()
useServicesMessages()

const t = useTranslate('webx-services')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

/** How long after the last keystroke the draft goes to the server. */
const PAUSE = 1200

const id = computed(() => Number(route.params.id))
const list = computed(() => props.base)

const service = ref<ServiceRow | null>(null)
const values = ref<ScreenModel>({})
const revision = ref('')
const prefix = ref<string | null>(null)
const previewUrl = ref<string | null>(null)

const loading = ref(true)
const saving = ref(false)
const working = ref(false)
const errors = ref<Record<string, string[]>>({})
const conflict = ref<ServiceConflict | null>(null)

const snapshot = ref('')
const reloadToken = ref(0)

const canManage = computed(() => context.can('services.manage'))

/** Closed for writing: no permission, or a publication in flight. */
const locked = computed(() => !canManage.value || working.value)

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value ? 'unsaved' : 'saved'
})

/** The name follows the field rather than the answer: a rename shows at the top as it is typed. */
const title = computed(() => {
  const written = values.value.title as LocalizedValue | string | null | undefined

  return localizedValue(written, locales.active.value, service.value?.title ?? '')
})

/** Since when it is on the site, under the name — the one fact the badge beside it cannot carry. */
const publication = computed(() => {
  const row = service.value

  if (!row || (row.status !== 'published' && row.status !== 'modified')) return ''

  return t('service.live-since', { date: dates.short(row.published_at) })
})

/* The constructor shows the draft as a page of the site. The link is the editor's to hand over:
   only this screen knows which entity it is editing. */
provideBlocksPreview({ url: computed(() => previewUrl.value), reload: reloadToken })

/* The address field is the panel's shared one (`wx-slug`), and this is what it prints. */
provideRecordAddress({
  values,
  prefix,
  path: computed(() => service.value?.path),
  moving: () => t('service.address-moving'),
})

provideServiceEditor({ service, canManage: canManage.value, reload: () => load(true) })

function take(detail: ServiceDetail): void {
  service.value = detail.service
  values.value = detail.values
  revision.value = detail.revision
  prefix.value = detail.prefix
  previewUrl.value = detail.preview_url
  snapshot.value = JSON.stringify(detail.values)
  conflict.value = null
}

/**
 * Read the service again — quietly unless this is the first time: the skeleton replaces the whole
 * screen, and a publication answered with it would throw the editor back onto the first tab.
 */
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

/* One pending save at a time, and one pending pause. */
let timer: ReturnType<typeof setTimeout> | undefined

function schedule(): void {
  if (!canManage.value || conflict.value || !dirty.value) return

  clearTimeout(timer)
  timer = setTimeout(() => void save(), PAUSE)
}

/** A field was left: write now rather than at the end of a pause nobody is waiting through. */
function onFocusOut(): void {
  if (!canManage.value || conflict.value || !dirty.value || saving.value) return

  clearTimeout(timer)
  void save()
}

async function save(): Promise<void> {
  if (!service.value || saving.value || !canManage.value) return

  clearTimeout(timer)

  // What is being sent, so that whatever is typed while the request is in flight stays dirty and
  // gets its own save rather than being marked as written.
  const sending = current.value
  const sent = values.value

  saving.value = true
  errors.value = {}

  try {
    const detail = await api.save(service.value.id, { values: sent, revision: revision.value })

    service.value = detail.service
    revision.value = detail.revision
    prefix.value = detail.prefix
    previewUrl.value = detail.preview_url
    conflict.value = null

    if (current.value === sending) snapshot.value = sending

    // The preview is rendered from the draft, and the draft is what was just written.
    reloadToken.value += 1
  } catch (error) {
    const failure = error as {
      status?: number
      body?: ServiceConflict & { errors?: Record<string, string[]> }
    }

    if (failure.status === 409 && failure.body?.data) {
      conflict.value = failure.body
    } else if (failure.body?.errors) {
      errors.value = failure.body.errors
      toast.danger(t('service.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

/** Give up what was typed and take the service as it now is. */
async function takeTheirs(): Promise<void> {
  const agreed = await confirm({
    title: t('service.conflict-theirs-title'),
    message: t('service.conflict-theirs-text'),
    confirmText: t('service.conflict-theirs'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  await load(true)
}

/** Keep what was typed and write it over the other version — which is still in the history. */
async function keepMine(): Promise<void> {
  const theirs = conflict.value

  if (!theirs) return

  revision.value = theirs.data.revision
  conflict.value = null

  await save()
}

/** Throw away what is waiting — asked about, because it is the one button here that loses writing. */
async function discard(): Promise<void> {
  if (!service.value) return

  const agreed = await confirm({
    title: t('service.discard-title'),
    message: t('service.discard-text'),
    confirmText: t('service.discard'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  working.value = true

  try {
    take(await api.discard(service.value.id))
    toast.success(t('service.discarded'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

/** Publishing is asked about, because it is the one action here that visitors see. */
async function publish(): Promise<void> {
  const row = service.value

  if (!row) return

  const agreed = await confirm({
    title: t('service.publish-title', { title: title.value || row.title }),
    message:
      row.path === null
        ? t('service.publish-nowhere')
        : t('service.publish-text', { address: `/${row.path}` }),
    confirmText: t('panel.publish'),
    cancelText: t('panel.cancel'),
  })

  if (!agreed) return

  if (dirty.value) await save()
  if (conflict.value) return

  working.value = true

  try {
    await api.publish(row.id)
    await load(true)
    toast.success(t('panel.published'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

function badge(): 'default' | 'success' {
  const status = service.value?.status

  return status === 'published' || status === 'modified' ? 'success' : 'default'
}

/* The browser's own guard: it cannot wait for a request, so all it can do is ask. */
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

/**
 * Leaving flushes the pause rather than asking about it; the question is asked only when the save
 * did not go through — a conflict, a refused field, a server that is not there.
 */
onBeforeRouteLeave(async () => {
  if (!dirty.value || !canManage.value) return true

  await save()

  if (!dirty.value) return true

  return await confirm({
    title: t('service.leave-title'),
    message: t('service.leave-text'),
    confirmText: t('service.leave'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })
})

/* What leads away from the service. On a phone the head folds them into the ···. */
const actions = computed<ScreenAction[]>(() => {
  const leads: ScreenAction[] = []

  if (previewUrl.value) {
    leads.push({ key: 'preview', label: t('service.preview'), icon: 'eye', href: previewUrl.value })
  }

  if (service.value?.url && service.value.status !== 'draft') {
    leads.push({
      key: 'site',
      label: t('panel.open-on-site'),
      icon: 'link',
      href: service.value.url,
    })
  }

  // Throwing away writing lives in the ···, never beside "publish", and only while there is a
  // difference between what is written and what is on the site.
  if (service.value?.status === 'modified') {
    leads.push({
      key: 'discard',
      label: t('service.discard'),
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
  <div class="wx-service-editor" @focusout="onFocusOut">
    <template v-if="loading || !service">
      <wx-skeleton class="wx-service-editor__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="8" /></wx-card>
    </template>

    <template v-else>
      <wx-screen-head
        divider
        :back="list"
        :back-label="t('module.services')"
        :title="title || t('service.untitled')"
        :subtitle="publication"
        :actions="actions"
      >
        <template #trail>
          <wx-breadcrumb size="sm" :label="t('service.trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="list">
              {{ t('module.services') }}
            </wx-breadcrumb-item>
            <wx-breadcrumb-item current>{{ title || t('service.untitled') }}</wx-breadcrumb-item>
          </wx-breadcrumb>
        </template>

        <template #title-after>
          <wx-badge :type="badge()" dot>{{ t(`panel.status-${service.status}`) }}</wx-badge>
          <wx-badge v-if="service.status === 'modified'" type="primary" round>
            {{ t('panel.edits') }}
          </wx-badge>
        </template>
      </wx-screen-head>

      <!-- Somebody else wrote while this editor was typing. Both versions still exist, so the
           question is which one the site gets — a question, not a toast that disappears. -->
      <wx-alert
        v-if="conflict"
        type="warning"
        :title="t('service.conflict-title')"
        :description="conflict.message"
      >
        <template #actions>
          <wx-button size="sm" variant="outline" @click="takeTheirs">
            {{ t('service.conflict-theirs') }}
          </wx-button>
          <wx-button size="sm" type="primary" @click="keepMine">
            {{ t('service.conflict-mine') }}
          </wx-button>
        </template>
      </wx-alert>

      <div class="wx-service-editor__screen">
        <wx-screen v-model="values" name="services.form" :errors="errors" :disabled="locked" />
      </div>

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button variant="outline" :loading="saving" :disabled="!dirty" @click="save">
          {{ t('service.save') }}
        </wx-button>

        <wx-button
          type="primary"
          :loading="working"
          :disabled="service.status === 'published' && !dirty"
          @click="publish"
        >
          {{ t('panel.publish') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-service-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  height: 100%;
  min-height: 0;
  container-type: inline-size;
}

.wx-service-editor__ghost {
  max-width: 420px;
}

.wx-service-editor__screen {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

/*
 * The height travels through the tabs to the constructor: every box between the page and it is a
 * flex column that may shrink. `:not([hidden])` is load-bearing — the tabs are kept alive, and a
 * `display: flex` that reached the hidden ones would split the height between all four.
 */
.wx-service-editor__screen :deep(.wx-screen-host),
.wx-service-editor__screen :deep(.wx-screen),
.wx-service-editor__screen :deep(.wx-tabs),
.wx-service-editor__screen :deep(.wx-tabs__layout),
.wx-service-editor__screen :deep(.wx-tabs__panels),
.wx-service-editor__screen :deep(.wx-tab:not([hidden])) {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}
</style>
