<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import {
  onBeforeRouteLeave,
  onBeforeRouteUpdate,
  useRoute,
  type RouteLocationNormalized,
} from 'vue-router'
import {
  confirm,
  localizedValue,
  toast,
  useLocales,
  WxActionBar,
  WxBadge,
  WxButton,
  WxCard,
  WxFormItem,
  WxSelect,
  WxSkeleton,
  type LocalizedValue,
  type SelectModelValue,
  type SelectOption,
} from '@webx-ui/core'
import {
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
import { createAuthorsApi, type AuthorAccount, type AuthorDetail, type AuthorRow } from './authors'
import { useBlogMessages } from './i18n'

/**
 * One author's profile: the pane beside the list, and the whole screen on a phone (§19.5).
 *
 * The described screen `blog.author-form` with a head over it and one button under it, as a
 * person of the team: no draft (decision 6) — what is saved is on the site at once.
 *
 * A new profile is a form first and a record on the first save, and the one thing it asks that
 * the screen does not is whose it is: the account is chosen once, here, above the screen, from
 * the accounts that have none — whose profile this is does not change afterwards.
 *
 * The way back is drawn here and not by the pane around it: the drawer a narrow screen opens
 * this in has no close of its own on purpose (CLAUDE.md §4).
 */
defineOptions({ name: 'WxAuthorPane' })

const props = withDefaults(
  defineProps<{
    /** `null` — a profile not written yet. */
    id: number | null
    /** False in the drawer a narrow screen opens this in. */
    inline?: boolean
  }>(),
  { inline: true },
)

const emit = defineEmits<{
  back: []
  /** The profile was written; the list redraws its row. */
  saved: [author: AuthorRow]
  /** The first save of a new profile: a record now, with an id to be opened by. */
  created: [author: AuthorRow]
  removed: [id: number]
}>()

const context = useAdmin()
const api = createAuthorsApi(context)
const route = useRoute()
const locales = useLocales()
useBlogMessages()

const t = useTranslate('webx-blog')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const author = ref<AuthorRow | null>(null)
const values = ref<ScreenModel>({})
const snapshot = ref('')

const accounts = ref<AuthorAccount[]>([])
const account = ref<number | null>(null)

const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => context.can('blog.authors.manage'))

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(
  () => snapshot.value !== '' && (current.value !== snapshot.value || account.value !== null),
)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value || props.id === null ? 'unsaved' : 'saved'
})

/** The name follows the field rather than the answer: an edit shows at the top as it is typed. */
const title = computed(() => {
  const written = values.value.title as LocalizedValue | string | null | undefined
  const fallback = props.id === null ? t('author.new') : (author.value?.name ?? '')

  return localizedValue(written, locales.active.value, fallback) || t('author.untitled')
})

const accountOptions = computed<SelectOption[]>(() =>
  accounts.value.map((one) => ({
    label:
      one.articles_count > 0
        ? `${one.name} · ${t('author.articles', { count: one.articles_count })}`
        : one.name,
    value: one.id,
  })),
)

const chosenAccount = computed<SelectModelValue>({
  get: () => account.value,
  set: (next) => {
    account.value = typeof next === 'number' ? next : null

    // The account's name is the first guess at the name for readers, until somebody writes one.
    const picked = accounts.value.find((one) => one.id === account.value)
    const written = values.value.title as Record<string, string> | undefined
    // The first language of the panel is the site's main one — the one the name is required in.
    const main = locales.list.value[0]?.code

    if (picked && main && !(written?.[main] ?? '').trim()) {
      values.value = { ...values.value, title: { ...(written ?? {}), [main]: picked.name } }
    }
  },
})

function take(detail: AuthorDetail): void {
  author.value = detail.author
  values.value = detail.values
  snapshot.value = JSON.stringify(detail.values)
  account.value = null
}

