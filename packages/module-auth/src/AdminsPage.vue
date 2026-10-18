<script setup lang="ts">
import { computed, ref, useTemplateRef, type Component } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxListScreen,
  type ScreenAction,
} from '@webx-ui/module-admin'
import { confirm, createModal, toast, WxResult, type TabItem, type TabValue } from '@webx-ui/core'
import AdminDialog from './AdminDialog.vue'
import AdminList from './AdminList.vue'
import CallList from './CallList.vue'
import ConnectionList from './ConnectionList.vue'
import KeyDialog from './KeyDialog.vue'
import KeyList from './KeyList.vue'
import RoleDialog from './RoleDialog.vue'
import RoleList from './RoleList.vue'
import { createAdminsApi } from './admins'
import { libraryAvatarField, libraryAvatarResolver } from './avatars'
import type { AdminsView } from './module'
import { useAuthMessages } from './i18n'
import type { Admin, McpKey, Role } from './types'

/**
 * The administrators section: the list, the form over it, the trail of what their agents did,
 * and the agents themselves.
 *
 * A dialog rather than a second route for the form. Editing somebody is half a dozen fields,
 * and a screen that takes over the page for that loses the list you were reading — which is
 * where you decide who to open next.
 *
 * The calls, the connections and the keys are further views of the same section rather than sections of
 * their own: "who did what" is a question about these people, whether a hand or a program was
 * at the other end. A route each rather than panels of one, because each has its own paging
 * and filters, and a view that quietly resets both when you come back to it is worse than a
 * second address. The calls are behind `admins.audit`, the permission the sign-in trail is
 * behind; everybody's connections are behind `admins.manage`, the one that is about other
 * people's accounts. Keys are behind it too: a key speaks for the administrator it names.
 *
 * The roles are a view of their own too (WEBX_UI_PERMISSIONS.md §11): read by whoever deals with
 * people or roles at all, edited with `roles.manage`. Each view is shown only to whoever may
 * open it; the section itself is in the menu for anybody holding one of its permissions, so an
 * address of a view they may not open lands on the first one they may.
 *
 * The heading and `Add` used to live inside the card, which made this the one section of the
 * panel with no line of its own at the top. They stand outside it now, where every other list
 * keeps them (§19).
 */
const props = withDefaults(
  defineProps<{
    /** The section's own path, for the address of the second view. */
    base?: string
    /** Which view is open. */
    current?: AdminsView
    avatarField?: Component
    resolveAvatar?: (key: string) => Promise<string | null>
  }>(),
  { base: '/admins', current: 'admins', avatarField: undefined, resolveAvatar: undefined },
)

const context = useAdmin()
const api = createAdminsApi(context)
// Handed in, they win; otherwise the panel's library, when it has one.
const avatarField = props.avatarField ?? libraryAvatarField(context) ?? undefined
const resolveAvatar = props.resolveAvatar ?? libraryAvatarResolver(context) ?? undefined
const router = useRouter()
const route = useRoute()
useAuthMessages()

const t = useTranslate('webx-auth')
const panel = useTranslate('webx-admin')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const list = useTemplateRef<{ reload: () => void }>('list')
const keysView = useTemplateRef<{ reload: () => void }>('keys')
const rolesView = useTemplateRef<{ reload: () => void }>('roles')
const editing = ref<Admin | null>(null)

const edit = createModal<Admin, { admin: Admin | null; avatarField?: Component }>(AdminDialog)
const issueKey = createModal<McpKey>(KeyDialog)
const editRole = createModal<Role, { role: Role | null; readOnly: boolean }>(RoleDialog)

const canManage = context.can('admins.manage')
const canAudit = context.can('admins.audit')
const canSeePeople = canManage || context.can('admins.view')
const canEditRoles = context.can('roles.manage')
const canSeeRoles = canSeePeople || canEditRoles || context.can('roles.assign')

/* Which views this administrator may open, in the order of the strip. */
const allowed = computed<AdminsView[]>(() => {
  const list: AdminsView[] = []

  if (canSeePeople) list.push('admins')
  if (canSeeRoles) list.push('roles')
  if (canAudit) list.push('calls')
  if (canManage) list.push('connections', 'keys')

  return list
})

/* A view they may not open: the first one they may, or the panel's «no access» if none. */
const shown = computed<AdminsView | null>(() =>
  allowed.value.includes(props.current) ? props.current : (allowed.value[0] ?? null),
)

