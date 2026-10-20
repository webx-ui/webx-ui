import type { HistoryEntry, HistoryPage } from '../../../../packages/module-admin/src/history'
import type { Patch, ScreenNode } from '../../../../packages/schema/src/types'
import { SUBMISSION_SAMPLES } from './notificationSamples'
import {
  renderTemplate,
  TemplateRefusal,
} from '../../../../packages/module-notifications/src/render'

/*
 * «Notifications» (WEBX_UI_MODULE_NOTIFICATIONS.md §10) without Telegram and without mail: three
 * channels — a bot with two chats, a letter to an administrator and to the visitor who wrote, one
 * in the bin — two events to choose from, and a log with a line of every status. "Send a test" and
 * "Find chats" answer the way the real ones do when everything works, and nothing leaves the
 * playground. Every chat id, address and name here is made up.
 *
 * A template is checked by the panel's own renderer, so a refusal here is the server's words.
 */

type On = (
  method: string,
  pattern: string,
  handler: (context: {
    params: string[]
    query: URLSearchParams
    body: Record<string, unknown>
    locale: string
  }) => unknown,
) => void
type Fail = (status: number, message: string, errors?: Record<string, string[]>) => Error
type Line = (locale: string, namespace: string, path: string) => string

interface Field {
  name: string
  label: string
  secret: boolean
  kind: 'text' | 'number' | 'choice' | 'email'
  choices: string[]
  default: unknown
}

interface Channel {
  id: number
  name: string
  provider: 'telegram' | 'email'
  event: string | null
  enabled: boolean
  recipients: Record<string, unknown>[]
  options: Record<string, unknown>
  secrets: string[]
  subject: Record<string, string>
  template: Record<string, string>
  copied_from: number | null
  updated_at: string
  deleted_at: string | null
}

interface Delivery {
  id: number
  channel_id: number | null
  channel_name: string
  provider: string
  event: string
  recipient: string
  recipient_label: string | null
  status: 'queued' | 'sent' | 'failed' | 'skipped'
  reason: string | null
  error: string | null
  attempts: number
  is_test: boolean
  truncated: boolean
  unknown_variables: string[]
  variables: Record<string, unknown>
  subject_id: number | null
  created_at: string
  sent_at: string | null
}

const TELEGRAM_FIELDS: Field[] = [
  { name: 'token', label: 'Bot token', secret: true, kind: 'text', choices: [], default: null },
]

const EMAIL_FIELDS: Field[] = [
  {
    name: 'mode',
    label: 'Send through',
    secret: false,
    kind: 'choice',
    choices: ['site', 'smtp'],
    default: 'site',
  },
  { name: 'host', label: 'SMTP server', secret: false, kind: 'text', choices: [], default: null },
  { name: 'port', label: 'Port', secret: false, kind: 'number', choices: [], default: 587 },
  {
    name: 'encryption',
    label: 'Encryption',
    secret: false,
    kind: 'choice',
    choices: ['tls', 'ssl', 'none'],
    default: 'tls',
  },
  { name: 'username', label: 'User name', secret: false, kind: 'text', choices: [], default: null },
  { name: 'password', label: 'Password', secret: true, kind: 'text', choices: [], default: null },
  {
    name: 'from_address',
    label: "Sender's address",
    secret: false,
    kind: 'email',
    choices: [],
    default: null,
  },
  {
    name: 'from_name',
    label: "Sender's name",
    secret: false,
    kind: 'text',
    choices: [],
    default: null,
  },
]

const PROVIDERS = [
  {
    key: 'telegram',
    label: 'Telegram',
    icon: 'send',
    fields: TELEGRAM_FIELDS,
    subject: false,
    address: 'telegram',
    limit: 4096,
    find_recipients: true,
  },
  {
    key: 'email',
    label: 'E-mail',
    icon: 'mail',
    fields: EMAIL_FIELDS,
    subject: true,
    address: 'email',
    limit: null,
    find_recipients: false,
  },
]

