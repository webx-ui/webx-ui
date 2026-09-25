<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  provideRecordAddress,
  provideRelationOwner,
  useAdmin,
  useDates,
  useErrorText,
  useTranslate,
  WxSaveState,
  WxScreen,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
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
  WxIcon,
  WxSkeleton,
  type LocalizedValue,
} from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'
import { createEventsApi } from './api'
import { provideEventEditor } from './editor'
import { useEventsMessages } from './i18n'
import type { EventConflict, EventDetail, EventRow } from './types'

/**
 * The editor of one event: a head that stays put, the described screen under it, and the bar
 * along the bottom where it is saved, duplicated and published (§4.9).
 *
 * Everything between the head and the bar is `events.form` — the event, its settings, the SEO
 * card `module-seo` patches on and the history — so a project adds its own field with a patch
 * rather than a fork of this file. Saving is by autosave into the draft, and the draft carries
 * the categories and the services too: the site changes, all of it at once, only on "Publish".
 * Edits are guarded by a revision: a save over somebody else's is refused with a 409 and the
 * question of which version the site gets.
 *
 * The dates are moments with their offset (`valueFormat` on the screen's pickers), so the same
 * event reads the same hour in every browser and the server keeps it in its own zone (decision
 * 15). What the head prints under the name is the server's line (`when`, §4.6), not ours: it is
 * the line the site prints, `date_note` included, and a second formatter here would disagree
 * with it sooner or later.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/events' })

const context = useAdmin()
const api = createEventsApi(context)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
const dates = useDates()
useEventsMessages()

const t = useTranslate('webx-events')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

/** How long after the last keystroke the draft goes to the server. */
const PAUSE = 1200

const id = computed(() => Number(route.params.id))
const list = computed(() => props.base)

const event = ref<EventRow | null>(null)
const values = ref<ScreenModel>({})
const revision = ref('')
const prefix = ref<string | null>(null)
const previewUrl = ref<string | null>(null)

const loading = ref(true)
const saving = ref(false)
const working = ref(false)
const errors = ref<Record<string, string[]>>({})
const conflict = ref<EventConflict | null>(null)

const snapshot = ref('')

const canManage = computed(() => context.can('events.manage'))

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

  return localizedValue(written, locales.active.value, event.value?.title ?? '')
})

/**
 * Under the name: when the event is, as the site prints it, and since when it is on the site —
 * the two facts the badges beside the name cannot carry.
 */
const subtitle = computed(() => {
  const row = event.value

  if (!row) return ''

  const parts = [row.when]

  if (row.status === 'published' || row.status === 'modified') {
    parts.push(t('event.live-since', { date: dates.short(row.published_at) }))
  }

  return parts.filter((part) => part !== '').join(' · ')
})

/* The event as the owner of its relations: nothing it links to can be the event itself. */
provideRelationOwner({ type: 'event', id: computed(() => event.value?.id ?? null) })

/* The address field is the panel's shared one (`wx-slug`), and this is what it prints. */
provideRecordAddress({
  values,
  prefix,
  path: computed(() => event.value?.path),
  moving: () => t('event.address-moving'),
})

provideEventEditor({ event, canManage: canManage.value, reload: () => load(true) })

function take(detail: EventDetail): void {
  event.value = detail.event
  values.value = detail.values
  revision.value = detail.revision
  prefix.value = detail.prefix
  previewUrl.value = detail.preview_url
  snapshot.value = JSON.stringify(detail.values)
  conflict.value = null
}

