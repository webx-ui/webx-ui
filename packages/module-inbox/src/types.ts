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
  position: number
  /** Only where the query counted them — null on a form loaded on its own. */
  submissions_count: number | null
  /** Unread and not spam. */
  unread_count: number | null
  /** Only on a form asked for by id, or one just written. */
  fields?: InboxField[]
  created_at: string | null
  updated_at: string | null
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
  type: 'created' | 'status' | 'assignee' | 'note' | 'notified' | string
  from: string | null
  to: string | null
  /** Null is the system — the submission arriving, the notification going out. */
  author: { id: number; name: string | null } | null
  created_at: string | null
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
  notified_at: string | null
  notify_error: string | null
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
