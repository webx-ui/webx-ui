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
  useAdmin,
  useErrorText,
  useTranslate,
  WxSaveState,
  WxScreen,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import { createTariffsApi } from './api'
import { useTariffsMessages } from './i18n'
import type { TariffDetail, TariffSummary } from './types'

/**
 * One tariff: the pane beside the list, and the whole screen on a phone.
 *
 * The described screen `tariffs.form` with a head over it and one button under it. One explicit
 * save, and no autosave: a tariff has no draft (decision 10), so what is saved is on the site at
 * once — the button is the moment somebody decides it is ready.
 *
 * A new tariff is a form first and a record on the first save: an empty row made by pressing
 * "New tariff" and walking away would be a tariff with nothing in it on the list for ever.
 *
 * The way back is drawn here and not by the pane around it: the drawer a narrow screen opens
 * this in has no close of its own on purpose (CLAUDE.md §4).
 */
defineOptions({ name: 'WxTariffPane' })

const props = withDefaults(
  defineProps<{
    /** `null` — a tariff not written yet. */
    id: number | null
    /** The group the list is narrowed to: a new tariff starts in it. */
    category?: number | null
    /** False in the drawer a narrow screen opens this in. */
    inline?: boolean
  }>(),
  { category: null, inline: true },
)

const emit = defineEmits<{
  back: []
  /** The tariff was written; the list redraws its row. */
  saved: [tariff: TariffSummary]
  /** The first save of a new tariff: it is a record now, with an id to be opened by. */
  created: [tariff: TariffSummary]
  removed: [id: number]
}>()

const context = useAdmin()
const api = createTariffsApi(context)
const locales = useLocales()
useTariffsMessages()

const t = useTranslate('webx-tariffs')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const tariff = ref<TariffSummary | null>(null)
const values = ref<ScreenModel>({})
const snapshot = ref('')

const loading = ref(props.id !== null)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => context.can('tariffs.manage'))

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value || props.id === null ? 'unsaved' : 'saved'
})

/** The name follows the field rather than the answer: an edit shows at the top as it is typed. */
const title = computed(() => {
  const written = values.value.name as LocalizedValue | string | null | undefined
  const fallback = props.id === null ? t('tariff.new') : (tariff.value?.name ?? '')

  return localizedValue(written, locales.active.value, fallback) || t('tariff.untitled')
})

function take(detail: TariffDetail): void {
  tariff.value = detail.tariff
  values.value = detail.values
  snapshot.value = JSON.stringify(detail.values)
}

if (props.id === null) {
  // What the server would answer for a tariff with nothing in it yet. No currency: the panel does
  // not know the site's list, and the server gives a new tariff its first one (decision 15).
  const blank: ScreenModel = {
    name: {},
    badge: {},
    price: null,
    period: {},
    price_text: {},
    features: [],
    description: {},
    button_label: {},
    button_link: null,
    featured: false,
    published: false,
    categories: props.category === null ? [] : [props.category],
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
    toast.success(t('tariff.saved'))
    if (made) emit('created', detail.tariff)
    else emit('saved', detail.tariff)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('tariff.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

async function remove(): Promise<void> {
  const record = tariff.value

  if (!record) return

  const agreed = await confirm({
    title: t('tariff.delete-title', { name: record.name }),
    message: t('tariff.delete-text'),
    confirmText: t('tariff.delete'),
    cancelText: t('tariff.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(record.id)
    toast.success(t('tariff.deleted'))
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
    title: t('tariff.leave-title'),
    message: t('tariff.leave-text'),
    confirmText: t('tariff.leave'),
    cancelText: t('tariff.cancel'),
    tone: 'danger',
  })
}

/*
 * The list and this form are one route, so another tariff is an update of it rather than a way
 * out — and so is every keystroke in the search above the list, which goes into the address too.
 * Only a change of the tariff asks.
 */
onBeforeRouteUpdate(async (to: RouteLocationNormalized, from: RouteLocationNormalized) =>
  to.query.tariff === from.query.tariff ? true : await mayLeave(),
)

onBeforeRouteLeave(mayLeave)

/* What leads away from the tariff. On a narrow pane the head folds them into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value && tariff.value !== null
    ? [
        {
          key: 'delete',
          label: t('tariff.delete'),
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
  <div class="wx-tariff" @keydown="onKeydown">
    <wx-skeleton v-if="loading" class="wx-tariff__ghost" title :rows="6" />

    <template v-else>
      <wx-screen-head
        :level="3"
        :back="!props.inline"
        :back-label="t('tariff.back')"
        :title="title"
        :actions="actions"
        :collapse-below="480"
        @back="emit('back')"
      >
        <template v-if="tariff && !tariff.published" #title-after>
          <wx-badge dot>{{ t('tariff.not-published') }}</wx-badge>
        </template>
      </wx-screen-head>

      <wx-screen
        v-model="values"
        name="tariffs.form"
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
          {{ t('tariff.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-tariff {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
  padding: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-tariff__ghost {
  max-width: 480px;
}
</style>