async function load(): Promise<void> {
  loading.value = true

  try {
    if (props.id === null) {
      // What the server would answer for a profile with nothing in it yet.
      const blank: ScreenModel = {
        title: {},
        slug: {},
        role: {},
        bio: {},
        photo: null,
        is_public: false,
      }

      values.value = blank
      snapshot.value = JSON.stringify(blank)
      accounts.value = await api.accounts()
      // The article editor's «Make a profile» names the account the profile is for.
      const wanted = Number(route.query.account)

      if (accounts.value.some((one) => one.id === wanted)) chosenAccount.value = wanted
    } else {
      take(await api.get(props.id))
    }
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

    if (made && account.value === null) {
      errors.value = { user_id: [t('author.account-missing')] }
      toast.danger(t('author.save-failed'))

      return
    }

    const detail = made
      ? await api.create(account.value as number, values.value)
      : await api.save(props.id as number, values.value)

    take(detail)
    toast.success(t('author.saved'))
    if (made) emit('created', detail.author)
    else emit('saved', detail.author)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('author.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

async function remove(): Promise<void> {
  const record = author.value

  if (!record) return

  const agreed = await confirm({
    title: t('author.delete-title', { name: record.name }),
    message: t('author.delete-text'),
    confirmText: t('author.delete'),
    cancelText: t('author.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(record.id)
    toast.success(t('author.deleted'))
    // Nothing left to protect: the profile is gone.
    snapshot.value = current.value
    account.value = null
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
    title: t('author.leave-title'),
    message: t('author.leave-text'),
    confirmText: t('author.leave'),
    cancelText: t('author.cancel'),
    tone: 'danger',
  })
}

/* The list and this form are one route: another author is an update of it rather than a way out. */
onBeforeRouteUpdate(async (to: RouteLocationNormalized, from: RouteLocationNormalized) =>
  to.query.author === from.query.author ? true : await mayLeave(),
)

onBeforeRouteLeave(mayLeave)

/* What leads away from the profile. On a narrow pane the head folds them into the ···. */
const actions = computed<ScreenAction[]>(() => {
  const list: ScreenAction[] = []
  const record = author.value

  if (record?.open && record.url) {
    list.push({
      key: 'open',
      label: t('author.open-page'),
      icon: 'external-link',
      run: () => void window.open(record.url as string, '_blank', 'noopener'),
    })
  }

  if (canManage.value && record !== null) {
    list.push({
      key: 'delete',
      label: t('author.delete'),
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
  <div class="wx-author" @keydown="onKeydown">
    <wx-skeleton v-if="loading" class="wx-author__ghost" title :rows="6" />

    <template v-else>
      <wx-screen-head
        :level="3"
        :back="!props.inline"
        :back-label="t('author.back')"
        :title="title"
        :actions="actions"
        :collapse-below="480"
        @back="emit('back')"
      >
        <template v-if="author && !author.open" #title-after>
          <wx-badge dot>{{ t('author.page-closed') }}</wx-badge>
        </template>
      </wx-screen-head>

      <!-- Whose profile this is: chosen once, for a new one; the account of an existing one is shown. -->
      <wx-card v-if="props.id === null">
        <wx-form-item
          :label="t('author.account')"
          :help="accounts.length === 0 ? t('author.no-accounts') : t('author.account-help')"
          :error="errors.user_id?.[0]"
          required
        >
          <wx-select
            v-model="chosenAccount"
            :options="accountOptions"
            :disabled="!canManage || saving || accounts.length === 0"
            filterable
            teleport
            :aria-label="t('author.account')"
          />
        </wx-form-item>
      </wx-card>

      <p v-else-if="author?.user" class="wx-author__account">
        {{ t('author.account') }}: {{ author.user.name }}
      </p>

      <wx-screen
        v-model="values"
        name="blog.author-form"
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
          {{ t('author.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-author {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
  padding: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-author__ghost {
  max-width: 480px;
}

.wx-author__account {
  margin: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}
</style>
