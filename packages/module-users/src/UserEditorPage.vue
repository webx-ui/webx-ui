<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  confirm,
  toast,
  WxActionBar,
  WxAlert,
  WxBadge,
  WxBreadcrumb,
  WxBreadcrumbItem,
  WxButton,
  WxCard,
  WxCheckbox,
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
import type { ScreenModel } from '@webx-ui/schema'
import { createUsersApi } from './api'
import { provideUserEditor } from './editor'
import { NAMESPACE, useUsersMessages } from './i18n'
import type { UserDetail, UserSummary } from './types'

/**
 * One customer (spec §16.2): a head with the actions — the letters, a confirmation by hand, the
 * block, the deletion — and `users.form` under it: the profile, the site's fields, the groups, the
 * account with the ways to sign in, the history. A new customer is the same page at `…/users/new`:
 * an address and a profile, no password, and the invitation ticked.
 */
defineOptions({ name: 'WxUserEditor' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/users' })

const admin = useAdmin()
const api = createUsersApi(admin)
const route = useRoute()
const router = useRouter()
useUsersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const creating = computed(() => route.params.id === undefined || route.params.id === 'new')
const id = computed(() => (creating.value ? null : Number(route.params.id)))

const user = ref<UserSummary | null>(null)
const values = ref<ScreenModel>({})
const snapshot = ref('')
const invite = ref(true)

const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => admin.can('users.manage'))
const locked = computed(() => !canManage.value || saving.value)

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value || creating.value ? 'unsaved' : 'saved'
})

const section = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'users')?.title ??
    t('module.customers'),
)

/** The name as it is typed, the address for somebody without one. */
const title = computed(() => {
  const first = String(values.value.first_name ?? '').trim()
  const last = String(values.value.last_name ?? '').trim()
  const name = [first, last].filter(Boolean).join(' ')

  return name || String(values.value.email ?? '').trim() || t('panel.new')
})

const subtitle = computed(() =>
  user.value && title.value !== user.value.email ? user.value.email : undefined,
)

provideUserEditor({ user, locked, unlink })
provideHistorySubject({ id: computed(() => user.value?.id ?? null) })

function take(detail: UserDetail): void {
  user.value = detail.user
  values.value = { ...detail.values }
  snapshot.value = JSON.stringify(values.value)
}

/** Only the state: what is being typed in the form stays. */
function takeState(detail: UserDetail): void {
  user.value = detail.user
}

function blank(): void {
  user.value = null
  values.value = { email: '', first_name: '', last_name: '', phone: '', groups: [] }
  snapshot.value = JSON.stringify(values.value)
}

async function load(): Promise<void> {
  errors.value = {}

  if (id.value === null) {
    blank()
    loading.value = false

    return
  }

  loading.value = true

  try {
    take(await api.get(id.value))
  } catch (error) {
    toast.danger(message(error))
    void router.push(props.base)
  } finally {
    loading.value = false
  }
}

function failed(error: unknown): void {
  const body = (error as { body?: { errors?: Record<string, string[]> } }).body

  if (body?.errors && Object.keys(body.errors).length > 0) {
    errors.value = body.errors
    toast.danger(Object.values(body.errors)[0]?.[0] ?? t('panel.save-failed'))
  } else {
    toast.danger(message(error))
  }
}

async function save(): Promise<void> {
  if (saving.value || !canManage.value) return

  saving.value = true
  errors.value = {}

  try {
    if (id.value === null) {
      const created = await api.create(values.value, invite.value)

      take(created)
      toast.success(t('panel.created'))
      await router.replace(`${props.base}/${created.user.id}`)
    } else {
      take(await api.save(id.value, values.value))
      toast.success(t('panel.saved'))
    }
  } catch (error) {
    failed(error)
  } finally {
    saving.value = false
  }
}

async function act(work: () => Promise<UserDetail | void>, done: string): Promise<void> {
  try {
    const answer = await work()

    if (answer) takeState(answer)
    toast.success(done)
  } catch (error) {
    failed(error)
  }
}

async function block(on: boolean): Promise<void> {
  const row = user.value

  if (!row) return

  if (on) {
    const agreed = await confirm({
      title: t('panel.block-title', { name: title.value }),
      message: t('panel.block-text'),
      confirmText: t('panel.block'),
      cancelText: t('panel.cancel'),
      tone: 'danger',
    })

    if (!agreed) return
  }

  await act(() => api.block(row.id, on), t(on ? 'panel.blocked-toast' : 'panel.unblocked-toast'))
}