/* Two events: what the Inbox will declare (§15.1), and an order with a list in it (§7.2). */
const EVENTS = [
  {
    key: 'inbox.submission',
    label: 'A submission of a form',
    module: 'inbox',
    /* As `WebxUi\Inbox\Notifications\SubmissionEvent` declares it (§15.1). */
    variables: [
      {
        name: 'form',
        label: 'The name of the form',
        example: 'Contact us',
        address: null,
        fields: [],
      },
      {
        name: 'form_slug',
        label: 'The slug of the form',
        example: 'contact',
        address: null,
        fields: [],
      },
      {
        name: 'submission_id',
        label: 'The number of the submission',
        example: '128',
        address: null,
        fields: [],
      },
      {
        name: 'submission_url',
        label: 'The submission in the panel',
        example: 'https://site.test/cms/inbox/submissions/128',
        address: null,
        fields: [],
      },
      {
        name: 'created_at',
        label: 'When it arrived',
        example: '10/16/2026 2:05 PM',
        address: null,
        fields: [],
      },
      {
        name: 'fields',
        label: 'Every answer, a line each: “Label: value”',
        example: 'Name: Alex Doe\nPhone: +0 000 000-00-00\nMessage: Call me after six, please.',
        address: null,
        fields: [],
      },
      {
        name: 'answers',
        label: 'The answers, as a list',
        example: [
          { name: 'name', label: 'Name', value: 'Alex Doe' },
          { name: 'phone', label: 'Phone', value: '+0 000 000-00-00' },
          { name: 'message', label: 'Message', value: 'Call me after six, please.' },
        ],
        address: null,
        fields: [
          { name: 'name', label: 'The machine name of the field' },
          { name: 'label', label: 'The label of the field' },
          { name: 'value', label: 'The answer' },
        ],
      },
      {
        name: 'field.*',
        label: 'One answer, by the machine name of its field',
        example: '',
        address: null,
        fields: [],
      },
      {
        name: 'files',
        label: 'The names of the attached files',
        example: 'brief.pdf',
        address: null,
        fields: [],
      },
      {
        name: 'page',
        label: 'The page the form was sent from',
        example: 'https://site.test/contacts',
        address: null,
        fields: [],
      },
      {
        name: 'locale',
        label: 'The language of the submission',
        example: 'en',
        address: null,
        fields: [],
      },
      { name: 'utm', label: 'UTM tags', example: 'source=newsletter', address: null, fields: [] },
      {
        name: 'reply_to',
        label: "The visitor's e-mail, from the form's e-mail field",
        example: 'visitor@site.test',
        address: 'email',
        fields: [],
      },
    ],
    sample: SUBMISSION_SAMPLES,
  },
  {
    key: 'shop.order',
    label: 'An order is placed',
    module: 'shop',
    variables: [
      {
        name: 'number',
        label: 'The number of the order',
        example: '1042',
        address: null,
        fields: [],
      },
      { name: 'total', label: 'The total', example: '58.00', address: null, fields: [] },
      {
        name: 'note',
        label: "The buyer's note",
        example: 'Leave at the door',
        address: null,
        fields: [],
      },
      {
        name: 'buyer_chat',
        label: "The buyer's Telegram",
        example: '100000002',
        address: 'telegram',
        fields: [],
      },
      {
        name: 'items',
        label: 'The lines of the order',
        example: [
          { name: 'Green tea', qty: '2', price: '12.00' },
          { name: 'Cup <large>', qty: '1', price: '34.00' },
        ],
        address: null,
        fields: [
          { name: 'name', label: 'What' },
          { name: 'qty', label: 'How many' },
          { name: 'price', label: 'The price of the line' },
        ],
      },
    ],
    sample: {
      '*': {
        subject: { ru: 'Заказ {{number}}' },
        template: {
          ru: 'Заказ {{number}}\n{{#items}}\n- {{name}} × {{qty}}: {{price}}\n{{/items}}\nИтого {{total}}',
        },
      },
    },
  },
]

const ADMINS = [
  { id: 1, name: 'Anna K.', email: 'anna@site.test' },
  { id: 2, name: 'Oleg M.', email: 'oleg@site.test' },
]