/**
 * Read the event again — quietly unless this is the first time: the skeleton replaces the whole
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
  if (!event.value || saving.value || !canManage.value) return

  clearTimeout(timer)

  // What is being sent, so that whatever is typed while the request is in flight stays dirty and
  // gets its own save rather than being marked as written.
  const sending = current.value
  const sent = values.value

  saving.value = true
  errors.value = {}

  try {
    const detail = await api.save(event.value.id, { values: sent, revision: revision.value })

    event.value = detail.event
    revision.value = detail.revision
    prefix.value = detail.prefix
    previewUrl.value = detail.preview_url
    conflict.value = null

    if (current.value === sending) snapshot.value = sending
  } catch (error) {
    const failure = error as {
      status?: number
      body?: EventConflict & { errors?: Record<string, string[]> }
    }

    if (failure.status === 409 && failure.body?.data) {
      conflict.value = failure.body
    } else if (failure.body?.errors) {
      errors.value = failure.body.errors
      toast.danger(t('event.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

/** Give up what was typed and take the event as it now is. */
async function takeTheirs(): Promise<void> {
  const agreed = await confirm({
    title: t('event.conflict-theirs-title'),
    message: t('event.conflict-theirs-text'),
    confirmText: t('event.conflict-theirs'),
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
  if (!event.value) return

  const agreed = await confirm({
    title: t('event.discard-title'),
    message: t('event.discard-text'),
    confirmText: t('event.discard'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  working.value = true

  try {
    take(await api.discard(event.value.id))
    toast.success(t('event.discarded'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

/** Publishing is asked about, because it is the one action here that visitors see. */
async function publish(): Promise<void> {
  const row = event.value

  if (!row) return

  const agreed = await confirm({
    title: t('event.publish-title', { title: title.value || row.title }),
    message:
      row.path === null
        ? t('event.publish-nowhere')
        : t('event.publish-text', { address: `/${row.path}` }),
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

/**
 * Another date, the same event: a copy as a draft, opened at once (decision 9). What is typed
 * here goes first — the copy is made from what the server holds, and an edit still in the pause
 * would be in the original and missing from the copy.
 */
async function duplicate(): Promise<void> {
  const row = event.value

  if (!row) return

  if (dirty.value) await save()
  if (conflict.value || dirty.value) return

  working.value = true

  try {
    const copy = await api.duplicate(row.id)

    toast.success(t('event.duplicated'))
    // The same route with another id: the editor stays and reads the copy (`watch(id)`).
    await router.push({ path: `${list.value}/${copy.event.id}`, query: { ...route.query } })
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

function badge(): 'default' | 'success' {
  const status = event.value?.status

  return status === 'published' || status === 'modified' ? 'success' : 'default'
}

/* The browser's own guard: it cannot wait for a request, so all it can do is ask. */
function leaveGuard(unload: BeforeUnloadEvent): void {
  if (dirty.value) unload.preventDefault()
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
    title: t('event.leave-title'),
    message: t('event.leave-text'),
    confirmText: t('event.leave'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })
})

/* What leads away from the event. On a phone the head folds them into the ···. */
const actions = computed<ScreenAction[]>(() => {
  const leads: ScreenAction[] = []

  if (previewUrl.value) {
    leads.push({ key: 'preview', label: t('event.preview'), icon: 'eye', href: previewUrl.value })
  }

  if (event.value?.url && event.value.status !== 'draft') {
    leads.push({
      key: 'site',
      label: t('panel.open-on-site'),
      icon: 'link',
      href: event.value.url,
    })
  }

  // Throwing away writing lives in the ···, never beside "publish", and only while there is a
  // difference between what is written and what is on the site.
  if (event.value?.status === 'modified') {
    leads.push({
      key: 'discard',
      label: t('event.discard'),
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
  <div class="wx-event-editor" @focusout="onFocusOut">
    <template v-if="loading || !event">
      <wx-skeleton class="wx-event-editor__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="8" /></wx-card>
    </template>

    <template v-else>
      <wx-screen-head
        divider
        :back="list"
        :back-label="t('module.events')"
        :title="title || t('event.untitled')"
        :subtitle="subtitle"
        :actions="actions"
      >
        <template #trail>
          <wx-breadcrumb size="sm" :label="t('event.trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="list">
              {{ t('module.events') }}
            </wx-breadcrumb-item>
            <wx-breadcrumb-item current>{{ title || t('event.untitled') }}</wx-breadcrumb-item>
          </wx-breadcrumb>
        </template>

        <template #title-after>
          <wx-badge :type="badge()" dot>{{ t(`panel.status-${event.status}`) }}</wx-badge>
          <wx-badge v-if="event.status === 'modified'" type="primary" round>
            {{ t('panel.edits') }}
          </wx-badge>
          <!-- Over, and still a page of the site: it is a photo report now (decision 6). -->
          <wx-badge v-if="event.past" round>{{ t('panel.past') }}</wx-badge>
        </template>
      </wx-screen-head>

      <!-- Somebody else wrote while this editor was typing. Both versions still exist, so the
           question is which one the site gets — a question, not a toast that disappears. -->
      <wx-alert
        v-if="conflict"
        type="warning"
        :title="t('event.conflict-title')"
        :description="conflict.message"
      >
        <template #actions>
          <wx-button size="sm" variant="outline" @click="takeTheirs">
            {{ t('event.conflict-theirs') }}
          </wx-button>
          <wx-button size="sm" type="primary" @click="keepMine">
            {{ t('event.conflict-mine') }}
          </wx-button>
        </template>
      </wx-alert>

      <wx-screen v-model="values" name="events.form" :errors="errors" :disabled="locked" />

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <!-- The way to the next date of the same event: there are no series (decision 9). -->
        <wx-button
          variant="text"
          :disabled="working"
          :aria-label="t('panel.duplicate')"
          @click="duplicate"
        >
          <template #icon><wx-icon name="copy" /></template>
          <span class="wx-event-editor__word">{{ t('panel.duplicate') }}</span>
        </wx-button>

        <wx-button variant="outline" :loading="saving" :disabled="!dirty" @click="save">
          {{ t('event.save') }}
        </wx-button>

        <wx-button
          type="primary"
          :loading="working"
          :disabled="event.status === 'published' && !dirty"
          @click="publish"
        >
          {{ t('panel.publish') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
/*
 * No constructor here, so nothing needs the height of the window: the page scrolls, and the
 * panel gives a screen with an action bar a floor tall enough for the bar to sit at the bottom
 * (`WxMain`, CLAUDE.md §4 on `data-wx-fill`).
 */
.wx-event-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-height: 0;
  container-type: inline-size;
}

.wx-event-editor__ghost {
  max-width: 420px;
}

/*
 * On a phone the bar holds the state and three buttons, and with the word the third one pushes
 * the state onto a line of its own — a strip of nothing over the buttons (CLAUDE.md §4 on
 * `WxActionBar`). The copy icon is enough there; the word stays for a screen reader.
 */
@container (max-width: 480px) {
  .wx-event-editor__word {
    display: none;
  }
}
</style>
