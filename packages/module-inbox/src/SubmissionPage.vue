<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxBackButton,
  WxDate,
  WxNotes,
  WxRowMenu,
  type RowAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  localizedValue,
  toast,
  useLocales,
  WxAction,
  WxAlert,
  WxBadge,
  WxButton,
  WxCard,
  WxDescriptions,
  WxDescriptionsItem,
  WxEmpty,
  WxFileCard,
  WxHeading,
  WxSelect,
  WxSkeleton,
  WxTab,
  WxTabs,
  WxText,
  WxTimeline,
  WxTimelineItem,
} from '@webx-ui/core'
import { createInboxApi } from './api'
import { useInboxMessages } from './i18n'
import type { InboxRecipient, InboxStatus, InboxSubmission, SubmissionEvent } from './types'

/**
 * One submission, on a screen of its own (§11).
 *
 * A dialog over the list would have kept the filters under the reader's hand, and it was
 * rejected for one reason: a submission needs an address somebody can send. It goes into the
 * mail to whoever deals with it, it gets pasted into a chat, an agent links to it. What the
 * dialog would have given back is paid for here instead — the list's whole state travels in
 * the address, so the way out lands exactly where the reader was, and the arrows walk the same
 * filtered pile.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/inbox' })

const context = useAdmin()
const api = createInboxApi(context)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
useInboxMessages()

const t = useTranslate('webx-inbox')
/* The notes feed is the panel's own, and so is the word for it: a second "Notes" in this
   module's dictionary would be the same line translated twice. */
const panel = useTranslate('webx-admin')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const submission = ref<InboxSubmission | null>(null)
const statuses = ref<InboxStatus[]>([])
const admins = ref<InboxRecipient[]>([])
const loading = ref(true)

const canUpdate = computed(() => context.can('inbox.update'))

const id = computed(() => Number(route.params.id))

/** Everything the list was looking at, carried along so the arrows walk the same pile. */
const filter = computed(() => ({
  view: typeof route.query.view === 'string' ? route.query.view : undefined,
  search: typeof route.query.search === 'string' ? route.query.search : undefined,
  assignee: typeof route.query.assignee === 'string' ? route.query.assignee : undefined,
}))

/** Back to the list, on the form, the tab and the page it was left at. */
const backTo = computed(() => ({ path: props.base, query: { ...route.query } }))

const statusOptions = computed(() =>
  statuses.value.map((status) => ({ value: status.id, label: name(status) })),
)

/* Nobody is not an option in the list but the empty state of the control: a select whose
   first line means "none" is one somebody picks by accident, and `clearable` already says
   it. */
const assigneeOptions = computed(() =>
  admins.value.map((admin) => ({ value: admin.id, label: admin.name })),
)

function name(status: InboxStatus): string {
  return localizedValue(status.title, locales.active.value, status.key)
}

const heading = computed(() => t('panel.submission', { id: String(id.value) }))

const formTitle = computed(() => {
  const form = submission.value?.form

  return form === undefined
    ? ''
    : localizedValue(form.title ?? {}, locales.active.value, form.slug ?? '')
})

/**
 * Where a reply goes.
 *
 * The form says which field holds the sender's address (`email_field`); without one, the first
 * answer that was asked as an e-mail is the honest guess, and where there is none the button
 * is simply not there — a `mailto:` with nothing after it opens an empty window.
 */
const replyTo = computed(() => {
  const values = submission.value?.values ?? []
  const answer = values.find((value) => value.type === 'email' && (value.value ?? '') !== '')

  return answer?.value ?? null
})

const mailto = computed(() => {
  if (replyTo.value === null) return null

  const subject = encodeURIComponent(`${formTitle.value} — ${heading.value}`)

  return `mailto:${replyTo.value}?subject=${subject}`
})

async function load(): Promise<void> {
  loading.value = true

  try {
    submission.value = await api.submission(id.value, filter.value)
  } catch (error) {
    toast.danger(message(error))
    void router.replace(backTo.value)
  } finally {
    loading.value = false
  }
}

async function lists(): Promise<void> {
  try {
    statuses.value = await api.statuses()

    // Only where there is something to do with them: assigning is `inbox.update`, and the
    // list of administrators is not a reader's business.
    if (canUpdate.value) {
      admins.value = await api.recipients()
    }
  } catch (error) {
    toast.danger(message(error))
  }
}

/* The arrows change the address without leaving the screen, so the record has to follow it. */
watch(id, () => void load(), { immediate: true })

void lists()

