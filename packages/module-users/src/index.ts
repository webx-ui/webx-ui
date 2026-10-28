import { defineAsyncComponent } from 'vue'
import './style.css'

export { users, type UsersOptions } from './module'
export { usersMessages } from './messages'
export { createUsersApi, userSearch, USERS_API, type UsersApi } from './api'
export { provideUserEditor, userEditorKey, useUserEditor, type UserEditorContext } from './editor'
export const WxUsersPage = defineAsyncComponent(() => import('./UsersPage.vue'))
export const WxUserEditor = defineAsyncComponent(() => import('./UserEditorPage.vue'))
export const WxUsersGroupsPage = defineAsyncComponent(() => import('./GroupsPage.vue'))
export const WxUsersSettingsPage = defineAsyncComponent(() => import('./SettingsPage.vue'))
export { default as WxUsersState } from './StateField.vue'
export { default as WxUsersWays } from './WaysField.vue'
export type {
  GroupRow,
  GroupTag,
  ProviderCard,
  ProviderField,
  ProviderSave,
  SignInWay,
  UserDetail,
  UserQuery,
  UserRow,
  UsersMeta,
  UsersPage,
  UsersSettings,
  UserSummary,
} from './types'
