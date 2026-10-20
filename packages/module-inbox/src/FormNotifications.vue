<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAdmin, useTranslate, WxRelationsField } from '@webx-ui/module-admin'
import {
  confirm,
  WxAction,
  WxAlert,
  WxButton,
  WxIcon,
  WxCard,
  WxFormItem,
  WxInput,
  WxLink,
  WxRadioGroup,
  WxSelect,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { createInboxApi } from './api'
import { option } from './options'
import { isAdmin, problemOf } from './recipients'
import type {
  FormOptions,
  InboxField,
  InboxRecipient,
  NotifyVia,
  Recipient,
  RecipientState,
} from './types'

/**
 * Who hears that something arrived (§9) — one card, and one way of hearing it.
 *
 * On top, the field whose answer is the visitor's address: both ways use it — the letter as its
 * Reply-To, a channel as `{{reply_to}}`. Below it the choice of how, when the notifications
 * module is installed, and only the half that was chosen. A submission goes one way: a letter to
 * the addresses and a message in a chat about the same thing was the same news twice, and a form
 * set up for one way kept sending the other because nobody had emptied it. The half not chosen is
 * kept as it is on the form, so switching back finds it where it was.
 *
 * Two kinds of recipient of a letter, and they are not interchangeable. An administrator is
 * written to at the address on their account and in the language they keep the panel in; a typed
 * address is written to in the language the submission came in. The administrators offered are
 * the ones who may open a submission — a notification is a link, and sending one to somebody who
 * gets a 403 looks like the panel is broken rather than like the form is.
 *
 * The channels are asked of the event's target on the server (NOTIFICATIONS §15.2), so this
 * package needs nothing of that module's code; a site without it sees the letter and nothing else.
 */
const props = defineProps<{
  fields: InboxField[]
  errors: Record<string, string[]>
  /** What the server said about the saved list: who is deleted or switched off. */
  reported?: RecipientState[]
  /** Whether the notifications module is installed — the server says so on the form. */
  notifications?: boolean
  /** How the form tells, as the server worked it out for a form saved before the choice. */
  via?: NotifyVia
}>()

const options = defineModel<FormOptions>({ required: true })

const context = useAdmin()
const api = createInboxApi(context)
const t = useTranslate('webx-inbox')

const admins = ref<InboxRecipient[]>([])

const recipients = option<Recipient[]>(options, 'recipients', [])
const emailField = option<string>(options, 'email_field', '')
const channels = option<number[]>(options, 'notify_channels', [])

/*
 * The form's own choice, or what the server worked out for one saved before there was a choice —
 * written into the options the moment somebody picks, so a form that loses its channels later
 * does not quietly change its way with them.
 */
const via = computed<NotifyVia>({
  get: () => options.value.notify_via ?? props.via ?? 'email',
  set: (value) => {
    options.value = { ...options.value, notify_via: value }
  },
})

const viaOptions = computed(() => [
  { value: 'email', label: t('panel.via-email') },
  { value: 'channels', label: t('panel.via-channels') },
])

/* Without the module there is no choice to make, and the letter is what goes. */
const byLetter = computed(() => !props.notifications || via.value === 'email')

const router = useRouter()

/* The channels are set up in their own section; only somebody who may do it gets the way there. */
const canSetUp = computed(() => context.can('notifications.manage'))
const channelsLink = computed(() => `${context.basePath}/notifications`.replace(/\/{2,}/g, '/'))

/* Inside the panel, without loading it again: the address is for a middle click. */
function setUpChannels(event: MouseEvent): void {
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return

  event.preventDefault()
  void router.push('/notifications')
}

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

/**
 * The fields that could hold the visitor's address, plus the option of naming none — by their
 * label, which is what the editor wrote; the machine name is beside it only to tell two alike.
 */
const emailOptions = computed(() => [
  { value: '', label: t('panel.no-email-field') },
  ...props.fields
    .filter((field) => field.type === 'email')
    .map((field) => ({ value: field.key, label: fieldLabel(field) })),
])

function fieldLabel(field: InboxField): string {
  const title = Object.values(field.title ?? {}).find(
    (line): line is string => typeof line === 'string' && line.trim() !== '',
  )

  if (title === undefined) return field.name ?? field.key

  return field.name ? `${title} · ${field.name}` : title
}

/*
 * Only the administrators' problems are said beside the row: an address being typed is not
 * wrong yet, and the server refuses a bad one on save in words of its own.
 */
function adminProblem(recipient: Recipient): string | null {
  if (!isAdmin(recipient)) return null

  const problem = problemOf(recipient, props.reported)

  return problem === null ? null : t(`panel.recipient-${problem}`)
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
  <wx-card
    class="wx-inbox-notifications"
    :title="props.notifications ? undefined : t('panel.recipients')"
  >
    <wx-form-item
      :label="t('panel.email-field')"
      :help="
        props.notifications ? t('panel.email-field-help-channels') : t('panel.email-field-help')
      "
      :error="errors['options.email_field']?.[0]"
    >
      <wx-select v-model="emailField" :options="emailOptions" />
    </wx-form-item>

    <wx-form-item v-if="props.notifications" :label="t('panel.notify-via')">
      <wx-radio-group v-model="via" :options="viaOptions" inline />
    </wx-form-item>

    <section v-if="byLetter" class="wx-inbox-notifications__way" data-way="email">
      <wx-text v-if="props.notifications" weight="medium">{{ t('panel.recipients') }}</wx-text>
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

        <div
          v-for="(recipient, index) in recipients"
          :key="index"
          class="wx-inbox-recipients__item"
        >
          <div class="wx-inbox-recipients__row">
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
            <wx-action
              icon="trash"
              tone="danger"
              :title="t('panel.remove')"
              @click="remove(index)"
            />
          </div>
          <wx-text v-if="adminProblem(recipient)" size="sm" tone="danger">
            {{ adminProblem(recipient) }}
          </wx-text>
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
        <wx-button variant="outline" @click="add({ email: '' })">
          <template #icon><wx-icon name="mail" /></template>
          {{ t('panel.add-email') }}
        </wx-button>
      </wx-space>
    </section>

    <section v-else class="wx-inbox-notifications__way" data-way="channels">
      <wx-text weight="medium">{{ t('panel.notify-through') }}</wx-text>
      <wx-text size="sm" tone="muted">{{ t('panel.notify-through-help') }}</wx-text>

      <div class="wx-inbox-channels">
        <wx-relations-field
          v-model="channels"
          target="notify-inbox-submission"
          :sortable="false"
          :add-text="t('panel.pick-channels')"
          :empty-text="t('panel.no-channels')"
          :none-text="t('panel.no-channels-yet')"
        />
        <wx-link v-if="canSetUp" :href="channelsLink" size="sm" @click="setUpChannels">{{
          t('panel.set-up-channels')
        }}</wx-link>
      </div>
    </section>
  </wx-card>
</template>

<style scoped>
/*
 * One way of telling under the shared field and the choice: its title, its help, its list. The
 * rows of a way sit closer than the form items above them — they are one answer, not three.
 */
.wx-inbox-notifications__way {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}

/*
 * As wide as a field, because that is what each row is: an address is typed into it and read
 * back off it. The rows are not form items — one of them is a select, the next an input, and
 * both carry a bin — so the cap that a form item applies has to be said here.
 */
.wx-inbox-recipients {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  margin-block: var(--wx-space-8) var(--wx-space-12);
  max-width: var(--wx-field-max-width, 640px);
}

.wx-inbox-channels {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-8);
  margin-block-start: var(--wx-space-8);
  max-width: var(--wx-field-max-width, 640px);
}

.wx-inbox-channels > :first-child {
  align-self: stretch;
}

.wx-inbox-recipients__item {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
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
