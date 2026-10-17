import { defineAsyncComponent } from 'vue'
export { settings, type SettingsOptions } from './module'
export { createSettingsApi, type SettingsApi } from './api'
export { settingsMessages } from './messages'
export const WxSettingsPage = defineAsyncComponent(() => import('./SettingsPage.vue'))
export type { SettingsValues } from './types'