const ago = (minutes: number) => new Date(Date.now() - minutes * 60_000).toISOString()

const channels: Channel[] = [
  {
    id: 1,
    name: 'Sales chat',
    provider: 'telegram',
    event: 'inbox.submission',
    enabled: true,
    recipients: [
      { chat_id: '-1000000000001', label: 'Sales team' },
      { chat_id: '100000001', label: 'Duty manager' },
    ],
    options: {},
    secrets: ['token'],
    subject: {},
    template: {
      ru: '<b>{{form}}</b> — {{field.name}}\n{{#answers}}\n{{label}}: {{value}}\n{{/answers}}\n<a href="{{submission_url}}">Открыть заявку</a>',
      en: '<b>{{form}}</b> — {{field.name}}\n{{#answers}}\n{{label}}: {{value}}\n{{/answers}}\n<a href="{{submission_url}}">Open the submission</a>',
    },
    copied_from: null,
    updated_at: ago(60 * 26),
    deleted_at: null,
  },
  {
    id: 2,
    name: 'Letter to the manager',
    provider: 'email',
    event: 'shop.order',
    enabled: false,
    recipients: [{ admin_id: 1 }, { email: 'orders@site.test' }],
    options: { mode: 'site', port: 587, encryption: 'tls', from_name: 'The shop' },
    secrets: [],
    subject: { ru: 'Заказ {{number}}', en: 'Order {{number}}' },
    template: {
      ru: 'Заказ {{number}}\n{{#items}}\n- {{name}} × {{qty}}: {{price}}\n{{/items}}\n{{#note}}Комментарий: {{note}}\n{{/note}}Итого {{total}}',
      en: 'Order {{number}}\n{{#items}}\n- {{name}} × {{qty}}: {{price}}\n{{/items}}\n{{#note}}Note: {{note}}\n{{/note}}Total {{total}}',
    },
    copied_from: null,
    updated_at: ago(60 * 3),
    deleted_at: null,
  },
  {
    id: 3,
    name: 'Old bot',
    provider: 'telegram',
    event: null,
    enabled: false,
    recipients: [{ chat_id: '100000003' }],
    options: {},
    secrets: ['token'],
    subject: {},
    template: { en: '{{form}}' },
    copied_from: null,
    updated_at: ago(60 * 24 * 9),
    deleted_at: ago(60 * 24 * 2),
  },
]

const deliveries: Delivery[] = [
  {
    id: 6,
    channel_id: 1,
    channel_name: 'Sales chat',
    provider: 'telegram',
    event: 'inbox.submission',
    recipient: '-1000000000001',
    recipient_label: 'Sales team',
    status: 'sent',
    reason: null,
    error: null,
    attempts: 1,
    is_test: false,
    truncated: false,
    unknown_variables: [],
    variables: {},
    subject_id: 42,
    created_at: ago(35),
    sent_at: ago(35),
  },
  {
    id: 5,
    channel_id: 1,
    channel_name: 'Sales chat',
    provider: 'telegram',
    event: 'inbox.submission',
    recipient: '100000001',
    recipient_label: 'Duty manager',
    status: 'failed',
    reason: null,
    error:
      'The bot is not in this chat, or was blocked there: Forbidden: bot was blocked by the user',
    attempts: 1,
    is_test: false,
    truncated: false,
    unknown_variables: [],
    variables: {},
    subject_id: 42,
    created_at: ago(35),
    sent_at: null,
  },
  {
    id: 4,
    channel_id: 2,
    channel_name: 'Letter to the manager',
    provider: 'email',
    event: 'shop.order',
    recipient: 'orders@site.test',
    recipient_label: null,
    status: 'skipped',
    reason: 'channel_disabled',
    error: null,
    attempts: 0,
    is_test: false,
    truncated: false,
    unknown_variables: [],
    variables: {},
    subject_id: 1042,
    created_at: ago(180),
    sent_at: null,
  },
  {
    id: 3,
    channel_id: 1,
    channel_name: 'Sales chat',
    provider: 'telegram',
    event: 'inbox.submission',
    recipient: '-1000000000001',
    recipient_label: 'Sales team',
    status: 'queued',
    reason: null,
    error: null,
    attempts: 1,
    is_test: false,
    truncated: false,
    unknown_variables: [],
    variables: {},
    subject_id: 41,
    created_at: ago(240),
    sent_at: null,
  },
  {
    id: 2,
    channel_id: 2,
    channel_name: 'Letter to the manager',
    provider: 'email',
    event: 'shop.order',
    recipient: 'anna@site.test',
    recipient_label: 'Anna K.',
    status: 'sent',
    reason: null,
    error: null,
    attempts: 1,
    is_test: true,
    truncated: false,
    unknown_variables: [],
    variables: {},
    subject_id: null,
    created_at: ago(60 * 5),
    sent_at: ago(60 * 5),
  },
  {
    id: 1,
    channel_id: null,
    channel_name: 'Removed channel',
    provider: 'telegram',
    event: 'inbox.submission',
    recipient: '100000009',
    recipient_label: null,
    status: 'sent',
    reason: null,
    error: null,
    attempts: 2,
    is_test: false,
    truncated: false,
    unknown_variables: ['phone'],
    variables: {},
    subject_id: 40,
    created_at: ago(60 * 24 * 3),
    sent_at: ago(60 * 24 * 3),
  },
]

