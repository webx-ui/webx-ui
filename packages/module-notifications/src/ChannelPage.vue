<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  confirm,
  createModal,
  toast,
  useLocales,
  WxActionBar,
  WxAlert,
  WxBadge,
  WxButton,
  WxSkeleton,
} from '@webx-ui/core'
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
import type { Patch, ScreenModel } from '@webx-ui/schema'
import { createNotificationsApi } from './api'
import { provideChannel } from './context'
import { fillOnEvent, sampleOf } from './defaults'
import { useNotificationsMessages } from './i18n'
import MessagePreview from './MessagePreview.vue'
import TestDialog from './TestDialog.vue'
import type {
  Administrator,
  ChannelDetail,
  ChannelRow,
  ChatRecipient,
  EmailRecipient,
  NotificationsMeta,
} from './types'

/**
 * One channel (§10.3): the described screen `notifications.channel` — the common part and the
 * provider's settings the server lays over it — with the live preview beside it on a wide pane and
 * under it on a narrow one, and one explicit save.
 *
 * A secret is never in the form: the server answers which are set, and the field says so with
 * `••••••••` until a new one is typed. "Forget" is in the head's menu, one per secret that is set,
 * and takes effect on the save, like every other change here.
 *
 * The form's values are what "Check", "Send a test" and the preview run on — saved or not: a
 * channel is tried before it is kept, and a test of a switched-off channel is the point of one.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/notifications' })

const admin = useAdmin()
const api = createNotificationsApi(admin)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
useNotificationsMessages()

const t = useTranslate('webx-notifications')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const id = ref<number | null>(
  typeof route.params.id === 'string' && route.params.id !== '' ? Number(route.params.id) : null,
)

const meta = ref<NotificationsMeta | null>(null)
const channel = ref<ChannelRow | null>(null)
const values = ref<ScreenModel>({})
const snapshot = ref('')
const warnings = ref<string[]>([])
const errors = ref<Record<string, string[]>>({})
const loading = ref(true)
const saving = ref(false)
const checking = ref(false)

const context = provideChannel(meta, values, id)

provideHistorySubject({ id: computed(() => channel.value?.id ?? null) })

const canManage = computed(() => admin.can('notifications.manage'))

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value || id.value === null ? 'unsaved' : 'saved'
})

const title = computed(() => {
  const written = typeof values.value.name === 'string' ? values.value.name.trim() : ''

  if (written !== '') return written

  return id.value === null ? t('panel.new') : t('panel.untitled')
})

const forgetting = computed<string[]>(() =>
  Array.isArray(values.value.forget) ? (values.value.forget as string[]) : [],
)

/** The provider's secret settings, and whether each is set on the saved channel. */
const secrets = computed(() =>
  (context.provider.value?.fields ?? [])
    .filter((field) => field.secret)
    .map((field) => ({
      field,
      set:
        channel.value?.credentials_set[field.name] === true &&
        !forgetting.value.includes(field.name),
    })),
)

/*
 * What only this page knows about the tree: the kind of channel is chosen once, and a secret that
 * is set says so instead of showing an empty field that reads as "not set".
 */
const patch = computed<Patch>(() => [
  { op: 'set', target: 'provider', props: { disabled: true } },
  ...secrets.value
    .filter((one) => one.set)
    .map((one): Patch[number] => ({
      op: 'set',
      target: one.field.name,
      help: t('panel.secret-set'),
      props: { placeholder: '••••••••' },
    })),
])

function take(detail: ChannelDetail): void {
  channel.value = detail.channel
  values.value = detail.values
  snapshot.value = JSON.stringify(detail.values)
  warnings.value = detail.warnings
}

/** What the server would answer for a channel with nothing in it yet, and the event's default template. */
function blank(found: NotificationsMeta): ScreenModel {
  const provider = typeof route.query.provider === 'string' ? route.query.provider : 'email'
  const event =
    typeof route.query.event === 'string' && route.query.event !== '' ? route.query.event : null
  const sample = sampleOf(
    found.events.find((one) => one.key === event),
    provider,
  )
  const locale = locales.list.value[0]?.code ?? 'en'
  const settings: ScreenModel = {}

  for (const field of found.providers.find((one) => one.key === provider)?.fields ?? []) {
    settings[field.name] = field.secret ? '' : (field.default ?? '')
  }

  return {
    name: '',
    provider,
    event,
    enabled: false,
    recipients: [],
    ...settings,
    template: sample ? { ...sample.template } : { [locale]: '' },
    subject: sample?.subject ? { ...sample.subject } : { [locale]: '' },
  }
}

