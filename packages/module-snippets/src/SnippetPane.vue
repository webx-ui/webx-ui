<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, type RouteLocationNormalized } from 'vue-router'
import { confirm, toast, WxActionBar, WxAlert, WxBadge, WxButton, WxSkeleton } from '@webx-ui/core'
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
import { createSnippetsApi } from './api'
import { provideSnippetsCopy, silentHere } from './copy'
import { useSnippetsMessages } from './i18n'
import type { SnippetDetail, SnippetPlace, SnippetRow } from './types'

/**
 * One code: the pane beside the list, and the whole screen on a phone.
 *
 * The described screen `snippets.form` — the code in a highlighting editor, its place, consent,
 * coverage and the journal — with a head over it and one button under it. One explicit save and
 * no autosave: a code has no draft (decision 4), so a save of an enabled code is on every page
 * at once, and the button is the moment somebody decides it is ready.
 *
 * What the server says about the code itself — the tags of a whole document (§7.4) — does not
 * stop the save; it stands above the form until the code is saved without them.
 *
 * The way back is drawn here and not by the pane around it: the drawer a narrow screen opens
 * this in has no close of its own on purpose (CLAUDE.md §4).
 */
defineOptions({ name: 'WxSnippetPane' })

const props = withDefaults(
  defineProps<{
    /** `null` — a code not written yet. */
    id: number | null
    /** The place a new code starts in. */
    place?: string
    /** The places of the list: whether the code's place is still declared. */
    places?: SnippetPlace[]
    /** False in the drawer a narrow screen opens this in. */
    inline?: boolean
    /** `APP_ENV` of the site, for the mark of a code it does not print. */
    environment?: string
    /** False outside the environments that print everything: only the codes marked "on dev too". */
    everythingPrinted?: boolean
  }>(),
  {
    place: 'head',
    places: () => [],
    inline: true,
    environment: 'production',
    everythingPrinted: true,
  },
)

const emit = defineEmits<{
  back: []
  /** The code was written; the list redraws its row. */
  saved: [row: SnippetRow]
  /** The first save of a new code: it is a record now, with an id to be opened by. */
  created: [row: SnippetRow]
  removed: [id: number]
}>()

const context = useAdmin()
const api = createSnippetsApi(context)
useSnippetsMessages()

const t = useTranslate('webx-snippets')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const snippet = ref<SnippetRow | null>(null)
const values = ref<ScreenModel>({})
const snapshot = ref('')
const warnings = ref<string[]>([])

const loading = ref(props.id !== null)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => context.can('snippets.manage'))

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value || props.id === null ? 'unsaved' : 'saved'
})

/** The name follows the field rather than the answer: an edit shows at the top as it is typed. */
const title = computed(() => {
  const written = typeof values.value.name === 'string' ? values.value.name.trim() : ''

  if (written !== '') return written

  return props.id === null ? t('panel.new') : t('panel.untitled')
})

/*
 * The place chosen in the form, if the config no longer has it (§4.3). Asked of the list rather
 * than of the select: the select only offers what is declared, so it cannot show what is not.
 */
const orphaned = computed(() => {
  const place = typeof values.value.place === 'string' ? values.value.place : ''

  if (place === '' || props.places.length === 0) return null

  return props.places.some((one) => one.declared && one.key === place) ? null : place
})

provideHistorySubject({ id: computed(() => snippet.value?.id ?? null) })

/* Read from the values on screen, not the saved row: the mark follows the switches as they are flipped. */
provideSnippetsCopy({
  environment: computed(() => props.environment),
  silent: computed(() =>
    silentHere({ enabled: true, on_stage: values.value.on_stage }, props.everythingPrinted),
  ),
})

function take(detail: SnippetDetail): void {
  snippet.value = detail.snippet
  values.value = detail.values
  snapshot.value = JSON.stringify(detail.values)
  warnings.value = detail.warnings
}

if (props.id === null) {
  // What the server would answer for a code with nothing in it yet: off, until switched on.
  const blank: ScreenModel = {
    name: '',
    place: props.place,
    code: '',
    consent: 'necessary',
    only_paths: [],
    except_paths: [],
    locales: [],
    on_stage: false,
    enabled: false,
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

    if (detail.warnings.length > 0) {
      toast.warning(t('panel.saved-warning', { warning: detail.warnings.join(' ') }))
    } else {
      toast.success(t('panel.saved'))
    }

    if (made) emit('created', detail.snippet)
    else emit('saved', detail.snippet)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('panel.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

async function remove(): Promise<void> {
  const row = snippet.value

  if (!row) return

  const agreed = await confirm({
    title: t('panel.delete-title', { title: title.value }),
    message: t('panel.delete-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(t('panel.deleted'))
    // Nothing left to protect: the record is in the bin.
    snapshot.value = current.value
    emit('removed', row.id)
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

// And from the page around the form, where a click on empty space leaves the focus.
useBodyKeys(onKeydown)

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
    title: t('panel.leave-title'),
    message: t('panel.leave-text'),
    confirmText: t('panel.leave'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })
}

/* The list and this form are one route: only a change of the code asks. */
onBeforeRouteUpdate(async (to: RouteLocationNormalized, from: RouteLocationNormalized) =>
  to.query.snippet === from.query.snippet ? true : await mayLeave(),
)

onBeforeRouteLeave(mayLeave)

/* What leads away from the code. On a narrow pane the head folds them into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value && snippet.value !== null
    ? [
        {
          key: 'delete',
          label: t('panel.delete'),
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
  <div class="wx-snippets-pane" @keydown="onKeydown">
    <wx-skeleton v-if="loading" class="wx-snippets-pane__ghost" title :rows="8" />

    <template v-else>
      <wx-screen-head
        :level="3"
        :back="!props.inline"
        :back-label="t('panel.back')"
        :title="title"
        :actions="actions"
        :collapse-below="480"
        @back="emit('back')"
      >
        <template v-if="snippet && !snippet.enabled" #title-after>
          <wx-badge dot>{{ t('panel.off') }}</wx-badge>
        </template>
      </wx-screen-head>

      <wx-alert
        v-if="orphaned !== null"
        type="warning"
        :title="t('panel.undeclared')"
        :description="t('panel.orphaned-code', { place: orphaned })"
      />

      <!-- Over the editor, where the code is: a save went through, and this is what to fix. -->
      <wx-alert v-for="warning in warnings" :key="warning" type="warning" :description="warning" />

      <wx-screen
        v-model="values"
        name="snippets.form"
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
          {{ t('panel.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-snippets-pane {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
  padding: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-snippets-pane__ghost {
  max-width: 480px;
}
</style>
