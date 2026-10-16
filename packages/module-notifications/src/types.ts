import type { ScreenModel } from '@webx-ui/schema'

/** What happened to one recipient of one channel (§9). */
export type DeliveryStatus = 'queued' | 'sent' | 'failed' | 'skipped'

/** A setting of a provider: a bot's token, an SMTP host (`Sending\Field` on the server). */
export interface ProviderField {
  name: string
  label: string
  /** Lives encrypted in `credentials` and is answered only with "it is set". */
  secret: boolean
  kind: 'text' | 'number' | 'choice' | 'email'
  choices: string[]
  default: unknown
}

/** A kind of channel (§4): Telegram, e-mail, or one a site registered. */
export interface NotificationProvider {
  key: string
  label: string
  icon: string
  fields: ProviderField[]
  /** Whether a message has a subject — e-mail does, Telegram does not. */
  subject: boolean
  /** The kind of address an event's addressee must be to receive through it: `email`, `telegram`. */
  address: string
  /** The longest message, in characters; null for none. */
  limit: number | null
  /** Whether it can find its recipients itself — Telegram's "Find chats". */
  find_recipients: boolean
}

/** A variable an event fills a template with (`Variable::toArray()`). */
export interface EventVariable {
  /** `form`, `field.*` for a family of names, `items` for a list. */
  name: string
  label: string
  example: unknown
  /** `email` or `telegram` when the value is an address a channel may send to. */
  address: string | null
  /** A list: the fields of each element. */
  fields: { name: string; label: string }[]
}

/** An event a consumer declared (§5). */
export interface NotificationEvent {
  key: string
  label: string
  module: string
  variables: EventVariable[]
  /** What a new channel starts with, by provider; `*` for any. */
  sample: Record<string, { subject?: string | null; template: string }>
}

/** `GET meta`: what the site has. */
export interface NotificationsMeta {
  providers: NotificationProvider[]
  events: NotificationEvent[]
  environment: string
  /** How sending goes: `queue`, `after_response`. */
  mode: string
}

/** One channel as a row of the list and the head of its form (`ChannelForm::row()`). */
export interface ChannelRow {
  id: number
  name: string
  provider: string
  provider_label: string
  /** False when the provider's package is gone: the channel sends nothing. */
  provider_declared: boolean
  icon: string | null
  event: string | null
  event_label: string | null
  /** False when the event is no longer declared: the channel works as one for any event. */
  event_declared: boolean
  /** How many recipients. */
  recipients: number
  enabled: boolean
  /** Which secrets are set — never their values. */
  credentials_set: Record<string, true>
  /** False when the secrets cannot be decrypted: the key of the site changed. */
  credentials_readable: boolean
  /** The bot a check found, `@name`, kept for an hour. */
  bot: string | null
  copied_from: number | null
  last_delivery: { status: DeliveryStatus; at: string | null; error: string | null } | null
  updated_at: string | null
  deleted_at: string | null
}

/** A channel as its form opens it. Secret fields are always empty in `values`. */
export interface ChannelDetail {
  channel: ChannelRow
  values: ScreenModel
  /** What to look at although the channel was saved: names no event has. */
  warnings: string[]
}

export interface ChannelsList {
  data: ChannelRow[]
  environment: string
}

/** A recipient of a Telegram channel. */
export interface ChatRecipient {
  chat_id?: string
  label?: string
  /** An addressee of the event instead of a fixed chat. */
  variable?: string
}

/** A recipient of an e-mail channel. */
export interface EmailRecipient {
  admin_id?: number
  email?: string
  variable?: string
}

/** A chat "Find chats" found among the bot's updates (§6.3). */
export interface FoundChat {
  id: string
  /** `private`, `group`, `supergroup`, `channel`. */
  type: string
  title: string
  username: string | null
}

export interface Administrator {
  id: number
  name: string
  email: string
}

/** One line of the delivery log (`DeliveryController::row()`). */
export interface DeliveryRow {
  id: number
  channel_id: number | null
  channel_name: string
  provider: string
  event: string
  event_label: string | null
  subject_type: string | null
  subject_id: number | null
  subject_url: string | null
  locale: string | null
  recipient: string
  recipient_label: string | null
  status: DeliveryStatus
  reason: string | null
  error: string | null
  attempts: number
  is_test: boolean
  truncated: boolean
  unknown_variables: string[]
  variables: Record<string, unknown>
  retryable: boolean
  created_at: string | null
  sent_at: string | null
}

/** A page of the log, as Laravel's paginator answers it. */
export interface DeliveriesPage {
  data: DeliveryRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
  environment: string
}

export interface DeliveriesQuery {
  page?: number
  per_page?: number
  channel?: number | null
  status?: DeliveryStatus | null
  event?: string | null
  /** False hides the tests. */
  tests?: boolean
}

/** What a test did for one recipient. */
export interface TestResult {
  recipient: string
  label?: string | null
  status: DeliveryStatus
  error?: string | null
  reason?: string | null
  truncated?: boolean
  unknown_variables?: string[]
}

/** What the server's check of the settings said. */
export interface CheckAnswer {
  ok: boolean
  message: string
  details?: Record<string, unknown>
}

/** The message rendered on the event's examples, by the server (`POST preview`). */
export interface ServerPreview {
  subject: string | null
  body: string
  plain: string
  truncated: boolean
  unknown_variables: string[]
}
