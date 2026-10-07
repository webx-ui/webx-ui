import type { LocalizedValue } from '@webx-ui/core'

/** The kinds of question a form can ask (§4). Mirrors `WebxUi\Inbox\Fields\FieldType`. */
export const FIELD_TYPES = [
  'text',
  'email',
  'tel',
  'textarea',
  'select',
  'radio',
  'checkbox',
  'consent',
  'date',
  'file',
  'hidden',
] as const

export type FieldType = (typeof FIELD_TYPES)[number]

/** Tones of `WxBadge`, which is what a status is coloured with — never a hex value. */
export const STATUS_COLORS = ['default', 'primary', 'success', 'warning', 'info', 'danger'] as const

export type StatusColor = (typeof STATUS_COLORS)[number]

/** One written-down answer of a `select`, `radio` or `checkbox`. */
export interface FieldChoice {
  value: string
  label: LocalizedValue | string
}

/**
 * What a field may be configured with. Which keys mean anything depends on the type (§4), and
 * the server keeps only the ones that do.
 */
export interface FieldOptions {
  maxlength?: number
  rows?: number
  pattern?: string
  min?: number | string
  max?: number | string
  choices?: FieldChoice[]
  text?: LocalizedValue | string
  max_size?: number
  extensions?: string[]
  multiple?: boolean
  [key: string]: unknown
}

export interface InboxField {
  id: number
  form_id: number
  /** What somebody typed, or null. */
  name: string | null
  /** What the HTML will actually say — `f17` for a field nobody named. */
  key: string
  type: FieldType
  title: LocalizedValue
  placeholder: LocalizedValue
  help: LocalizedValue
  options: FieldOptions
  is_enabled: boolean
  is_required: boolean
  is_fullsize: boolean
  in_table: boolean
  position: number
  /* A table row, so the index signature `WxTable` asks of what it draws. */
  [key: string]: unknown
}

/** A field as the dialog saves it. */
export interface FieldInput {
  name: string | null
  type: FieldType
  title: LocalizedValue
  placeholder?: LocalizedValue
  help?: LocalizedValue
  options?: FieldOptions
  is_enabled: boolean
  is_required: boolean
  is_fullsize: boolean
  in_table: boolean
}

/** Who a notification goes to: somebody with an account, or an address typed in. */
export type Recipient = { admin_id: number } | { email: string }

/** Why a recipient the form names would not get a letter. */
export type RecipientProblem = 'admin_deleted' | 'admin_inactive' | 'invalid_email'

/** A recipient as the server reads it today: who it is, and whether a letter would reach it. */
export interface RecipientState {
  type: 'admin' | 'email'
  admin_id?: number
  name?: string | null
  email: string | null
  receives: boolean
  problem: RecipientProblem | null
}

/** The settings of a form (§5). Every key is literal, dots included. */
export interface FormOptions {
  'thank-you.heading'?: LocalizedValue
  'thank-you.text'?: LocalizedValue
  'design.submit-text'?: LocalizedValue
  redirect?: string
  recipients?: Recipient[]
  email_field?: string
  'antispam.honeypot'?: boolean
  'antispam.min_seconds'?: number
  'antispam.throttle'?: number
  'antispam.captcha'?: 'off' | 'recaptcha' | 'turnstile'
  [key: string]: unknown
}

export interface InboxForm {
  id: number
  slug: string
  title: LocalizedValue
  is_enabled: boolean
  options: FormOptions
  /**
   * Who a submission would be written to, read from `options.recipients` as saved. Optional
   * only because a server older than this field does not send it.
   */
  recipients?: RecipientState[]
  /** False when a submission would be written to nobody at all. */
  notifies?: boolean
  position: number
  /** Only where the query counted them — null on a form loaded on its own. */
  submissions_count: number | null
  /** Unread and not spam. */
  unread_count: number | null
  /** Only on a form asked for by id, or one just written. */
  fields?: InboxField[]
  /**
   * What the site has for each captcha — the keys and how they are run live in its `.env`,
   * not in the form — so the antispam tab can say which kind of key it expects. Optional
   * because a server older than this field does not send it.
   */
  captcha?: Record<CaptchaProvider, CaptchaSite>
  created_at: string | null
  updated_at: string | null
}

export type CaptchaProvider = 'recaptcha' | 'turnstile'

/** How a provider runs on this site, and whether both halves of its keys are there. */
export interface CaptchaSite {
  type: 'checkbox' | 'invisible' | 'v3'
  configured: boolean
}

/** A form as its editor saves it. */
export interface FormInput {
  slug: string
  title: LocalizedValue
  is_enabled: boolean
  options: FormOptions
}

export interface InboxStatus {
  id: number
  key: string
  title: LocalizedValue
  color: StatusColor
  is_default: boolean
  is_spam: boolean
  is_closed: boolean
  position: number
  submissions_count: number | null
}

export interface StatusInput {
  key: string
  title: LocalizedValue
  color: StatusColor
  is_default: boolean
  is_spam: boolean
  is_closed: boolean
}

/** Somebody with an account who may read the section, for the recipients list. */
export interface InboxRecipient {
  id: number
  name: string
  email: string
}

