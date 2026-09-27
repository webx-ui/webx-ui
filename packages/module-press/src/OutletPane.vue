<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, type RouteLocationNormalized } from 'vue-router'
import {
  confirm,
  localizedValue,
  toast,
  useLocales,
  WxActionBar,
  WxBadge,
  WxButton,
  WxSkeleton,
  type LocalizedValue,
} from '@webx-ui/core'
import {
  provideRecordAddress,
  useAdmin,
  useErrorText,
  useTranslate,
  WxSaveState,
  WxScreen,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import { createPressApi } from './api'
import { usePressMessages } from './i18n'
import type { OutletDetail, OutletSummary } from './types'

/**
 * One outlet with its articles: the pane beside the list, and the whole screen on a phone.
 *
 * The described screen `press.outlet-form` — General · Articles · SEO — with a head over it and
 * one button under it. One explicit save for the whole outlet, articles included (decision 15):
 * the articles are rows of its form, and the server writes them into their own table in the same
 * transaction (§4.6). No autosave: there is no draft (decision 9), so what is saved is on the site
 * at once — the button is the moment somebody decides it is ready.
 *
 * A new outlet is a form first and a record on the first save: an empty row made by pressing
 * "New outlet" and walking away would be an outlet with nothing in it on the list for ever.
 *
 * The way back is drawn here and not by the pane around it: the drawer a narrow screen opens
 * this in has no close of its own on purpose (CLAUDE.md §4).
 */
defineOptions({ name: 'WxOutletPane' })

const props = withDefaults(
  defineProps<{
    /** `null` — an outlet not written yet. */
    id: number | null
    /** False in the drawer a narrow screen opens this in. */
    inline?: boolean
  }>(),
  { inline: true },
)

const emit = defineEmits<{
  back: []
  /** The outlet was written; the list redraws its row. */
  saved: [outlet: OutletSummary]
  /** The first save of a new outlet: it is a record now, with an id to be opened by. */
  created: [outlet: OutletSummary]
  removed: [id: number]
}>()

const context = useAdmin()
const api = createPressApi(context)
const locales = useLocales()
usePressMessages()

const t = useTranslate('webx-press')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const outlet = ref<OutletSummary | null>(null)
const values = ref<ScreenModel>({})
const prefix = ref<string | null>(null)
const snapshot = ref('')

const loading = ref(props.id !== null)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => context.can('press.manage'))

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value || props.id === null ? 'unsaved' : 'saved'
})

/** The name follows the field rather than the answer: an edit shows at the top as it is typed. */
const title = computed(() => {
  const written = values.value.title as LocalizedValue | string | null | undefined
  const fallback = props.id === null ? t('outlet.new') : (outlet.value?.title ?? '')

  return localizedValue(written, locales.active.value, fallback) || t('outlet.untitled')
})

/*
 * The address field is the panel's shared one (`wx-slug`), and this is what it prints. The form
 * answers with the page's address (`url`); the field wants it without the host and the slash.
 */
provideRecordAddress({
  values,
  prefix,
  path: computed(() => pathOf(outlet.value?.url ?? null)),
  moving: () => t('outlet.address-moving'),
})

function pathOf(url: string | null): string | null {
  if (url === null) return null

  try {
    return new URL(url, 'http://site.invalid').pathname.replace(/^\/+/, '')
  } catch {
    return null
  }
}

function take(detail: OutletDetail): void {
  outlet.value = detail.outlet
  values.value = detail.values
  prefix.value = detail.prefix
  snapshot.value = JSON.stringify(detail.values)
}

if (props.id === null) {
  // What the server would answer for an outlet with nothing in it yet.
  const blank: ScreenModel = {
    title: {},
    slug: {},
    summary: {},
    published: false,
    featured: false,
    articles: [],
  }

  values.value = blank
  snapshot.value = JSON.stringify(blank)
}

async function load(): Promise<void> {
  if (props.id === null) return

  loading.value = true

  try {
    take(await api.get(props.id))
  } catch (error) {
    toast.danger(message(error))
    emit('back')
  } finally {
    loading.value = false
  }
}

