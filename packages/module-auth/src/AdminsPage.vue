<script setup lang="ts">
import { ref, useTemplateRef, type Component } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/admin'
import { confirm, createModal, toast, WxButton, WxCard } from '@webx-ui/core'
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

const list = useTemplateRef<{ reload: () => void }>('list')
const editing = ref<Admin | null>(null)

const edit = createModal<Admin, { admin: Admin | null; avatarField?: Component }>(AdminDialog)

const canManage = context.can('users.manage')

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
    const body = (error as { body?: { message?: string } }).body

    toast.danger(body?.message ?? t('errors.forbidden'))
  }
}
</script>

<template>
  <wx-card>
    <template #header>{{ t('admins.title') }}</template>

    <template v-if="canManage" #extra>
      <wx-button type="primary" icon="add" @click="open(null)">{{ t('admins.new') }}</wx-button>
    </template>

    <admin-list
      ref="list"
      :removable="canManage"
      :resolve-avatar="props.resolveAvatar"
      @open="open"
      @remove="remove"
    />
  </wx-card>
</template>
