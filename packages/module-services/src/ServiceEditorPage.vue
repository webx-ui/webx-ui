<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  provideRecordAddress,
  useAdmin,
  useDates,
  useEditing,
  useErrorText,
  useTranslate,
  WxEditingAlerts,
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
 * a 409, merged with theirs, and asked about only where both changed the same place.
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
const panel = useTranslate('webx-admin')
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

const snapshot = ref('')
const reloadToken = ref(0)

const canManage = computed(() => context.can('services.manage'))

/*
 * Other people on the same service: a refused save is merged with theirs field by field and
 * saved again, only a field both changed is asked about, and a save made meanwhile — by a
 * colleague or an agent — is offered before this editor writes over it.
 */
const editing = useEditing<ScreenModel>({
  entity: 'services',
  id: () => service.value?.id,
  values,
  revision,
  read: async () => {
    const detail = await api.get(id.value)

    return { values: detail.values, revision: detail.revision }
  },
  adopt: (theirs) => {
    snapshot.value = JSON.stringify(theirs.values)
    reloadToken.value += 1
  },
  canWrite: () => canManage.value,
  busy: () => saving.value || flight !== undefined,
})

const conflict = editing.conflict

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
  editing.opened(detail.values)
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

/* The save on its way, and what it carries. */
let flight: { carried: string; done: Promise<void> } | undefined

/**
 * A save asked for while another is on its way waits for it instead of being dropped: leaving and
 * publishing read `dirty` right after, and a save that was skipped reads as one that failed.
 */
async function save(): Promise<void> {
  while (flight) {
    const { carried, done } = flight

    await done

    // That request wrote what it carried; go again only for what was typed while it was out.
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
  if (!service.value || !canManage.value) return

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

    // What the server holds now is what was sent: the base of the next merge.
    editing.opened(sent)

    // The preview is rendered from the draft, and the draft is what was just written.
    reloadToken.value += 1
  } catch (error) {
    const failure = error as {
      status?: number
      body?: ServiceConflict & { errors?: Record<string, string[]> }
    }

    if (failure.status === 409 && failure.body?.data) {
      const theirs = failure.body.data

      // Nothing overlapping: both edits are in the form now, and they go to the server again.
      if (
        editing.refused({
          values: theirs.values,
          revision: theirs.revision,
          changed: failure.body.changed,
        })
      ) {
        service.value = theirs.service
        void save()
      }
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

/** Give up what was typed and take the service as it now is; the component asked first. */
async function takeTheirs(): Promise<void> {
  await load(true)
}

/** Throw away what is waiting — asked about, because it is the one button here that loses writing. */
async function discard(): Promise<void> {
  if (!service.value) return

  const agreed = await confirm({
    title: panel('editor.discard-title'),
    message: panel('editor.discard-text'),
    confirmText: t('service.discard'),
    // Not «Cancel»: beside «Discard changes» the two read as the same word.
    cancelText: panel('editor.keep-changes'),
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
  // Saved before asking: the question names the address the draft will publish at, and only a
  // saved draft has one — an address typed but not saved was asked about under the old one.
  if (dirty.value) await save()
  if (conflict.value || dirty.value) return

  const row = service.value

  if (!row) return

  // Publishing moves a renamed slug, so the question names where the page will be, not where
  // it is — and says the old address will lead there, since that is what happens to it.
  const next = row.next_path ?? row.path
  const old =
    row.next_path != null && row.path !== null
      ? ` ${panel('editor.publish-moves', { old: `/${row.path}` })}`
      : ''

  const agreed = await confirm({
    title: t('service.publish-title', { title: title.value || row.title }),
    message:
      next === null
        ? t('service.publish-nowhere')
        : t('service.publish-text', { address: `/${next}` }) + old,
    confirmText: t('panel.publish'),
    cancelText: t('panel.cancel'),
  })

  if (!agreed) return

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

/**
 * Taking it off the site is asked about, as publishing is: visitors are who notice. Nothing
 * written is lost, and «Publish» brings it back at the same address.
 */
async function unpublish(): Promise<void> {
  const row = service.value

  if (!row) return

  const agreed = await confirm({
    title: panel('editor.unpublish-title'),
    message: panel('editor.unpublish-text'),
    confirmText: panel('editor.unpublish'),
    cancelText: panel('editor.keep-published'),
  })

  if (!agreed) return

  working.value = true

  try {
    await api.unpublish(row.id)
    await load(true)
    toast.success(panel('editor.unpublished'))
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

  // On the site now: the way off it is a button of its own beside «Open on the site», not a
  // line in the ···. Hiding loses nothing and is undone by «Publish».
  if (
    canManage.value &&
    (service.value?.status === 'published' || service.value?.status === 'modified')
  ) {
    leads.push({
      key: 'unpublish',
      label: panel('editor.unpublish'),
      icon: 'eye-off',
      run: () => void unpublish(),
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

      <!-- Somebody else wrote while this editor was open. What does not overlap is merged
           without a word; what does is listed place by place. -->
      <wx-editing-alerts :editing="editing" @save="save" @theirs="takeTheirs" />

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