async function save(): Promise<void> {
  if (saving.value || !canManage.value) return

  saving.value = true
  errors.value = {}

  try {
    const made = props.id === null
    const detail = made
      ? await api.create(values.value)
      : await api.save(props.id as number, values.value)

    take(detail)
    toast.success(t('outlet.saved'))
    if (made) emit('created', detail.outlet)
    else emit('saved', detail.outlet)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      // `articles.2.url` as the server names it: the repeater finds its row and field by that.
      errors.value = body.errors
      toast.danger(t('outlet.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

async function remove(): Promise<void> {
  const record = outlet.value

  if (!record) return

  const agreed = await confirm({
    title: t('outlet.delete-title', { name: record.title }),
    message: t('outlet.delete-text'),
    confirmText: t('outlet.delete'),
    cancelText: t('outlet.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(record.id)
    toast.success(t('outlet.deleted'))
    // Nothing left to protect: the record is in the bin.
    snapshot.value = current.value
    emit('removed', record.id)
  } catch (error) {
    toast.danger(message(error))
  }
}

/* The browser's own guard: it cannot wait for a request, so all it can do is ask. */
function leaveGuard(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

/* Ctrl+S saves, as it does everywhere else that has a save button. */
function onKeydown(event: KeyboardEvent): void {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
    event.preventDefault()
    if (dirty.value || props.id === null) void save()
  }
}

onMounted(() => {
  void load()
  window.addEventListener('beforeunload', leaveGuard)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', leaveGuard)
})

async function mayLeave(): Promise<boolean> {
  if (!dirty.value || !canManage.value) return true

  return await confirm({
    title: t('outlet.leave-title'),
    message: t('outlet.leave-text'),
    confirmText: t('outlet.leave'),
    cancelText: t('outlet.cancel'),
    tone: 'danger',
  })
}

/*
 * The list and this form are one route, so another outlet is an update of it rather than a way
 * out — and so is every keystroke in the search above the list, which goes into the address too.
 * Only a change of the outlet asks.
 */
onBeforeRouteUpdate(async (to: RouteLocationNormalized, from: RouteLocationNormalized) =>
  to.query.outlet === from.query.outlet ? true : await mayLeave(),
)

onBeforeRouteLeave(mayLeave)

/* What leads away from the outlet. On a narrow pane the head folds them into the ···. */
const actions = computed<ScreenAction[]>(() => {
  const record = outlet.value
  const list: ScreenAction[] = []

  // Only a page that answers: an unpublished outlet, or one seen nowhere, has no address.
  if (record?.url) {
    const url = record.url

    list.push({
      key: 'open',
      label: t('outlet.open'),
      icon: 'external-link',
      run: () => void window.open(url, '_blank', 'noopener'),
    })
  }

  if (canManage.value && record !== null) {
    list.push({
      key: 'delete',
      label: t('outlet.delete'),
      icon: 'trash',
      danger: true,
      menu: true,
      run: () => void remove(),
    })
  }

  return list
})
</script>

<template>
  <div class="wx-outlet" @keydown="onKeydown">
    <wx-skeleton v-if="loading" class="wx-outlet__ghost" title :rows="6" />

    <template v-else>
      <wx-screen-head
        :level="3"
        :back="!props.inline"
        :back-label="t('outlet.back')"
        :title="title"
        :actions="actions"
        :collapse-below="480"
        @back="emit('back')"
      >
        <template v-if="outlet && !outlet.published" #title-after>
          <wx-badge dot>{{ t('outlet.not-published') }}</wx-badge>
        </template>
      </wx-screen-head>

      <wx-screen
        v-model="values"
        name="press.outlet-form"
        :errors="errors"
        :disabled="!canManage || saving"
      />

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button
          type="primary"
          :loading="saving"
          :disabled="!dirty && props.id !== null"
          @click="save"
        >
          {{ t('outlet.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-outlet {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
  padding: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-outlet__ghost {
  max-width: 480px;
}
</style>
