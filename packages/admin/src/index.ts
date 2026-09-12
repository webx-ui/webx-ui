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
