import type { ScreenModel } from '@webx-ui/schema'

/** A customer group as a row of a list says it (§13.1): a code and a name in one language. */
export interface GroupTag {
  code: string
  name: string
}

/** One customer in the list (§16.2). No password, no token, no id at a provider — ever. */
export interface UserRow {
  id: number
  /** First and last name; `null` for somebody who gave neither. */
  name: string | null
  email: string
  phone: string | null
  verified: boolean
  blocked: boolean
  groups: GroupTag[]
  /** `password` and the keys of the linked networks. */
  ways: string[]
  /** Linked networks that are switched off on the site: no way in any more. */
  ways_off: string[]
  created_at: string | null
  last_sign_in_at: string | null
}

/** A page of the list, the shape of Laravel's `paginate()`. */
export interface UsersPage {
  data: UserRow[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface UserQuery {
  search?: string
  group?: string
  verified?: boolean
  blocked?: boolean
  /** `password` or the key of a network. */
  provider?: string
  /** `YYYY-MM-DD`. */
  from?: string
  to?: string
  /** `-created_at` (default), `-last_sign_in_at`, `email`. */
  sort?: string
  page?: number
  per_page?: number
}

/** What the filters choose from. */
export interface UsersMeta {
  groups: GroupTag[]
  providers: { key: string; label: string; shown: boolean }[]
}

/** One way to sign in on the card, with the rule of the last way already applied. */
export interface SignInWay {
  /** `password` or the key of a network. */
  key: string
  label: string
  /** The address the network gave. */
  email: string | null
  linked_at: string | null
  used_at: string | null
  /** A network that is switched off is no way in. */
  shown: boolean
  can_unlink: boolean
}

/** The record half of the card — what the head and the side of the form show. */
export interface UserSummary {
  id: number
  name: string
  email: string
  verified: boolean
  verified_at: string | null
  blocked: boolean
  blocked_at: string | null
  has_password: boolean
  locale: string | null
  created_at: string | null
  last_sign_in_at: string | null
  ways: SignInWay[]
}

/** One customer as the card opens them: the record, and the values of `users.form` by name. */
export interface UserDetail {
  user: UserSummary
  values: ScreenModel
}

/** A customer group in its own list. */
export interface GroupRow {
  id: number
  code: string
  name: string
  users_count: number
  created_at: string | null
}

/** One field of a network's application, never with a secret's value. */
export interface ProviderField {
  name: string
  label: string
  secret: boolean
  required: boolean
  kind: string
  /** The value of an open field; always `null` for a secret. */
  value: string | null
  /** Whether the panel holds a value. */
  set: boolean
  /** Empty in the panel, filled by `.env`. */
  env: boolean
}

/** One network's card on the settings (§16.3). */
export interface ProviderCard {
  key: string
  label: string
  enabled: boolean
  /** Whether the panel has a row of it — without one, `.env` switches it on. */
  saved: boolean
  shown: boolean
  /** The required fields with no value anywhere. */
  missing: string[]
  callback: string
  fields: ProviderField[]
  /** Customers who sign in by this network alone. */
  only: number
}

export interface UsersSettings {
  /** Whether the site keeps settings at all (`module-settings`). */
  stored: boolean
  values: ScreenModel
  providers: ProviderCard[]
  mail: { transport: string; reaches: boolean }
}

/** What a provider's card sends: a secret only when a new one was typed. */
export interface ProviderSave {
  enabled?: boolean
  options?: Record<string, string>
  credentials?: Record<string, string>
  forget?: string[]
}