async function remove(): Promise<void> {
  const row = user.value

  if (!row) return

  let warnings: string[] = []

  try {
    warnings = await api.warnings(row.id)
  } catch (error) {
    toast.danger(message(error))

    return
  }

  const agreed = await confirm({
    title: t('panel.delete-title', { name: title.value }),
    message: [...warnings, t('panel.delete-text')].join('\n\n'),
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

async function unlink(provider: string): Promise<void> {
  const row = user.value

  if (!row) return

  const way = row.ways.find((one) => one.key === provider)
  const agreed = await confirm({
    title: t('panel.unlink-title', { provider: way?.label ?? provider }),
    message: t('panel.unlink-text', { provider: way?.label ?? provider }),
    confirmText: t('panel.unlink'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  await act(() => api.unlink(row.id, provider), t('panel.unlinked'))
}

function leaveGuard(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

function onKeydown(event: KeyboardEvent): void {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
    event.preventDefault()
    if (dirty.value || creating.value) void save()
  }
}

useBodyKeys(onKeydown)

watch(id, (next, previous) => {
  // The first save of a new customer moves the address to their id — nothing to read again.
  if (previous === null && next !== null && user.value?.id === next) return

  void load()
})

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
  const row = user.value

  if (!row || !canManage.value) return []

  const leads: ScreenAction[] = []

  if (!row.blocked) {
    leads.push({
      key: 'reset',
      label: t(row.has_password ? 'panel.send-reset' : 'panel.send-invite'),
      icon: 'mail',
      run: () => void act(() => api.sendReset(row.id), t('panel.reset-sent', { email: row.email })),
    })
  }

  if (!row.verified) {
    leads.push(
      {
        key: 'verification',
        label: t('panel.send-verification'),
        icon: 'send',
        menu: true,
        run: () =>
          void act(
            () => api.sendVerification(row.id),
            t('panel.verification-sent', { email: row.email }),
          ),
      },
      {
        key: 'verify',
        label: t('panel.verify'),
        icon: 'check',
        menu: true,
        run: () => void act(() => api.verify(row.id), t('panel.verified-toast')),
      },
    )
  }

  leads.push(
    row.blocked
      ? { key: 'unblock', label: t('panel.unblock'), icon: 'unlock', run: () => void block(false) }
      : {
          key: 'block',
          label: t('panel.block'),
          icon: 'lock',
          menu: true,
          run: () => void block(true),
        },
    {
      key: 'delete',
      label: t('panel.delete'),
      icon: 'trash',
      danger: true,
      menu: true,
      run: () => void remove(),
    },
  )

  return leads
})
</script>

<template>
  <div class="wx-user-editor" @keydown="onKeydown">
    <template v-if="loading">
      <wx-skeleton class="wx-user-editor__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="6" /></wx-card>
    </template>

    <template v-else>
      <wx-screen-head
        divider
        :back="props.base"
        :back-label="section"
        :title="title"
        :subtitle="subtitle"
        :actions="actions"
      >
        <template #trail>
          <wx-breadcrumb size="sm" :label="t('panel.trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="props.base">{{
              section
            }}</wx-breadcrumb-item>
            <wx-breadcrumb-item current>{{ title }}</wx-breadcrumb-item>
          </wx-breadcrumb>
        </template>

        <template v-if="user" #title-after>
          <wx-badge v-if="user.blocked" type="danger" size="sm">{{ t('panel.blocked') }}</wx-badge>
          <wx-badge v-else :type="user.verified ? 'success' : 'warning'" dot size="sm">
            {{ t(user.verified ? 'panel.verified' : 'panel.unverified') }}
          </wx-badge>
        </template>
      </wx-screen-head>

      <wx-alert v-if="creating" type="info" :description="t('panel.new-help')" />

      <wx-screen v-model="values" name="users.form" :errors="errors" :disabled="locked" />

      <wx-card v-if="creating && canManage">
        <wx-checkbox v-model="invite" :label="t('panel.invite')" />
        <p class="wx-user-editor__help">{{ t('panel.invite-help') }}</p>
      </wx-card>

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button type="primary" :loading="saving" :disabled="!dirty && !creating" @click="save">
          {{ t(creating ? 'panel.create' : 'panel.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-user-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-user-editor__ghost {
  max-width: 420px;
}

.wx-user-editor__help {
  margin: var(--wx-space-6) 0 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}
</style>
