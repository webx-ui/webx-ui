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
import { confirm, createModal, toast, type TabItem, type TabValue } from '@webx-ui/core'
import AdminDialog from './AdminDialog.vue'
import AdminList from './AdminList.vue'
import CallList from './CallList.vue'
import { createAdminsApi } from './admins'
import { useAuthMessages } from './i18n'
import type { Admin } from './types'

/**
 * The administrators section: the list, the form over it, and the trail of what their agents
 * did.
 *
 * A dialog rather than a second route for the form. Editing somebody is half a dozen fields,
 * and a screen that takes over the page for that loses the list you were reading — which is
 * where you decide who to open next.
 *
 * The agent calls are a second view of the same section rather than a section of their own:
 * "who did what" is a question about these people, whether a hand or a program was at the
 * other end. Two routes rather than two panels of one, because they are two tables with
 * their own paging and filters, and a view that quietly resets both when you come back to it
 * is worse than a second address. The view is shown to whoever holds `admins.audit`, the
 * permission the sign-in trail is behind.
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
    current?: 'admins' | 'calls'
    avatarField?: Component
    resolveAvatar?: (key: string) => Promise<string | null>
  }>(),
  { base: '/admins', current: 'admins', avatarField: undefined, resolveAvatar: undefined },
)

const context = useAdmin()
const api = createAdminsApi(context)
const router = useRouter()
const route = useRoute()
useAuthMessages()

const t = useTranslate('webx-auth')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const list = useTemplateRef<{ reload: () => void }>('list')
const editing = ref<Admin | null>(null)

const edit = createModal<Admin, { admin: Admin | null; avatarField?: Component }>(AdminDialog)

const canManage = context.can('admins.manage')
const canAudit = context.can('admins.audit')

/** The section's name as the server translated it; the built-in English until it arrives. */
const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'admins')?.title ??
    t('admins.title'),
)

/* One view is no view: the strip is drawn only for somebody who can see the second. */
const views = computed<TabItem[] | undefined>(() =>
  canAudit
    ? [
        { value: 'admins', label: title.value },
        { value: 'calls', label: t('calls.title') },
      ]
    : undefined,
)

const where = computed<TabValue>({
  get: () => props.current,
  set: (next) => {
    const path = next === 'admins' ? props.base : `${props.base}/${String(next)}`

    if (path !== route.path) void router.push(path)
  },
})

/* Short enough for a phone, where the name and the button share one line. `Add` belongs to
   the people, not to the log. */
const actions = computed<ScreenAction[]>(() =>
  canManage && props.current === 'admins'
    ? [
        {
          key: 'new',
          label: t('admins.new-short'),
          icon: 'plus',
          primary: true,
          run: () => void open(null),
        },
      ]
    : [],
)

async function open(admin: Admin | null): Promise<void> {
  editing.value = admin

  const saved = await edit({ admin, avatarField: props.avatarField })

  if (saved) {
    list.value?.reload()
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
    <call-list v-if="current === 'calls'" />
    <admin-list
      v-else
      ref="list"
      :removable="canManage"
      :resolve-avatar="props.resolveAvatar"
      @open="open"
      @remove="remove"
    />
  </wx-list-screen>
</template>