/** The section's name as the server translated it; the built-in English until it arrives. */
const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'admins')?.title ??
    t('admins.title'),
)

/*
 * One view is no view: the strip is drawn only for somebody who can see a second one.
 *
 * The connections here are everybody's, which is `admins.manage` — the permission that is
 * already about other people's accounts. Your own live on the connect page, where anybody
 * signed in reaches them without a permission at all.
 */
const views = computed<TabItem[] | undefined>(() => {
  const labels: Record<AdminsView, string> = {
    admins: title.value,
    roles: t('roles.title'),
    calls: t('calls.title'),
    connections: t('connections.title'),
    keys: t('keys.title'),
  }
  const items: TabItem[] = allowed.value.map((view) => ({ value: view, label: labels[view] }))

  return items.length > 1 ? items : undefined
})

const where = computed<TabValue>({
  get: () => shown.value ?? props.current,
  set: (next) => {
    const path = next === 'admins' ? props.base : `${props.base}/${String(next)}`

    if (path !== route.path) void router.push(path)
  },
})

/* Short enough for a phone, where the name and the button share one line. `Add` belongs to
   the people, not to the log. */
const actions = computed<ScreenAction[]>(() => {
  if (shown.value === 'roles') {
    return canEditRoles
      ? [
          {
            key: 'new-role',
            label: t('roles.new-short'),
            icon: 'plus',
            primary: true,
            run: () => void openRole(null),
          },
        ]
      : []
  }

  if (!canManage) return []

  if (shown.value === 'admins') {
    return [
      {
        key: 'new',
        label: t('admins.new-short'),
        icon: 'plus',
        primary: true,
        run: () => void open(null),
      },
    ]
  }

  if (shown.value === 'keys') {
    return [
      {
        key: 'issue',
        label: t('keys.issue-short'),
        icon: 'plus',
        primary: true,
        run: () => void issue(),
      },
    ]
  }

  return []
})

async function issue(): Promise<void> {
  if (await issueKey()) keysView.value?.reload()
}

async function open(admin: Admin | null): Promise<void> {
  editing.value = admin

  const saved = await edit({ admin, avatarField })

  if (saved) {
    list.value?.reload()
  }
}

async function openRole(role: Role | null): Promise<void> {
  if (await editRole({ role, readOnly: !canEditRoles })) rolesView.value?.reload()
}

/* Who loses it is asked first: the confirmation names them, so nobody deletes a role blind. */
async function removeRole(role: Role): Promise<void> {
  let holders: string[] = []

  try {
    holders = (await api.role(role.id)).holders.map((one) => one.name)
  } catch (error) {
    toast.danger(message(error))

    return
  }

  const agreed = await confirm({
    title: t('roles.delete-title', { name: role.name }),
    message:
      holders.length > 0
        ? t('roles.delete-holders', { names: holders.join(', ') })
        : t('roles.delete-text'),
    confirmText: t('roles.delete'),
    cancelText: t('roles.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeRole(role.id)
    toast.success(t('roles.deleted'))
    rolesView.value?.reload()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(admin: Admin): Promise<void> {
  const agreed = await confirm({
    title: t('admins.delete-title', { name: admin.name }),
    message: t('admins.delete-text'),
    confirmText: t('admins.delete'),
    cancelText: t('admins.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(admin.id)
    toast.success(t('admins.deleted'))
    list.value?.reload()
  } catch (error) {
    toast.danger(message(error))
  }
}
</script>

<template>
  <wx-list-screen v-model:view="where" :title="title" :views="views" :actions="actions">
    <wx-result
      v-if="shown === null"
      status="403"
      :title="panel('shell.forbidden-title')"
      :subtitle="panel('shell.forbidden-description')"
    />
    <role-list
      v-else-if="shown === 'roles'"
      ref="roles"
      :removable="canEditRoles"
      @open="openRole"
      @remove="removeRole"
    />
    <call-list v-else-if="shown === 'calls'" />
    <connection-list v-else-if="shown === 'connections'" scope="all" />
    <key-list v-else-if="shown === 'keys'" ref="keys" />
    <admin-list
      v-else
      ref="list"
      :removable="canManage"
      :resolve-avatar="resolveAvatar"
      @open="open"
      @remove="remove"
    />
  </wx-list-screen>
</template>