const fieldsOf = (provider: string) => PROVIDERS.find((one) => one.key === provider)?.fields ?? []

function row(channel: Channel) {
  const event = EVENTS.find((one) => one.key === channel.event)
  const last = deliveries.find(
    (one) =>
      one.channel_id === channel.id &&
      !one.is_test &&
      (one.status === 'sent' || one.status === 'failed'),
  )

  return {
    id: channel.id,
    name: channel.name,
    provider: channel.provider,
    provider_label:
      PROVIDERS.find((one) => one.key === channel.provider)?.label ?? channel.provider,
    provider_declared: true,
    icon: channel.provider === 'telegram' ? 'send' : 'mail',
    event: channel.event,
    event_label: event?.label ?? null,
    event_declared: channel.event === null || event !== undefined,
    recipients: channel.recipients.length,
    enabled: channel.enabled,
    credentials_set: Object.fromEntries(channel.secrets.map((name) => [name, true])),
    credentials_readable: true,
    bot:
      channel.provider === 'telegram' && channel.secrets.includes('token')
        ? '@site_demo_bot'
        : null,
    copied_from: channel.copied_from,
    last_delivery: last
      ? { status: last.status, at: last.sent_at ?? last.created_at, error: last.error }
      : null,
    updated_at: channel.updated_at,
    deleted_at: channel.deleted_at,
  }
}

function values(channel: Channel) {
  const settings: Record<string, unknown> = {}

  for (const field of fieldsOf(channel.provider)) {
    settings[field.name] = field.secret ? '' : (channel.options[field.name] ?? field.default)
  }

  return {
    name: channel.name,
    provider: channel.provider,
    event: channel.event,
    enabled: channel.enabled,
    recipients: channel.recipients,
    subject: channel.subject,
    template: channel.template,
    ...settings,
  }
}

const detail = (channel: Channel, warnings: string[] = []) => ({
  data: { channel: row(channel), values: values(channel), warnings },
})

