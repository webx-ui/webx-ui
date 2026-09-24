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
import { createFaqApi } from './api'
import { provideQuestionEditor } from './editor'
import { useFaqMessages } from './i18n'
import type { QuestionDetail, QuestionRow } from './types'

/**
 * One question: the pane beside the list, and the whole screen on a phone.
 *
 * The described screen `faq.form` with a head over it and one button under it. One explicit
 * save, and not the autosave of an article: a question has no draft (decision 12), so what is
 * saved is on the site at once — the button is the moment somebody decides it is ready.
 *
 * A new question is a form first and a record on the first save: an empty row made by pressing
 * "New question" and walking away would be a question with nothing in it on the list for ever.
 *
 * The way back is drawn here and not by the pane around it: the drawer a narrow screen opens
 * this in has no close of its own on purpose (CLAUDE.md §4).
 */
defineOptions({ name: 'WxFaqQuestionPane' })

const props = withDefaults(
  defineProps<{
    /** `null` — a question not written yet. */
    id: number | null
    /** The category the list is narrowed to: a new question starts in it. */
    category?: number | null
    /** False in the drawer a narrow screen opens this in. */
    inline?: boolean
  }>(),
  { category: null, inline: true },
)

const emit = defineEmits<{
  back: []
  /** The question was written; the list redraws its row. */
  saved: [row: QuestionRow]
  /** The first save of a new question: it is a record now, with an id to be opened by. */
  created: [row: QuestionRow]
  removed: [id: number]
}>()

const context = useAdmin()
const api = createFaqApi(context)
const locales = useLocales()
useFaqMessages()

const t = useTranslate('webx-faq')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const question = ref<QuestionRow | null>(null)
const values = ref<ScreenModel>({})
const snapshot = ref('')

const loading = ref(props.id !== null)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => context.can('faq.manage'))

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value || props.id === null ? 'unsaved' : 'saved'
})

/** The question follows the field rather than the answer: an edit shows at the top as it is typed. */
const title = computed(() => {
  const written = values.value.question as LocalizedValue | string | null | undefined
  const fallback = props.id === null ? t('question.new') : (question.value?.question ?? '')

  return localizedValue(written, locales.active.value, fallback) || t('question.untitled')
})

provideQuestionEditor({ question })

function take(detail: QuestionDetail): void {
  question.value = detail.question
  values.value = detail.values
  snapshot.value = JSON.stringify(detail.values)
}

if (props.id === null) {
  // What the server would answer for a question with nothing in it yet.
  const blank: ScreenModel = {
    question: {},
    answer: {},
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
    toast.success(t('question.saved'))
    if (made) emit('created', detail.question)
    else emit('saved', detail.question)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('question.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

async function remove(): Promise<void> {
  const row = question.value

  if (!row) return

  const agreed = await confirm({
    title: t('question.delete-title', { title: row.question }),
    message: t('question.delete-text'),
    confirmText: t('question.delete'),
    cancelText: t('question.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(t('question.deleted'))
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
    title: t('question.leave-title'),
    message: t('question.leave-text'),
    confirmText: t('question.leave'),
    cancelText: t('question.cancel'),
    tone: 'danger',
  })
}

/*
 * The list and this form are one route, so another question is an update of it rather than a
 * way out — and so is every keystroke in the search above the list, which goes into the address
 * too. Only a change of the question asks.
 */
onBeforeRouteUpdate(async (to: RouteLocationNormalized, from: RouteLocationNormalized) =>
  to.query.question === from.query.question ? true : await mayLeave(),
)

onBeforeRouteLeave(mayLeave)

/* What leads away from the question. On a narrow pane the head folds them into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value && question.value !== null
    ? [
        {
          key: 'delete',
          label: t('question.delete'),
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
  <div class="wx-faq-question" @keydown="onKeydown">
    <wx-skeleton v-if="loading" class="wx-faq-question__ghost" title :rows="6" />

    <template v-else>
      <wx-screen-head
        :level="3"
        :back="!props.inline"
        :back-label="t('question.back')"
        :title="title"
        :actions="actions"
        :collapse-below="480"
        @back="emit('back')"
      >
        <template v-if="question && !question.published" #title-after>
          <wx-badge dot>{{ t('question.not-published') }}</wx-badge>
        </template>
      </wx-screen-head>

      <wx-screen
        v-model="values"
        name="faq.form"
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
          {{ t('question.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-faq-question {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
  padding: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-faq-question__ghost {
  max-width: 480px;
}
</style>
