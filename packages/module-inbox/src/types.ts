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