/** The checks of `ChannelValues` this playground needs to show: a name, Mustache, a full channel. */
function write(
  channel: Channel,
  input: Record<string, unknown>,
  locale: string,
  line: Line,
  fail: Fail,
): void {
  const errors: Record<string, string[]> = {}
  const say = (key: string, replace: Record<string, string> = {}) =>
    Object.entries(replace).reduce(
      (text, [name, value]) => text.split(`:${name}`).join(value),
      line(locale, 'webx-notifications', `errors.${key}`),
    )

  if ('name' in input && String(input.name ?? '').trim() === '')
    errors.name = [say('required', { field: 'name' })]

  for (const field of ['template', 'subject'] as const) {
    const texts = input[field]

    if (texts === undefined || texts === null || typeof texts !== 'object') continue

    for (const text of Object.values(texts as Record<string, string>)) {
      try {
        renderTemplate(String(text ?? ''), {}, (value) => value)
      } catch (error) {
        if (error instanceof TemplateRefusal) errors[field] = [say(error.key, error.replace)]
      }
    }
  }

  const recipients = Array.isArray(input.recipients)
    ? (input.recipients as Record<string, unknown>[])
    : channel.recipients
  const enabled = typeof input.enabled === 'boolean' ? input.enabled : channel.enabled

  if (enabled && recipients.length === 0) {
    errors.enabled = [say('cannot-enable', { why: say('missing-recipients') })]
  }

  if (Object.keys(errors).length > 0) throw fail(422, Object.values(errors)[0]![0]!, errors)

  if (typeof input.name === 'string') channel.name = input.name.trim()
  if ('event' in input)
    channel.event = typeof input.event === 'string' && input.event !== '' ? input.event : null
  channel.enabled = enabled
  channel.recipients = recipients
  if (input.template && typeof input.template === 'object')
    channel.template = input.template as Record<string, string>
  if (input.subject && typeof input.subject === 'object')
    channel.subject = input.subject as Record<string, string>

  for (const field of fieldsOf(channel.provider)) {
    const value = input[field.name]

    if (field.secret) {
      if (typeof value === 'string' && value.trim() !== '' && !channel.secrets.includes(field.name))
        channel.secrets.push(field.name)
    } else if (field.name in input) {
      channel.options[field.name] = value
    }
  }

  if (Array.isArray(input.forget))
    channel.secrets = channel.secrets.filter((name) => !(input.forget as string[]).includes(name))

  channel.updated_at = new Date().toISOString()
}

/* The provider's settings laid over the common form, as `Provider::screen()` does on the server. */
const visible = (provider: string) => ({ when: 'provider', is: provider })
const smtp = { when: 'mode', is: 'smtp' }

const PROVIDER_NODES: ScreenNode[] = [
  {
    id: 'token',
    type: 'wx-input',
    name: 'token',
    label: 'trans::webx-notifications::providers.telegram.token',
    help: 'trans::webx-notifications::screen.token-help',
    props: { type: 'password', autocomplete: 'off' },
    visible: visible('telegram'),
  },
  {
    id: 'telegram_recipients',
    type: 'wx-notifications-chats',
    name: 'recipients',
    label: 'trans::webx-notifications::screen.chats',
    help: 'trans::webx-notifications::screen.chats-help',
    visible: visible('telegram'),
  },
  {
    id: 'mode',
    type: 'wx-segmented',
    name: 'mode',
    label: 'trans::webx-notifications::providers.email.mode',
    help: 'trans::webx-notifications::screen.mode-help',
    props: {
      options: [
        { value: 'site', label: 'trans::webx-notifications::providers.email.mode-site' },
        { value: 'smtp', label: 'trans::webx-notifications::providers.email.mode-smtp' },
      ],
    },
    visible: visible('email'),
  },
  {
    id: 'host',
    type: 'wx-input',
    name: 'host',
    label: 'trans::webx-notifications::providers.email.host',
    visible: smtp,
  },
  {
    id: 'port',
    type: 'wx-input',
    name: 'port',
    label: 'trans::webx-notifications::providers.email.port',
    props: { type: 'number' },
    visible: smtp,
  },
  {
    id: 'encryption',
    type: 'wx-select',
    name: 'encryption',
    label: 'trans::webx-notifications::providers.email.encryption',
    props: {
      options: [
        { value: 'tls', label: 'TLS' },
        { value: 'ssl', label: 'SSL' },
        { value: 'none', label: 'trans::webx-notifications::providers.email.no-encryption' },
      ],
    },
    visible: smtp,
  },
  {
    id: 'username',
    type: 'wx-input',
    name: 'username',
    label: 'trans::webx-notifications::providers.email.username',
    props: { autocomplete: 'off' },
    visible: smtp,
  },
  {
    id: 'password',
    type: 'wx-input',
    name: 'password',
    label: 'trans::webx-notifications::providers.email.password',
    props: { type: 'password', autocomplete: 'off' },
    visible: smtp,
  },
  {
    id: 'from_address',
    type: 'wx-input',
    name: 'from_address',
    label: 'trans::webx-notifications::providers.email.from-address',
    help: 'trans::webx-notifications::screen.from-help',
    visible: visible('email'),
  },
  {
    id: 'from_name',
    type: 'wx-input',
    name: 'from_name',
    label: 'trans::webx-notifications::providers.email.from-name',
    visible: visible('email'),
  },
  {
    id: 'email_recipients',
    type: 'wx-notifications-emails',
    name: 'recipients',
    label: 'trans::webx-notifications::screen.emails',
    help: 'trans::webx-notifications::screen.emails-help',
    visible: visible('email'),
  },
]

