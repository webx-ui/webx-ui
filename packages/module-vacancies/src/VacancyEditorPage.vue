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
import { createVacanciesApi } from './api'
import { longDay } from './days'
import { provideVacancyEditor } from './editor'
import { useVacanciesMessages } from './i18n'
import type { VacancyConflict, VacancyDetail, VacancyRow } from './types'

/**
 * The editor of one vacancy: a head that stays put, the described screen under it, and the bar
 * along the bottom where it is saved, duplicated and published (§4.10).
 *
 * Everything between the head and the bar is `vacancies.form` — the vacancy, its settings, the SEO
 * card `module-seo` patches on and the history — so a project adds its own field with a patch
 * rather than a fork of this file. Saving is by autosave into the draft, and the draft carries
 * the categories, the form and "closed" too: the site changes, all of it at once, only on
 * "Publish". Edits are guarded by a revision: a save over somebody else's is refused with a 409
 * and the question of which version the site gets.
 *
 * The two dates are calendar days, not moments (decision 13): the pickers hold `YYYY-MM-DD` as
 * written, and the head prints them from their parts (`days.ts`), so no zone moves them.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/vacancies' })

const context = useAdmin()
const api = createVacanciesApi(context)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
const dates = useDates()
useVacanciesMessages()

const t = useTranslate('webx-vacancies')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

/** How long after the last keystroke the draft goes to the server. */
const PAUSE = 1200

const id = computed(() => Number(route.params.id))
const list = computed(() => props.base)

const vacancy = ref<VacancyRow | null>(null)
const values = ref<ScreenModel>({})
const revision = ref('')
const prefix = ref<string | null>(null)
const previewUrl = ref<string | null>(null)

const loading = ref(true)
const saving = ref(false)
const working = ref(false)
const errors = ref<Record<string, string[]>>({})
const conflict = ref<VacancyConflict | null>(null)

const snapshot = ref('')

const canManage = computed(() => context.can('vacancies.manage'))

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

  return localizedValue(written, locales.active.value, vacancy.value?.title ?? '')
})

/**
 * Under the name: how long it is open, and since when it is on the site — the two facts the
 * badges beside the name cannot carry.
 */
const subtitle = computed(() => {
  const row = vacancy.value

  if (!row) return ''

  const parts: string[] = []

  if (row.valid_through !== null && !row.closed) {
    parts.push(
      t('editor.open-until', { date: longDay(row.valid_through, context.i18n.state.locale) }),
    )
  }

  if (row.status === 'published' || row.status === 'modified') {
    parts.push(t('editor.live-since', { date: dates.short(row.published_at) }))
  }

  return parts.join(' · ')
})

/* The vacancy as the owner of its relations — the one it has is the response form (decision 10). */
provideRelationOwner({ type: 'vacancy', id: computed(() => vacancy.value?.id ?? null) })

/* The address field is the panel's shared one (`wx-slug`), and this is what it prints. */
provideRecordAddress({
  values,
  prefix,
  path: computed(() => vacancy.value?.path),
  moving: () => t('editor.address-moving'),
})

provideVacancyEditor({ vacancy, canManage: canManage.value, reload: () => load(true) })

function take(detail: VacancyDetail): void {
  const taken = detail.values

  vacancy.value = detail.vacancy
  values.value = taken
  revision.value = detail.revision
  prefix.value = detail.prefix
  previewUrl.value = detail.preview_url
  snapshot.value = JSON.stringify(taken)
  conflict.value = null
}

