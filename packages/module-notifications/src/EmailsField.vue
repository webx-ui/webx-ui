<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  WxAction,
  WxBadge,
  WxButton,
  WxIcon,
  WxInput,
  WxSelect,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { useAdmin, useRemoveConfirm, useTranslate } from '@webx-ui/module-admin'
import { createNotificationsApi } from './api'
import { useChannel } from './context'
import { useNotificationsMessages } from './i18n'
import { addresseesOf } from './variables'
import type { Administrator, EmailRecipient } from './types'

/**
 * `wx-notifications-emails`: who an e-mail channel writes to (§6.2) — administrators of the panel,
 * whose address follows their account, typed addresses, and the address the event brings: the
 * visitor who filled in a form, for a reply of the site (decision 12). One letter each: nobody
 * sees who else got it.
 */
defineOptions({ name: 'WxNotificationsEmailsField' })

const props = withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false })

const model = defineModel<EmailRecipient[] | null>({ default: () => [] })

useNotificationsMessages()

const t = useTranslate('webx-notifications')
const askRemove = useRemoveConfirm()
const channel = useChannel()
const admin = useAdmin()

const admins = ref<Administrator[]>([])

onMounted(async () => {
  try {
    admins.value = await createNotificationsApi(admin).administrators()
  } catch {
    // Only the shortcut is gone: an address can still be typed.
  }
})

const list = computed<EmailRecipient[]>(() => (Array.isArray(model.value) ? model.value : []))

const adminOptions = computed(() =>
  admins.value.map((one) => ({ value: one.id, label: `${one.name} · ${one.email}` })),
)

const addressees = computed(() => addresseesOf(channel.event.value, 'email'))

const unused = computed(() =>
  addressees.value.filter((variable) => !list.value.some((one) => one.variable === variable.name)),
)

function labelOf(name: string): string {
  return addressees.value.find((variable) => variable.name === name)?.label ?? `{{${name}}}`
}

function set(index: number, recipient: EmailRecipient): void {
  model.value = list.value.map((one, at) => (at === index ? recipient : one))
}

function add(recipient: EmailRecipient): void {
  model.value = [...list.value, recipient]
}

function remove(index: number): void {
  model.value = list.value.filter((_, at) => at !== index)
}

/* An administrator the list no longer has — deleted or switched off — is still drawn by number. */
function adminOptionsFor(id: number) {
  return adminOptions.value.some((one) => one.value === id)
    ? adminOptions.value
    : [...adminOptions.value, { value: id, label: t('panel.admin-unknown', { id: String(id) }) }]
}
</script>

<template>
  <div class="wx-notifications-recipients">
    <wx-text v-if="list.length === 0" size="sm" tone="placeholder">
      {{ t('panel.no-recipients') }}
    </wx-text>

    <div v-for="(recipient, index) in list" :key="index" class="wx-notifications-recipients__row">
      <wx-badge v-if="recipient.variable" type="primary" class="wx-notifications-recipients__wide">
        {{ t('panel.addressee', { label: labelOf(recipient.variable) }) }}
      </wx-badge>

      <wx-select
        v-else-if="recipient.admin_id !== undefined"
        class="wx-notifications-recipients__wide"
        :model-value="recipient.admin_id"
        :options="adminOptionsFor(recipient.admin_id)"
        :disabled="props.disabled"
        @update:model-value="(id) => set(index, { admin_id: Number(id) })"
      />

      <wx-input
        v-else
        class="wx-notifications-recipients__wide"
        type="email"
        :model-value="recipient.email ?? ''"
        placeholder="manager@site.test"
        :disabled="props.disabled"
        @update:model-value="(email) => set(index, { email: String(email ?? '') })"
      />

      <wx-action
        v-if="!props.disabled"
        icon="trash"
        tone="danger"
        :title="t('panel.remove')"
        v-bind="askRemove"
        @click="remove(index)"
      />
    </div>

    <wx-space v-if="!props.disabled" size="sm" wrap>
      <wx-button
        variant="outline"
        size="sm"
        :disabled="adminOptions.length === 0"
        @click="add({ admin_id: Number(adminOptions[0]?.value) })"
      >
        <template #icon><wx-icon name="user" /></template>
        {{ t('panel.add-admin') }}
      </wx-button>
      <wx-button variant="outline" size="sm" @click="add({ email: '' })">
        <template #icon><wx-icon name="mail" /></template>
        {{ t('panel.add-email') }}
      </wx-button>
      <wx-button
        v-for="variable in unused"
        :key="variable.name"
        variant="outline"
        size="sm"
        @click="add({ variable: variable.name })"
      >
        {{ t('panel.addressee', { label: variable.label }) }}
      </wx-button>
    </wx-space>
  </div>
</template>

<style scoped>
.wx-notifications-recipients {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
  max-width: var(--wx-field-max-width, 640px);
}

.wx-notifications-recipients__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

/* The control of a row takes what the bin leaves: a select draws only as wide as its words. */
.wx-notifications-recipients__row > :not(:last-child) {
  flex: 1 1 auto;
  min-width: 0;
}

.wx-notifications-recipients__wide {
  flex: 1 1 auto;
  min-width: 0;
}
</style>