export const notificationsChannelPatches: Patch[] = [
  [
    {
      op: 'set',
      target: 'provider',
      props: { options: PROVIDERS.map((one) => ({ value: one.key, label: one.label })) },
    },
    {
      op: 'set',
      target: 'event',
      props: { options: EVENTS.map((one) => ({ value: one.key, label: one.label })) },
    },
    ...PROVIDER_NODES.map((node) => ({ op: 'add' as const, target: 'connection', node })),
  ],
]

/**
 * `GET /history/notifications/{id}` — the bot's token replaced shows as a mask on both sides
 * (`historyMasked()`, §3.2): that it changed, never what it is.
 */
export function notificationsHistory(type: string, id: number): HistoryPage | null {
  if (type !== 'notifications') return null

  const entries: HistoryEntry[] =
    id !== 1
      ? []
      : [
          {
            id: 8101,
            event: 'updated',
            source: 'panel',
            subject: { type, id },
            admin: { id: 1, name: 'Anna K.' },
            grant_id: null,
            changes: [{ field: 'credentials', label: 'Secrets', from: '••••', to: '••••' }],
            run: null,
            created_at: ago(60 * 26),
          },
        ]

  return { data: entries, current_page: 1, last_page: 1, total: entries.length }
}

/** The target a consumer picks channels for an event from (`ChannelTarget`, §15.2). */
export function notifyCandidates(target: string) {
  const event = EVENTS.find((one) => `notify-${one.key.replace(/[._]/g, '-')}` === target)

  if (event === undefined) return null

  return channels
    .filter((one) => one.event === null || one.event === event.key)
    .map((one) => ({
      candidate: {
        id: one.id,
        title: one.name,
        subtitle: [row(one).provider_label, one.event === null ? 'Any event' : null]
          .filter(Boolean)
          .join(' · '),
        thumb: null,
        visible: one.enabled && one.deleted_at === null,
      },
      deleted: one.deleted_at !== null,
    }))
}

const STATUS_LABELS: Record<Delivery['status'], string> = {
  queued: 'Queued',
  sent: 'Sent',
  failed: 'Failed',
  skipped: 'Skipped',
}

const REASON_LABELS: Record<string, string> = {
  channel_deleted: 'The channel is deleted',
  channel_disabled: 'The channel is switched off',
  wrong_event: 'The channel is for another event',
  no_recipients: 'The channel has no recipients',
}

/** What the Inbox names on a form: the chosen channels, each with whether it sends (§15.2). */
export function channelsOfForm(ids: number[] | undefined) {
  return (ids ?? []).map((id) => {
    const channel = channels.find((one) => one.id === id)
    const deleted = channel === undefined || channel.deleted_at !== null
    const wrong =
      channel !== undefined && channel.event !== null && channel.event !== 'inbox.submission'

    return {
      id,
      name: channel?.name ?? null,
      provider: channel?.provider ?? null,
      enabled: channel?.enabled ?? false,
      deleted,
      sends: !deleted && !wrong && (channel?.enabled ?? false),
    }
  })
}

