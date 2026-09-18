<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import {
  confirm,
  WxAction,
  WxAlert,
  WxButton,
  WxCard,
  WxFormItem,
  WxInput,
  WxSelect,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { createInboxApi } from './api'
import { option } from './options'
import type { FormOptions, InboxField, InboxRecipient, Recipient } from './types'

/**
 * Who hears that something arrived (§9).
 *
 * Two kinds of recipient and they are not interchangeable. An administrator is written to at
 * the address on their account and in the language they keep the panel in, so changing either
 * changes it everywhere; a typed address is written to in the language the submission came in.
 * The list offered is not every administrator but the ones who may open a submission — a
 * notification is a link, and sending one to somebody who gets a 403 looks like the panel is
 * broken rather than like the form is.
 */
const props = defineProps<{ fields: InboxField[]; errors: Record<string, string[]> }>()

const options = defineModel<FormOptions>({ required: true })

const context = useAdmin()
const api = createInboxApi(context)
const t = useTranslate('webx-inbox')

const admins = ref<InboxRecipient[]>([])

const recipients = option<Recipient[]>(options, 'recipients', [])
const emailField = option<string>(options, 'email_field', '')

onMounted(async () => {
  try {
    admins.value = await api.recipients()
  } catch {
    // Only the shortcut is gone: an address can still be typed, and that is the half of this
    // that has to work.
  }
})

const adminOptions = computed(() =>
  admins.value.map((admin) => ({ value: admin.id, label: `${admin.name} · ${admin.email}` })),
)

/** The fields that could hold the sender's address, plus the option of naming none. */
const emailOptions = computed(() => [
  { value: '', label: t('panel.no-email-field') },
  ...props.fields
    .filter((field) => field.type === 'email')
    .map((field) => ({ value: field.key, label: field.key })),
])

function isAdmin(recipient: Recipient): recipient is { admin_id: number } {
  return 'admin_id' in recipient
}

function add(recipient: Recipient): void {
  recipients.value = [...recipients.value, recipient]
}

/**
 * A recipient goes behind a question, unless there is nothing there to lose.
 *
 * The row that was just added and never filled in is a blank line, and asking about a blank
 * line teaches the reader to click through the question without reading it — which is the
 * one thing a confirmation must not do.
 */
async function remove(index: number): Promise<void> {
  const recipient = recipients.value[index]
  const written =
    recipient !== undefined && (isAdmin(recipient) || (recipient.email ?? '').trim() !== '')

  if (written) {
    const agreed = await confirm({
      title: t('panel.remove-recipient-title'),
      message: t('panel.remove-recipient-text'),
      confirmText: t('panel.remove'),
      cancelText: t('panel.cancel'),
      tone: 'danger',
    })

    if (!agreed) return
  }

  recipients.value = recipients.value.filter((_, at) => at !== index)
}

function setAdmin(index: number, id: number): void {
  recipients.value = recipients.value.map((one, at) => (at === index ? { admin_id: id } : one))
}

function setEmail(index: number, email: string): void {
  recipients.value = recipients.value.map((one, at) => (at === index ? { email } : one))
}
</script>

<template>
  <wx-card :title="t('panel.recipients')">
    <wx-text size="sm" tone="muted">{{ t('panel.recipients-help') }}</wx-text>

    <wx-alert
      v-if="errors['options.recipients']"
      type="danger"
      variant="soft"
      :description="errors['options.recipients']?.[0]"
    />

    <div class="wx-inbox-recipients">
      <wx-text v-if="recipients.length === 0" size="sm" tone="placeholder">
        {{ t('panel.no-recipients') }}
      </wx-text>

      <div v-for="(recipient, index) in recipients" :key="index" class="wx-inbox-recipients__row">
        <wx-select
          v-if="isAdmin(recipient)"
          :model-value="recipient.admin_id"
          :options="adminOptions"
          @update:model-value="(id) => setAdmin(index, Number(id))"
        />
        <wx-input
          v-else
          :model-value="recipient.email"
          type="email"
          placeholder="sales@example.com"
          @update:model-value="(email) => setEmail(index, String(email))"
        />

        <!-- Red, like every other way of taking something away in the panel: the colour is
             what tells the two buttons of a row apart before either is read. -->
        <wx-action icon="trash" tone="danger" :title="t('panel.remove')" @click="remove(index)" />
      </div>
    </div>

    <wx-space size="sm">
      <wx-button
        variant="outline"
        icon="user"
        :disabled="adminOptions.length === 0"
        @click="add({ admin_id: Number(adminOptions[0]?.value) })"
      >
        {{ t('panel.add-admin') }}
      </wx-button>
      <wx-button variant="outline" icon="mail" @click="add({ email: '' })">
        {{ t('panel.add-email') }}
      </wx-button>
    </wx-space>

    <wx-form-item :label="t('panel.email-field')" :help="t('panel.email-field-help')">
      <wx-select v-model="emailField" :options="emailOptions" />
    </wx-form-item>
  </wx-card>
</template>

<style scoped>
/*
 * As wide as a field, because that is what each row is: an address is typed into it and read
 * back off it. The rows are not form items — one of them is a select, the next an input, and
 * both carry a bin — so the cap that a form item applies has to be said here.
 */
.wx-inbox-recipients {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  margin-block: var(--wx-space-12);
  max-width: var(--wx-field-max-width, 640px);
}

.wx-inbox-recipients__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-inbox-recipients__row > :first-child {
  flex: 1 1 auto;
  min-width: 0;
}
</style>
