<script setup lang="ts">
import { computed } from 'vue'
import {
  createModal,
  toast,
  WxAction,
  WxBadge,
  WxButton,
  WxIcon,
  WxInput,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { useRemoveConfirm, useTranslate } from '@webx-ui/module-admin'
import { useChannel } from './context'
import FindChatsDialog from './FindChatsDialog.vue'
import { useNotificationsMessages } from './i18n'
import { addresseesOf } from './variables'
import type { ChatRecipient, FoundChat } from './types'

/**
 * `wx-notifications-chats`: who a Telegram channel writes to (§6.1) — chat ids with a label each,
 * and the chat the event brings when it brings one (decision 12).
 *
 * The ids are hard to come by, so "Find chats" lists the ones the bot has seen: the saved token's,
 * or the one typed above and not saved yet.
 */
defineOptions({ name: 'WxNotificationsChatsField' })

const props = withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false })

const model = defineModel<ChatRecipient[] | null>({ default: () => [] })

useNotificationsMessages()

const t = useTranslate('webx-notifications')
const askRemove = useRemoveConfirm()
const channel = useChannel()
const find = createModal<
  FoundChat[],
  { channel?: number | null; token?: string; chosen?: string[] }
>(FindChatsDialog)

const list = computed<ChatRecipient[]>(() => (Array.isArray(model.value) ? model.value : []))

const addressees = computed(() => addresseesOf(channel.event.value, 'telegram'))

const unused = computed(() =>
  addressees.value.filter((variable) => !list.value.some((one) => one.variable === variable.name)),
)

function labelOf(name: string): string {
  return addressees.value.find((variable) => variable.name === name)?.label ?? `{{${name}}}`
}

function set(index: number, patch: Partial<ChatRecipient>): void {
  model.value = list.value.map((one, at) => (at === index ? { ...one, ...patch } : one))
}

function add(recipient: ChatRecipient): void {
  model.value = [...list.value, recipient]
}

function remove(index: number): void {
  model.value = list.value.filter((_, at) => at !== index)
}

const token = computed(() => {
  const typed = channel.values.value.token

  return typeof typed === 'string' ? typed : ''
})

/* Without a token there is nobody to ask: the saved channel has one, or the field above does. */
const canFind = computed(
  () =>
    token.value.trim() !== '' ||
    (channel.id.value !== null && channel.values.value.provider === 'telegram'),
)

async function search(): Promise<void> {
  if (!canFind.value) {
    toast.warning(t('panel.find-token'))

    return
  }

  const chosen = await find({
    channel: channel.id.value,
    token: token.value,
    chosen: list.value.map((one) => one.chat_id ?? '').filter(Boolean),
  })

  if (!chosen || chosen.length === 0) return

  model.value = [...list.value, ...chosen.map((chat) => ({ chat_id: chat.id, label: chat.title }))]
  toast.success(t('panel.find-added', { count: String(chosen.length) }))
}
</script>

<template>
  <div class="wx-notifications-recipients">
    <wx-text v-if="list.length === 0" size="sm" tone="placeholder">
      {{ t('panel.no-recipients') }}
    </wx-text>

    <div v-for="(recipient, index) in list" :key="index" class="wx-notifications-recipients__row">
      <template v-if="recipient.variable">
        <wx-badge type="primary" class="wx-notifications-recipients__addressee">
          {{ t('panel.addressee', { label: labelOf(recipient.variable) }) }}
        </wx-badge>
      </template>

      <template v-else>
        <wx-input
          class="wx-notifications-recipients__id"
          :model-value="recipient.chat_id ?? ''"
          :placeholder="t('panel.chat-id')"
          :aria-label="t('panel.chat-id')"
          :disabled="props.disabled"
          inputmode="text"
          @update:model-value="(value) => set(index, { chat_id: String(value ?? '') })"
        />
        <wx-input
          class="wx-notifications-recipients__label"
          :model-value="recipient.label ?? ''"
          :placeholder="t('panel.chat-label')"
          :aria-label="t('panel.chat-label')"
          :disabled="props.disabled"
          @update:model-value="(value) => set(index, { label: String(value ?? '') })"
        />
      </template>

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
      <wx-button variant="outline" size="sm" @click="add({ chat_id: '', label: '' })">
        <template #icon><wx-icon name="plus" /></template>
        {{ t('panel.add-chat') }}
      </wx-button>
      <wx-button variant="outline" size="sm" @click="search">
        <template #icon><wx-icon name="search" /></template>
        {{ t('panel.find-chats') }}
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

.wx-notifications-recipients__id {
  flex: 1 1 40%;
  min-width: 0;
}

.wx-notifications-recipients__label {
  flex: 1 1 60%;
  min-width: 0;
}

.wx-notifications-recipients__addressee {
  flex: 1 1 auto;
  min-width: 0;
}
</style>