/**
 * Read the vacancy again — quietly unless this is the first time: the skeleton replaces the whole
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
  if (!vacancy.value || saving.value || !canManage.value) return

  clearTimeout(timer)

  // What is being sent, so that whatever is typed while the request is in flight stays dirty and
  // gets its own save rather than being marked as written.
  const sending = current.value
  const sent = values.value

  saving.value = true
  errors.value = {}

  try {
    const detail = await api.save(vacancy.value.id, { values: sent, revision: revision.value })

    vacancy.value = detail.vacancy
    revision.value = detail.revision
    prefix.value = detail.prefix
    previewUrl.value = detail.preview_url
    conflict.value = null

    if (current.value === sending) snapshot.value = sending
  } catch (error) {
    const failure = error as {
      status?: number
      body?: VacancyConflict & { errors?: Record<string, string[]> }
    }

    if (failure.status === 409 && failure.body?.data) {
      conflict.value = failure.body
    } else if (failure.body?.errors) {
      errors.value = failure.body.errors
      toast.danger(t('editor.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

/** Give up what was typed and take the vacancy as it now is. */
async function takeTheirs(): Promise<void> {
  const agreed = await confirm({
    title: t('editor.conflict-theirs-title'),
    message: t('editor.conflict-theirs-text'),
    confirmText: t('editor.conflict-theirs'),
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
  if (!vacancy.value) return

  const agreed = await confirm({
    title: t('editor.discard-title'),
    message: t('editor.discard-text'),
    confirmText: t('editor.discard'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  working.value = true

  try {
    take(await api.discard(vacancy.value.id))
    toast.success(t('editor.discarded'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

/** Publishing is asked about, because it is the one action here that visitors see. */
async function publish(): Promise<void> {
  const row = vacancy.value

  if (!row) return

  const agreed = await confirm({
    title: t('editor.publish-title', { title: title.value || row.title }),
    message:
      row.path === null
        ? t('editor.publish-nowhere')
        : t('editor.publish-text', { address: `/${row.path}` }),
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
 * The same position somewhere else: a copy as a draft, opened at once (decision 20). What is typed
 * here goes first — the copy is made from what the server holds, and an edit still in the pause
 * would be in the original and missing from the copy.
 */
async function duplicate(): Promise<void> {
  const row = vacancy.value

  if (!row) return

  if (dirty.value) await save()
  if (conflict.value || dirty.value) return

  working.value = true

  try {
    const copy = await api.duplicate(row.id)

    toast.success(t('panel.duplicated'))
    // The same route with another id: the editor stays and reads the copy (`watch(id)`).
    await router.push({ path: `${list.value}/${copy.vacancy.id}`, query: { ...route.query } })
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

function badge(): 'default' | 'success' {
  const status = vacancy.value?.status

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
    title: t('editor.leave-title'),
    message: t('editor.leave-text'),
    confirmText: t('editor.leave'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })
})

/* What leads away from the vacancy. On a phone the head folds them into the ···. */
const actions = computed<ScreenAction[]>(() => {
  const leads: ScreenAction[] = []

  if (previewUrl.value) {
    leads.push({ key: 'preview', label: t('editor.preview'), icon: 'eye', href: previewUrl.value })
  }

  if (vacancy.value?.url && vacancy.value.status !== 'draft') {
    leads.push({
      key: 'site',
      label: t('panel.open-on-site'),
      icon: 'link',
      href: vacancy.value.url,
    })
  }

  // Throwing away writing lives in the ···, never beside "publish", and only while there is a
  // difference between what is written and what is on the site.
  if (vacancy.value?.status === 'modified') {
    leads.push({
      key: 'discard',
      label: t('editor.discard'),
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
  <div class="wx-vacancy-editor" @focusout="onFocusOut">
    <template v-if="loading || !vacancy">
      <wx-skeleton class="wx-vacancy-editor__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="8" /></wx-card>
    </template>

    <template v-else>
      <wx-screen-head
        divider
        :back="list"
        :back-label="t('module.vacancies')"
        :title="title || t('editor.untitled')"
        :subtitle="subtitle"
        :actions="actions"
      >
        <template #trail>
          <wx-breadcrumb size="sm" :label="t('editor.trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="list">
              {{ t('module.vacancies') }}
            </wx-breadcrumb-item>
            <wx-breadcrumb-item current>{{ title || t('editor.untitled') }}</wx-breadcrumb-item>
          </wx-breadcrumb>
        </template>

        <template #title-after>
          <wx-badge :type="badge()" dot>{{ t(`panel.status-${vacancy.status}`) }}</wx-badge>
          <wx-badge v-if="vacancy.status === 'modified'" type="primary" round>
            {{ t('panel.edits') }}
          </wx-badge>
          <!-- Closed, and still a page of the site: marked, off the lists (decision 4). -->
          <wx-badge v-if="vacancy.closed_reason" type="warning" round>
            {{ t(`panel.closed-${vacancy.closed_reason}`) }}
          </wx-badge>
        </template>
      </wx-screen-head>

      <!-- Somebody else wrote while this editor was typing. Both versions still exist, so the
           question is which one the site gets — a question, not a toast that disappears. -->
      <wx-alert
        v-if="conflict"
        type="warning"
        :title="t('editor.conflict-title')"
        :description="conflict.message"
      >
        <template #actions>
          <wx-button size="sm" variant="outline" @click="takeTheirs">
            {{ t('editor.conflict-theirs') }}
          </wx-button>
          <wx-button size="sm" type="primary" @click="keepMine">
            {{ t('editor.conflict-mine') }}
          </wx-button>
        </template>
      </wx-alert>

      <wx-screen v-model="values" name="vacancies.form" :errors="errors" :disabled="locked" />

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <!-- The same position in another city is a copy, not a vacancy from nothing (decision 20). -->
        <wx-button
          variant="text"
          :disabled="working"
          :aria-label="t('panel.duplicate')"
          @click="duplicate"
        >
          <template #icon><wx-icon name="copy" /></template>
          <span class="wx-vacancy-editor__word">{{ t('panel.duplicate') }}</span>
        </wx-button>

        <wx-button variant="outline" :loading="saving" :disabled="!dirty" @click="save">
          {{ t('editor.save') }}
        </wx-button>

        <wx-button
          type="primary"
          :loading="working"
          :disabled="vacancy.status === 'published' && !dirty"
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
.wx-vacancy-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-height: 0;
  container-type: inline-size;
}

.wx-vacancy-editor__ghost {
  max-width: 420px;
}

/*
 * On a phone the bar holds the state and three buttons, and with the word the third one pushes
 * the state onto a line of its own — a strip of nothing over the buttons (CLAUDE.md §4 on
 * `WxActionBar`). The copy icon is enough there; the word stays for a screen reader.
 */
@container (max-width: 480px) {
  .wx-vacancy-editor__word {
    display: none;
  }
}
</style>