/** An administrator as a name beside something else — an assignee, the author of a log line. */
export interface InboxAdmin {
  id: number
  name: string
  email: string
}

/** One column of the list: a field of the form that was marked `in_table`. */
export interface SubmissionColumn {
  key: string
  label: string
  type: FieldType
}

/** What each tab above the list would hold, under the filters that are on. */
export interface SubmissionCounts {
  all: number
  unread: number
  /** By the status's key, spam included — its own tab has to say how much is in it. */
  statuses: Record<string, number>
}

/** A page as Laravel's `->paginate()` serialises it, which is what `WxTable` reads. */
export interface InboxPage<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

/** One line of the list. The answers are keyed by the field's machine name. */
export interface SubmissionRow {
  id: number
  values: Record<string, string | null>
  status: InboxStatus | null
  assignee: InboxAdmin | null
  is_read: boolean
  source: string
  /** Where on the site the form stood (`footer`, `article`), or null when the page did not say. */
  placement: string | null
  files_count: number
  created_at: string | null
  /* A table row, so the index signature `WxTable` asks of what it draws. */
  [key: string]: unknown
}

/**
 * The list, and the two things that travel with it: what its columns are, and what each tab
 * above it would hold.
 */
export interface SubmissionsPage extends InboxPage<SubmissionRow> {
  columns: SubmissionColumn[]
  counts: SubmissionCounts
  /**
   * Every place on the site the form has been sent from, `null` for the page that did not say —
   * over the whole form rather than the filter, so the placement filter can always be undone.
   * More than one is what makes that filter worth showing.
   */
  placements: (string | null)[]
}

/** One answer, with the question as it was asked at the time (§2.2). */
export interface SubmissionValue {
  id: number
  field_id: number | null
  name: string
  label: string | null
  type: string
  value: string | null
  payload: unknown
}

export interface SubmissionAttachment {
  id: number
  field_id: number | null
  name: string
  size: number
  mime: string | null
  /** Through the panel, behind the same permission as the submission (§8). */
  url: string
}

/** One line of what has happened to a submission. Never edited, never deleted. */
export interface SubmissionEvent {
  id: number
  type:
    | 'created'
    | 'status'
    | 'assignee'
    | 'note'
    | 'notified'
    | 'handled'
    | 'handler_error'
    | 'no_recipients'
    | 'notify_queued'
    | 'notify_failed'
    | 'value'
    | string
  /** The answer a `value` line is about, by machine name; null on every other line. */
  field?: string | null
  from: string | null
  to: string | null
  /** Null is the system — the submission arriving, the notification going out. */
  author: { id: number; name: string | null } | null
  created_at: string | null
}

/**
 * Whether the letter about a submission left (§9). `queued` is handed to the site's queue and
 * not sent yet — on a site with a queue, sending is a worker's job, and a worker can be missing.
 */
export type NotifyState = 'none' | 'queued' | 'delivered' | 'failed'

export interface SubmissionNotification {
  state: NotifyState
  error: string | null
  queued_at: string | null
  delivered_at: string | null
  /** Empty on a submission from before the letters were watched one by one. */
  recipients: {
    address: string
    state: 'queued' | 'delivered' | 'failed'
    error: string | null
    at: string
  }[]
}

/** What the intake saw around the submission: the page, the language, the campaign. */
export interface SubmissionMeta {
  ip?: string
  user_agent?: string
  page?: string
  referrer?: string
  utm?: Record<string, string>
  locale?: string
  [key: string]: unknown
}

export interface InboxSubmission {
  id: number
  form: { id: number; slug: string | null; title: LocalizedValue | null }
  status: InboxStatus | null
  assignee: InboxAdmin | null
  values: SubmissionValue[]
  files: SubmissionAttachment[]
  meta: SubmissionMeta
  events: SubmissionEvent[]
  is_read: boolean
  source: string
  /** Where on the site the form stood (`footer`, `article`), or null when the page did not say. */
  placement: string | null
  notified_at: string | null
  notify_error: string | null
  /** The same two read as a state, with how each letter went. */
  notification: SubmissionNotification
  /** The neighbours in the list this was opened from, so the arrows walk the same pile. */
  previous_id: number | null
  next_id: number | null
  created_at: string | null
  updated_at: string | null
}

/** Everything the list asks the server for. The tab is `view`, the rest are filters. */
export interface SubmissionQuery {
  view?: string
  search?: string
  /** An administrator's id, or `none` — the pile nobody has picked up. */
  assignee?: string | null
  /** A placement, or `none` — the submissions whose page did not say. */
  placement?: string | null
  sort?: string | null
  page?: number
  per_page?: number
}

/** A submission changed by hand: the status, the assignee, a typo in an answer. */
export interface SubmissionInput {
  status_id?: number
  assignee_id?: number | null
  /** Machine name → the corrected text. The snapshot beside it is never rewritten. */
  values?: Record<string, string | null>
}

/** A pile dealt with at once. */
export interface SubmissionMassInput {
  ids: number[]
  action: 'status' | 'read' | 'unread' | 'delete'
  status_id?: number
}