// An event chosen for a channel whose message is still empty brings its default template.
fillOnEvent(context, loading, () => toast.info(t('panel.default-inserted')))

const administrators = ref<Administrator[]>([])

async function load(): Promise<void> {
  loading.value = true

  try {
    const [found, detail] = await Promise.all([
      api.meta(),
      id.value === null ? Promise.resolve(null) : api.get(id.value),
    ])

    meta.value = found

    if (detail === null) {
      const made = blank(found)

      values.value = made
      snapshot.value = JSON.stringify(made)
    } else {
      take(detail)
    }
  } catch (error) {
    toast.danger(message(error))
    void router.push(props.base)
  } finally {
    loading.value = false
  }

  if (values.value.provider === 'email' && canManage.value) {
    api
      .administrators()
      .then((found) => (administrators.value = found))
      .catch(() => undefined)
  }
}

function refusal(error: unknown): void {
  const body = (error as { body?: { errors?: Record<string, string[]> } }).body

  if (body?.errors) {
    errors.value = body.errors
    toast.danger(t('panel.save-failed'))
  } else {
    toast.danger(message(error))
  }
}

async function save(): Promise<void> {
  if (saving.value || !canManage.value) return

  saving.value = true
  errors.value = {}

  try {
    const made = id.value === null
    const detail = made
      ? await api.create(values.value)
      : await api.save(id.value as number, values.value)

    take(detail)

    if (detail.warnings.length > 0) {
      toast.warning(t('panel.saved-warning', { warning: detail.warnings.join(' ') }))
    } else {
      toast.success(t('panel.saved'))
    }

    if (made) {
      id.value = detail.channel.id
      // Nothing left to protect: the record exists, and its address is the one to keep.
      void router.replace(`${props.base}/channels/${detail.channel.id}`)
    }
  } catch (error) {
    refusal(error)
  } finally {
    saving.value = false
  }
}

async function check(): Promise<void> {
  checking.value = true

  try {
    const answer = await api.check(id.value, values.value)

    if (answer.ok) toast.success(answer.message || t('panel.check-ok'))
    else toast.warning(answer.message)
  } catch (error) {
    refusal(error)
  } finally {
    checking.value = false
  }
}

const tester = createModal<
  void,
  { channel?: number | null; values: ScreenModel; recipients?: { value: string; label: string }[] }
>(TestDialog)

/** The fixed recipients as the server knows them by: a chat id, an address. */
const fixed = computed(() => {
  const list = Array.isArray(values.value.recipients) ? values.value.recipients : []

  return (list as (ChatRecipient & EmailRecipient)[]).flatMap((one) => {
    if (one.chat_id)
      return [
        { value: one.chat_id, label: one.label ? `${one.label} · ${one.chat_id}` : one.chat_id },
      ]
    if (one.email) return [{ value: one.email, label: one.email }]

    const found = administrators.value.find((person) => person.id === one.admin_id)

    return found ? [{ value: found.email, label: `${found.name} · ${found.email}` }] : []
  })
})

async function test(): Promise<void> {
  await tester({ channel: id.value, values: values.value, recipients: fixed.value })
}

