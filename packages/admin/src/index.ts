export { createAdmin, type Admin, type AdminPlugin, type CreateAdminOptions } from './createAdmin'
export {
  createAdminContext,
  provideAdmin,
  useAdmin,
  adminKey,
  type AdminContext,
  type AdminState,
} from './admin'
export {
  createI18n,
  provideI18n,
  useI18n,
  useTranslate,
  i18nKey,
  type Dictionary,
  type I18n,
  type I18nState,
  type LocaleDescriptor,
  type Messages,
  type Translate,
} from './i18n'
export { adminMessages } from './messages'
export {
  createHttp,
  readCookie,
  HttpError,
  type Http,
  type HttpOptions,
  type RequestOptions,
} from './http'
export type {
  AdminModule,
  AdminStatus,
  AdminUser,
  Manifest,
  ManifestModule,
  NavEntry,
} from './types'

export { default as AdminShell } from './AdminShell.vue'
export { default as AdminNav } from './AdminNav.vue'
