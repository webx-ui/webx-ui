<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  useModal,
  WxAlert,
  WxBadge,
  WxButton,
  WxCheckbox,
  WxDialog,
  WxEmpty,
  WxSkeleton,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createNotificationsApi } from './api'
import { useNotificationsMessages } from './i18n'
import type { FoundChat } from './types'

/**
 * "Find chats" (§6.3): the chats the bot has seen in the last day, to tick and add. Asked of the
 * saved token, or of one typed into the form and not saved yet — that one goes into this request
 * and nowhere else. Nothing is sent and nothing is written: the bot's updates are read once,
 * without being acknowledged, so a bot that is read elsewhere still gets them.
 */
defineOptions({ name: 'WxNotificationsFindChats' })

const props = withDefaults(
  defineProps<{
    channel?: number | null
    token?: string
    /** Chats already on the list: shown ticked and not added twice. */
    chosen?: string[]
  }>(),
  { channel: null, token: '', chosen: () => [] },
)

const { open, resolve, dismiss } = useModal<FoundChat[]>()

const api = createNotificationsApi(useAdmin())
useNotificationsMessages()

const t = useTranslate('webx-notifications')
const message = useErrorText()

const found = ref<FoundChat[]>([])
const hint = ref<string | null>(null)
const failure = ref<string | null>(null)
const loading = ref(true)
const ticked = ref(new Set<string>())

async function search(): Promise<void> {
  loading.value = true
  failure.value = null

  try {
    const answer = await api.findRecipients({
      provider: 'telegram',
      channel: props.channel,
      token: props.token.trim() === '' ? undefined : props.token.trim(),
    })

    found.value = answer.recipients
    hint.value = answer.hint
  } catch (error) {
    failure.value = message(error)
  } finally {
    loading.value = false
  }
}

onMounted(() => void search())

const fresh = computed(() => found.value.filter((chat) => !props.chosen.includes(chat.id)))

function toggle(id: string, on: boolean): void {
  const next = new Set(ticked.value)

  if (on) next.add(id)
  else next.delete(id)

  ticked.value = next
}

function add(): void {
  resolve(fresh.value.filter((chat) => ticked.value.has(chat.id)))
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.find-title')" :width="480">
    <wx-skeleton v-if="loading" :rows="3" />

    <wx-alert v-else-if="failure" type="danger" variant="soft" :description="failure" />

    <wx-empty
      v-else-if="found.length === 0"
      :title="t('panel.find-title')"
      :description="hint ?? t('panel.find-help')"
    />

    <ul v-else class="wx-notifications-found">
      <li v-for="chat in found" :key="chat.id" class="wx-notifications-found__row">
        <wx-checkbox
          :model-value="props.chosen.includes(chat.id) || ticked.has(chat.id)"
          :disabled="props.chosen.includes(chat.id)"
          @update:model-value="(on: boolean) => toggle(chat.id, on)"
        >
          <span class="wx-notifications-found__text">
            <wx-text truncate>{{ chat.title }}</wx-text>
            <wx-text size="sm" tone="muted" mono truncate>
              {{ [chat.id, chat.username].filter(Boolean).join(' · ') }}
            </wx-text>
          </span>
        </wx-checkbox>
        <wx-badge>{{ t(`panel.chat-${chat.type}`) }}</wx-badge>
      </li>
    </ul>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" :disabled="loading" @click="search">
          {{ t('panel.find-chats') }}
        </wx-button>
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button type="primary" :disabled="ticked.size === 0" @click="add">
          {{ t('panel.find-add') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-notifications-found {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-notifications-found__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-notifications-found__text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
</style>