async function copy(): Promise<void> {
  if (id.value === null) return

  try {
    const detail = await api.copy(id.value)

    toast.success(t('panel.copied', { name: detail.channel.name }))
    void router.push(`${props.base}/channels/${detail.channel.id}`)
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(): Promise<void> {
  const row = channel.value

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
    snapshot.value = current.value
    void router.push(props.base)
  } catch (error) {
    toast.danger(message(error))
  }
}

function forget(name: string, label: string): void {
  values.value = { ...values.value, forget: [...forgetting.value, name], [name]: '' }
  toast.info(t('panel.forgotten', { field: label }))
}

/* The browser's own guard: it cannot wait for a request, so all it can do is ask. */
function leaveGuard(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

/* Ctrl+S saves, as it does everywhere else that has a save button. */
function onKeydown(event: KeyboardEvent): void {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
    event.preventDefault()
    if (dirty.value || id.value === null) void save()
  }
}

useBodyKeys(onKeydown)

onMounted(() => {
  void load()
  window.addEventListener('beforeunload', leaveGuard)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', leaveGuard)
})

onBeforeRouteLeave(async () => {
  if (!dirty.value || !canManage.value) return true

  return await confirm({
    title: t('panel.leave-title'),
    message: t('panel.leave-text'),
    confirmText: t('panel.leave'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })
})

const actions = computed<ScreenAction[]>(() => {
  if (!canManage.value) return []

  const list: ScreenAction[] = [
    {
      key: 'check',
      label: t('panel.check'),
      icon: 'check-circle',
      loading: checking.value,
      run: () => void check(),
    },
    { key: 'test', label: t('panel.test'), icon: 'send', run: () => void test() },
  ]

  if (channel.value !== null) {
    list.push({
      key: 'copy',
      label: t('panel.copy'),
      icon: 'copy',
      menu: true,
      run: () => void copy(),
    })
  }

  for (const one of secrets.value.filter((secret) => secret.set)) {
    list.push({
      key: `forget-${one.field.name}`,
      label: t('panel.forget', { field: one.field.label }),
      icon: 'unlock',
      menu: true,
      run: () => forget(one.field.name, one.field.label),
    })
  }

  if (channel.value !== null) {
    list.push({
      key: 'delete',
      label: t('panel.delete'),
      icon: 'trash',
      danger: true,
      run: () => void remove(),
    })
  }

  return list
})

const environment = computed(() => meta.value?.environment ?? 'production')
</script>

<template>
  <div class="wx-notifications-channel" @keydown="onKeydown">
    <wx-skeleton v-if="loading" class="wx-notifications-channel__ghost" title :rows="8" />

    <template v-else>
      <wx-screen-head
        :back="props.base"
        :back-label="t('panel.channels')"
        :title="title"
        :subtitle="channel?.provider_label ?? context.provider.value?.label"
        :actions="actions"
      >
        <template v-if="channel && !channel.enabled" #title-after>
          <wx-badge dot>{{ t('panel.off') }}</wx-badge>
        </template>
      </wx-screen-head>

      <wx-alert
        v-if="environment !== 'production'"
        type="info"
        variant="soft"
        :description="t('panel.environment', { environment })"
      />
      <wx-alert
        v-if="meta?.mode === 'after_response'"
        type="warning"
        variant="soft"
        :description="t('panel.mode-after-response')"
      />
      <wx-alert
        v-if="channel && !channel.provider_declared"
        type="danger"
        :title="t('panel.provider-undeclared')"
        :description="t('panel.provider-undeclared-text', { provider: channel.provider })"
      />
      <wx-alert
        v-if="channel && !channel.event_declared"
        type="warning"
        :title="t('panel.event-undeclared')"
        :description="t('panel.event-undeclared-text', { event: channel.event ?? '' })"
      />
      <wx-alert
        v-if="channel && !channel.credentials_readable"
        type="danger"
        :description="t('panel.unreadable')"
      />
      <wx-alert
        v-if="channel?.copied_from"
        type="info"
        variant="soft"
        :description="t('panel.copied-from', { id: String(channel.copied_from) })"
      />
      <wx-alert v-for="warning in warnings" :key="warning" type="warning" :description="warning" />

      <div class="wx-notifications-channel__body">
        <wx-screen
          v-model="values"
          class="wx-notifications-channel__form"
          name="notifications.channel"
          :errors="errors"
          :patch="patch"
          :disabled="!canManage || saving"
        />

        <message-preview class="wx-notifications-channel__preview" />
      </div>

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button type="primary" :loading="saving" :disabled="!dirty && id !== null" @click="save">
          {{ t('panel.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-notifications-channel {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
  container-type: inline-size;
}

.wx-notifications-channel__ghost {
  max-width: 480px;
}

.wx-notifications-channel__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
}

.wx-notifications-channel__form,
.wx-notifications-channel__preview {
  min-width: 0;
}

/*
 * Beside the form once the pane has room for both, and staying in view while the template is
 * scrolled to: the preview is read while typing, not after.
 */
@container (min-width: 1040px) {
  .wx-notifications-channel__body {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(320px, 400px);
    align-items: start;
  }

  .wx-notifications-channel__preview {
    position: sticky;
    top: var(--wx-space-16);
  }
}
</style>
