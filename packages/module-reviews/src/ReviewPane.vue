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
import { createReviewsApi } from './api'
import { useReviewsMessages } from './i18n'
import type { ReviewDetail, ReviewSummary } from './types'

/**
 * One review: the pane beside the list, and the whole screen on a phone.
 *
 * The described screen `reviews.form` with a head over it and one button under it. One explicit
 * save, and no autosave: a review has no draft (decision 10), so what is saved is on the site at
 * once — the button is the moment somebody decides it is ready.
 *
 * A new review is a form first and a record on the first save: an empty row made by pressing
 * "New review" and walking away would be a review with nothing in it on the list for ever.
 *
 * The way back is drawn here and not by the pane around it: the drawer a narrow screen opens
 * this in has no close of its own on purpose (CLAUDE.md §4).
 */
defineOptions({ name: 'WxReviewPane' })

const props = withDefaults(
  defineProps<{
    /** `null` — a review not written yet. */
    id: number | null
    /** The category the list is narrowed to: a new review starts in it. */
    category?: number | null
    /** False in the drawer a narrow screen opens this in. */
    inline?: boolean
  }>(),
  { category: null, inline: true },
)

const emit = defineEmits<{
  back: []
  /** The review was written; the list redraws its row. */
  saved: [review: ReviewSummary]
  /** The first save of a new review: it is a record now, with an id to be opened by. */
  created: [review: ReviewSummary]
  removed: [id: number]
}>()

const context = useAdmin()
const api = createReviewsApi(context)
const locales = useLocales()
useReviewsMessages()

const t = useTranslate('webx-reviews')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const review = ref<ReviewSummary | null>(null)
const values = ref<ScreenModel>({})
const snapshot = ref('')

const loading = ref(props.id !== null)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => context.can('reviews.manage'))

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value || props.id === null ? 'unsaved' : 'saved'
})

/** The name follows the field rather than the answer: an edit shows at the top as it is typed. */
const title = computed(() => {
  const written = values.value.name as LocalizedValue | string | null | undefined
  const fallback = props.id === null ? t('review.new') : (review.value?.name ?? '')

  return localizedValue(written, locales.active.value, fallback) || t('review.untitled')
})

function take(detail: ReviewDetail): void {
  review.value = detail.review
  values.value = detail.values
  snapshot.value = JSON.stringify(detail.values)
}

if (props.id === null) {
  // What the server would answer for a review with nothing in it yet.
  const blank: ScreenModel = {
    name: {},
    job_title: {},
    text: {},
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
    toast.success(t('review.saved'))
    if (made) emit('created', detail.review)
    else emit('saved', detail.review)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('review.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

async function remove(): Promise<void> {
  const record = review.value

  if (!record) return

  const agreed = await confirm({
    title: t('review.delete-title', { name: record.name }),
    message: t('review.delete-text'),
    confirmText: t('review.delete'),
    cancelText: t('review.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(record.id)
    toast.success(t('review.deleted'))
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
    title: t('review.leave-title'),
    message: t('review.leave-text'),
    confirmText: t('review.leave'),
    cancelText: t('review.cancel'),
    tone: 'danger',
  })
}

/*
 * The list and this form are one route, so another review is an update of it rather than a way
 * out — and so is every keystroke in the search above the list, which goes into the address too.
 * Only a change of the review asks.
 */
onBeforeRouteUpdate(async (to: RouteLocationNormalized, from: RouteLocationNormalized) =>
  to.query.review === from.query.review ? true : await mayLeave(),
)

onBeforeRouteLeave(mayLeave)

/* What leads away from the review. On a narrow pane the head folds them into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value && review.value !== null
    ? [
        {
          key: 'delete',
          label: t('review.delete'),
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
  <div class="wx-review" @keydown="onKeydown">
    <wx-skeleton v-if="loading" class="wx-review__ghost" title :rows="6" />

    <template v-else>
      <wx-screen-head
        :level="3"
        :back="!props.inline"
        :back-label="t('review.back')"
        :title="title"
        :actions="actions"
        :collapse-below="480"
        @back="emit('back')"
      >
        <template v-if="review && !review.published" #title-after>
          <wx-badge dot>{{ t('review.not-published') }}</wx-badge>
        </template>
      </wx-screen-head>

      <wx-screen
        v-model="values"
        name="reviews.form"
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
          {{ t('review.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-review {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
  padding: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-review__ghost {
  max-width: 480px;
}
</style>