async function save(input: { status_id?: number; assignee_id?: number | null }): Promise<void> {
  try {
    submission.value = await api.saveSubmission(id.value, input)
    toast.success(t('panel.saved'))
  } catch (error) {
    toast.danger(message(error))
    await load()
  }
}

function onStatus(value: unknown): void {
  if (typeof value === 'number') void save({ status_id: value })
}

function onAssignee(value: unknown): void {
  void save({ assignee_id: typeof value === 'number' ? value : null })
}

const actions = computed<RowAction[]>(() => {
  if (!canUpdate.value || submission.value === null) return []

  return [
    {
      key: 'unread',
      icon: 'eye-off',
      label: t('panel.mark-unread'),
      run: () => void unread(),
    },
    {
      key: 'delete',
      icon: 'trash',
      label: t('panel.delete'),
      danger: true,
      run: () => void remove(),
    },
  ]
})

async function unread(): Promise<void> {
  try {
    await api.massSubmissions({ ids: [id.value], action: 'unread' })
    // Straight back to the list: a submission marked unread and then left open is one the
    // next request would mark read again.
    void router.push(backTo.value)
  } catch (error) {
    toast.danger(message(error))
  }
}

async function remove(): Promise<void> {
  const agreed = await confirm({
    title: t('panel.delete-submission-title'),
    message: t('panel.delete-submission-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeSubmission(id.value)
    toast.success(t('panel.deleted'))
    void router.push(backTo.value)
  } catch (error) {
    toast.danger(message(error))
  }
}

function go(to: number | null): void {
  if (to === null) return

  void router.push({ path: `${props.base}/submissions/${to}`, query: { ...route.query } })
}

/**
 * A status the log names, in the words the panel calls it by.
 *
 * The log stores the key on purpose — read months later, the row it pointed at may have been
 * renamed or deleted, and history must not be rewritten by either. So the key is translated
 * where the panel still has that status, and shown as it stands where it does not: `spam` is
 * a poorer line than "Спам", and an invented one would be worse than both.
 */
function statusName(key: string): string {
  const status = statuses.value.find((one) => one.key === key)

  return status === undefined ? key : name(status)
}

/** One line of the log, in words rather than as a pair of columns. */
function line(event: SubmissionEvent): string {
  switch (event.type) {
    case 'created':
      return t('panel.event-created')
    case 'status':
      return t('panel.event-status', { to: statusName(event.to ?? '') })
    case 'assignee':
      return event.to === null || event.to === ''
        ? t('panel.event-unassigned')
        : t('panel.event-assignee', { to: event.to })
    case 'note':
      return t('panel.event-note')
    case 'notified':
      return t('panel.event-notified')
    default:
      return event.type
  }
}

function kilobytes(size: number): string {
  return `${Math.max(1, Math.round(size / 1024))} KB`
}

/** The metadata worth showing, in the order somebody reads it, with the empty ones left out. */
const details = computed(() => {
  const meta = submission.value?.meta ?? {}

  const rows: { label: string; value: string; link?: boolean }[] = []

  if (typeof meta.page === 'string' && meta.page !== '') {
    rows.push({ label: t('panel.meta-page'), value: meta.page, link: true })
  }

  if (typeof meta.referrer === 'string' && meta.referrer !== '') {
    rows.push({ label: t('panel.meta-referrer'), value: meta.referrer, link: true })
  }

  for (const [key, value] of Object.entries(meta.utm ?? {})) {
    rows.push({ label: key, value: String(value) })
  }

  if (typeof meta.locale === 'string' && meta.locale !== '') {
    rows.push({ label: t('panel.meta-locale'), value: meta.locale })
  }

  if (typeof meta.ip === 'string' && meta.ip !== '') {
    rows.push({ label: t('panel.meta-ip'), value: meta.ip })
  }

  if (typeof meta.user_agent === 'string' && meta.user_agent !== '') {
    rows.push({ label: t('panel.meta-agent'), value: meta.user_agent })
  }

  return rows
})
</script>

<template>
  <div class="wx-submission">
    <div class="wx-submission__head">
      <!-- The way back keeps its own size: it belongs to the heading beside it, not to the row
           of actions at the other end of the line. -->
      <wx-back-button :to="backTo" />

      <div class="wx-submission__who">
        <wx-heading :level="2" truncate>{{ heading }}</wx-heading>
        <wx-text size="sm" tone="muted" truncate>{{ formTitle }}</wx-text>
      </div>

      <!--
        One height for everything on this line, and the button with a word on it is what sets
        it: an icon button at `lg` is 42px, which is what a `md` button measures. Left to their
        defaults they came out four different sizes — 30 for the way back, 36 for the arrows,
        42 for the reply, 30 for the menu — and a row of controls that each picked their own
        reads as four unrelated things rather than as one set.

        The same pile the reader was looking at, one step at a time. An arrow with nowhere to
        go is disabled rather than hidden: the pair is a control, and a control that changes
        shape at the ends is one that moves under the hand.

        One group and not four items in the head's row, so that on a narrow screen they go to
        the next line together instead of breaking wherever the wrap happens to fall — which
        left the arrows up by the name and the reply and the menu alone underneath.
      -->
      <div class="wx-submission__tools">
        <wx-action
          icon="chevron-left"
          size="lg"
          :title="t('panel.previous')"
          :disabled="!submission?.previous_id"
          @click="go(submission?.previous_id ?? null)"
        />
        <wx-action
          icon="chevron-right"
          size="lg"
          :title="t('panel.next')"
          :disabled="!submission?.next_id"
          @click="go(submission?.next_id ?? null)"
        />

        <wx-button
          v-if="mailto"
          class="wx-submission__reply"
          variant="outline"
          icon="mail"
          :href="mailto"
        >
          {{ t('panel.reply') }}
        </wx-button>

        <wx-row-menu :actions="actions" size="lg" :label="heading" />
      </div>
    </div>

    <wx-skeleton v-if="loading" :rows="6" />

    <div v-else-if="submission" class="wx-submission__panes">
      <div class="wx-submission__main">
        <wx-card :title="t('panel.answers')">
          <wx-empty
            v-if="submission.values.length === 0"
            size="sm"
            :description="t('panel.no-answers')"
          />
          <!-- The labels are the snapshot written when it arrived, never the field's current
               words: a submission from a year ago has to read as it did (§2.2). -->
          <wx-descriptions v-else :columns="1" layout="vertical">
            <wx-descriptions-item
              v-for="value in submission.values"
              :key="value.id"
              :label="value.label ?? value.name"
            >
              <p class="wx-submission__value">{{ value.value || '—' }}</p>
            </wx-descriptions-item>
          </wx-descriptions>
        </wx-card>

        <wx-card v-if="submission.files.length > 0" :title="t('panel.attachments')">
          <div class="wx-submission__files">
            <!-- Through the panel and not from the disk: the bytes are behind the same
                 permission as the submission, for as long as the reader has it (§8). -->
            <wx-file-card
              v-for="file in submission.files"
              :key="file.id"
              :name="file.name"
              :type="file.mime ?? undefined"
              :download-url="file.url"
              :download-label="t('panel.download')"
              size="sm"
            >
              <template #meta>{{ kilobytes(file.size) }}</template>
            </wx-file-card>
          </div>
        </wx-card>

        <!--
          Everything about the submission rather than in it, behind one strip.

          Three cards stacked down a column is three headings to read past before the eye gets
          to the one that was wanted, and on a phone it is three screens of scrolling. They are
          not read together — a note is written while replying, the log is opened when
          something looks wrong, the metadata once — so only one of them is ever the answer.
          Pills rather than a line, and inside the card rather than over it: this is one card
          changing its contents, not a place in the panel that can be navigated to. The strip is
          the card's heading — which is why there is no other one.
        -->
        <wx-card class="wx-submission__more">
          <wx-tabs variant="pill" :aria-label="heading">
            <wx-tab value="notes" :label="panel('notes.title')">
              <!-- The feed is the panel's own, not this module's: the same one will hang off
                   an order and a client (§2.17). Its heading is the tab. -->
              <wx-notes
                :id="submission.id"
                type="inbox_submission"
                title=""
                :can="canUpdate"
                @change="load"
              />
            </wx-tab>

            <wx-tab value="log" :label="t('panel.log')">
              <wx-timeline size="sm">
                <wx-timeline-item v-for="event in submission.events" :key="event.id">
                  <!-- What happened on one line and who did it when on the next: three inline
                       pieces in a row run into each other, and a log is read down. -->
                  <wx-text size="sm" as="p" class="wx-submission__event">{{ line(event) }}</wx-text>
                  <p class="wx-submission__by">
                    <wx-text size="sm" tone="muted">
                      {{ event.author?.name ?? t('panel.system') }}
                    </wx-text>
                    <wx-date :value="event.created_at" />
                  </p>
                </wx-timeline-item>
              </wx-timeline>
            </wx-tab>

            <wx-tab value="details" :label="t('panel.details')">
              <div class="wx-submission__fields">
                <wx-descriptions :columns="1" layout="vertical" size="sm">
                  <wx-descriptions-item :label="t('panel.received')">
                    <wx-date :value="submission.created_at" tone="default" />
                  </wx-descriptions-item>
                  <wx-descriptions-item :label="t('panel.meta-source')">
                    <wx-badge :type="submission.source === 'panel' ? 'info' : 'default'">
                      {{
                        submission.source === 'panel'
                          ? t('panel.source-panel')
                          : t('panel.source-web')
                      }}
                    </wx-badge>
                  </wx-descriptions-item>
                  <wx-descriptions-item v-for="row in details" :key="row.label" :label="row.label">
                    <a v-if="row.link" :href="row.value" target="_blank" rel="noreferrer">{{
                      row.value
                    }}</a>
                    <span v-else class="wx-submission__detail">{{ row.value }}</span>
                  </wx-descriptions-item>
                </wx-descriptions>

                <!-- An unsent notification is a mark on the submission, not a lost one
                     (§2.10), and the only place it is ever said out loud is here. -->
                <wx-alert v-if="submission.notify_error" type="warning" :closable="false">
                  {{ t('panel.notify-failed') }}
                </wx-alert>
                <wx-text v-else-if="submission.notified_at" size="sm" tone="muted">
                  {{ t('panel.notified') }}
                </wx-text>
                <wx-text v-else size="sm" tone="muted">{{ t('panel.not-notified') }}</wx-text>
              </div>
            </wx-tab>
          </wx-tabs>
        </wx-card>
      </div>

      <aside class="wx-submission__aside">
        <wx-card>
          <div class="wx-submission__fields">
            <label class="wx-submission__field">
              <wx-text size="sm" tone="muted">{{ t('panel.status') }}</wx-text>
              <wx-select
                :model-value="submission.status?.id ?? null"
                :options="statusOptions"
                :disabled="!canUpdate"
                @update:model-value="onStatus"
              />
            </label>

            <label class="wx-submission__field">
              <wx-text size="sm" tone="muted">{{ t('panel.assignee') }}</wx-text>
              <wx-select
                :model-value="submission.assignee?.id ?? null"
                :options="assigneeOptions"
                :disabled="!canUpdate"
                clearable
                :placeholder="t('panel.unassigned')"
                @update:model-value="onAssignee"
              />
            </label>
          </div>
        </wx-card>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.wx-submission {
  display: flex;
  flex-direction: column;
  /* The panel says how far apart things stand, and it says something different on a phone
     than on a desktop (8, 12, 16). Writing the desktop number here is what made this screen
     the one place in the panel with desktop air on a 375px screen. */
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
  /* The panes below decide their own layout from the width of the screen rather than of the
     window: the panel has a sidebar, and the window knows nothing about it. */
  container-type: inline-size;
}

/*
 * The way out lines up with the heading, not with the pair of lines under it: what stands
 * beside it is two lines — which submission this is and which form it came through — and a
 * centred row put the arrow level with the gap between them.
 */
.wx-submission__head {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-8);
  flex-wrap: wrap;
}

.wx-submission__who {
  flex: 1 1 auto;
  min-width: 0;
}

.wx-submission__tools {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

/*
 * On a phone the group takes the line under the name, whole, and the one control with a word
 * on it takes what the icons leave — a button that says "reply by mail" in the middle of a
 * row of empty space is a button that looks like it did not fit.
 */
@container (max-width: 560px) {
  .wx-submission__tools {
    flex: 1 1 100%;
  }

  /* `:deep()` because the class is ours and the element it rides is `WxButton`'s. */
  .wx-submission__tools > :deep(.wx-submission__reply) {
    flex: 1 1 auto;
  }
}

.wx-submission__panes {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
}

.wx-submission__main,
.wx-submission__aside {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
}

/* The answers are what the screen is for, so they take the room; everything about the
   submission rather than in it stands beside them. */
@container (min-width: 900px) {
  .wx-submission__panes {
    flex-direction: row;
    align-items: flex-start;
  }

  .wx-submission__main {
    flex: 1 1 auto;
  }

  .wx-submission__aside {
    flex: 0 0 320px;
    max-width: 320px;
  }
}

.wx-submission__value {
  margin: 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.wx-submission__detail {
  overflow-wrap: anywhere;
}

.wx-submission__event {
  margin: 0;
}

.wx-submission__by {
  display: flex;
  align-items: baseline;
  gap: var(--wx-space-6);
  flex-wrap: wrap;
  margin: 0;
}

.wx-submission__files {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}

.wx-submission__fields {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-submission__field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}
</style>
