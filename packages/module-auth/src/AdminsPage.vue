<script setup lang="ts">
import { computed, ref, useTemplateRef, type Component } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxListScreen } from '@webx-ui/module-admin'
import { confirm, createModal, toast, WxButton } from '@webx-ui/core'
import AdminDialog from './AdminDialog.vue'
import AdminList from './AdminList.vue'
import { createAdminsApi } from './admins'
import { useAuthMessages } from './i18n'
import type { Admin } from './types'

/**
 * The administrators section: the list, and the form over it.
 *
 * A dialog rather than a second route. Editing somebody is half a dozen fields, and a screen
 * that takes over the page for that loses the list you were reading — which is where you
 * decide who to open next.
 *
 * The heading and `Add` used to live inside the card, which made this the one section of the
 * panel with no line of its own at the top. They stand outside it now, where every other list
 * keeps them (§19).
 */
const props = withDefaults(
  defineProps<{
    avatarField?: Component
    resolveAvatar?: (key: string) => Promise<string | null>
  }>(),
  { avatarField: undefined, resolveAvatar: undefined },
)

const context = useAdmin()
const api = createAdminsApi(context)
useAuthMessages()

const t = useTranslate('webx-auth')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const list = useTemplateRef<{ reload: () => void }>('list')
const editing = ref<Admin | null>(null)

const edit = createModal<Admin, { admin: Admin | null; avatarField?: Component }>(AdminDialog)

const canManage = context.can('admins.manage')

/** The section's name as the server translated it; the built-in English until it arrives. */
const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'admins')?.title ??
    t('admins.title'),
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
  <wx-list-screen :title="title">
    <template v-if="canManage" #actions>
      <!-- Short enough for a phone, where the heading and the button share one line. -->
      <wx-button type="primary" icon="plus" @click="open(null)">
        {{ t('admins.new-short') }}
      </wx-button>
    </template>

    <admin-list
      ref="list"
      :removable="canManage"
      :resolve-avatar="props.resolveAvatar"
      @open="open"
      @remove="remove"
    />
  </wx-list-screen>
</template>