/** The card's "Channels": the log about one submission, newest first (§15.3). */
export function deliveriesOfSubmission(id: number) {
  return deliveries
    .filter((one) => one.subject_id === id && one.event === 'inbox.submission' && !one.is_test)
    .map((one) => ({
      id: one.id,
      channel_id: one.channel_id,
      channel: one.channel_name,
      provider: one.provider,
      recipient: one.recipient,
      recipient_label: one.recipient_label,
      status: one.status,
      status_label: STATUS_LABELS[one.status],
      reason: one.reason,
      reason_label: one.reason === null ? null : (REASON_LABELS[one.reason] ?? one.reason),
      error: one.error,
      attempts: one.attempts,
      retryable: (one.status === 'failed' || one.status === 'queued') && one.channel_id !== null,
      created_at: one.created_at,
      sent_at: one.sent_at,
    }))
}

/** "Send the notification again" of a submission: the playground has no bot, so it went. */
export function retrySubmission(id: number): number {
  let count = 0

  for (const one of deliveries) {
    if (one.subject_id !== id || one.is_test || one.channel_id === null) continue
    if (one.status !== 'failed' && one.status !== 'queued') continue

    one.status = 'sent'
    one.error = null
    one.attempts += 1
    one.sent_at = new Date().toISOString()
    count += 1
  }

  return count
}

export function registerNotifications(on: On, fail: Fail, line: Line): void {
  const find = (id: string, trashed = false) => {
    const found = channels.find(
      (one) => one.id === Number(id) && (one.deleted_at !== null) === trashed,
    )

    if (!found) throw fail(404, 'Not found.')

    return found
  }

  on('GET', '/notifications/meta', () => ({
    data: { providers: PROVIDERS, events: EVENTS, environment: 'local', mode: 'queue' },
  }))

  on('GET', '/notifications/channels', ({ query }) => {
    const trashed = query.get('trashed') === '1'
    const event = query.get('event')

    return {
      data: channels
        .filter((one) => (one.deleted_at !== null) === trashed)
        .filter((one) => !event || (event === 'none' ? one.event === null : one.event === event))
        .sort((a, b) => b.id - a.id)
        .map(row),
      environment: 'local',
    }
  })

  on('GET', '/notifications/channels/(\\d+)', ({ params }) => detail(find(params[0]!)))

  on('POST', '/notifications/channels', ({ body, locale }) => {
    const input = (body.values ?? {}) as Record<string, unknown>
    const provider = input.provider === 'telegram' ? 'telegram' : 'email'
    const made: Channel = {
      id: Math.max(0, ...channels.map((one) => one.id)) + 1,
      name: '',
      provider,
      event: null,
      enabled: false,
      recipients: [],
      options: {},
      secrets: [],
      subject: {},
      template: {},
      copied_from: null,
      updated_at: new Date().toISOString(),
      deleted_at: null,
    }

    write(made, { name: '', ...input }, locale, line, fail)
    channels.push(made)

    return detail(made)
  })

  on('PUT', '/notifications/channels/(\\d+)', ({ params, body, locale }) => {
    const channel = find(params[0]!)

    write(channel, (body.values ?? {}) as Record<string, unknown>, locale, line, fail)

    return detail(channel)
  })

  on('DELETE', '/notifications/channels/(\\d+)', ({ params }) => {
    find(params[0]!).deleted_at = new Date().toISOString()

    return { data: null }
  })

  on('POST', '/notifications/channels/(\\d+)/restore', ({ params }) => {
    const channel = find(params[0]!, true)

    channel.deleted_at = null

    return detail(channel)
  })

  on('DELETE', '/notifications/channels/(\\d+)/purge', ({ params }) => {
    const channel = find(params[0]!, true)

    channels.splice(channels.indexOf(channel), 1)

    return { data: null }
  })

  on('POST', '/notifications/channels/(\\d+)/copy', ({ params, locale }) => {
    const source = find(params[0]!)
    const copy: Channel = {
      ...structuredClone(source),
      id: Math.max(0, ...channels.map((one) => one.id)) + 1,
      name: line(locale, 'webx-notifications', 'module.copy-name').replace(':name', source.name),
      enabled: false,
      copied_from: source.id,
      updated_at: new Date().toISOString(),
    }

    channels.push(copy)

    return detail(copy)
  })

  /* As the real button when everything works: one line per recipient, sent. */
  const test = ({ params, body }: { params: string[]; body: Record<string, unknown> }) => {
    const input = (body.values ?? {}) as Record<string, unknown>
    const list = (
      Array.isArray(input.recipients)
        ? input.recipients
        : params[0]
          ? find(params[0]).recipients
          : []
    ) as Record<string, unknown>[]
    const only = typeof body.recipient === 'string' ? body.recipient : null
    const results = list
      .filter((one) => !('variable' in one))
      .map((one) => {
        const admin = ADMINS.find((person) => person.id === one.admin_id)

        return {
          recipient: String(one.chat_id ?? one.email ?? admin?.email ?? ''),
          label: (one.label as string | undefined) ?? admin?.name ?? null,
          status: 'sent',
          reason: null,
          error: null,
          truncated: false,
          unknown_variables: [],
        }
      })
      .filter((one) => only === null || one.recipient === only)

    return { data: { results } }
  }

  on('POST', '/notifications/test', test)
  on('POST', '/notifications/channels/(\\d+)/test', test)

  on('POST', '/notifications/check', ({ body }) => {
    const input = (body.values ?? {}) as Record<string, unknown>

    return input.provider === 'telegram'
      ? {
          data: {
            ok: true,
            message: 'The token works: the bot is @site_demo_bot.',
            details: { bot: '@site_demo_bot' },
          },
        }
      : { data: { ok: true, message: "Letters go through the site's mail.", details: {} } }
  })

  on('POST', '/notifications/find-recipients', () => ({
    data: {
      recipients: [
        { id: '100000001', type: 'private', title: 'Duty manager', username: '@duty_manager_demo' },
        { id: '-1000000000001', type: 'supergroup', title: 'Sales team', username: null },
        { id: '-1000000000002', type: 'group', title: 'Warehouse', username: null },
      ],
      hint: null,
    },
  }))

  on('GET', '/notifications/administrators', () => ({ data: ADMINS }))

  on('GET', '/notifications/deliveries', ({ query }) => {
    const status = query.get('status')
    const channel = query.get('channel')
    const tests = query.get('tests') !== '0'
    const perPage = Number(query.get('per_page') ?? 25) || 25
    const page = Number(query.get('page') ?? 1) || 1
    const found = deliveries
      .filter((one) => !status || one.status === status)
      .filter((one) => !channel || one.channel_id === Number(channel))
      .filter((one) => tests || !one.is_test)
    const slice = found.slice((page - 1) * perPage, page * perPage)

    return {
      data: slice.map((one) => ({
        ...one,
        event_label: EVENTS.find((event) => event.key === one.event)?.label ?? null,
        subject_type: one.subject_id === null ? null : 'submission',
        subject_url:
          one.subject_id === null || one.event !== 'inbox.submission'
            ? null
            : `/panel/inbox/submissions/${one.subject_id}`,
        locale: null,
        retryable:
          (one.status === 'failed' || one.status === 'queued') &&
          !one.is_test &&
          one.channel_id !== null,
      })),
      current_page: page,
      last_page: Math.max(1, Math.ceil(found.length / perPage)),
      per_page: perPage,
      total: found.length,
      from: found.length === 0 ? null : (page - 1) * perPage + 1,
      to: found.length === 0 ? null : (page - 1) * perPage + slice.length,
      environment: 'local',
    }
  })

  on('POST', '/notifications/deliveries/(\\d+)/retry', ({ params }) => {
    const delivery = deliveries.find((one) => one.id === Number(params[0]))

    if (!delivery) throw fail(404, 'Not found.')

    delivery.status = 'queued'
    delivery.attempts += 1
    delivery.error = null

    return { data: { ...delivery, retryable: false } }
  })
}
