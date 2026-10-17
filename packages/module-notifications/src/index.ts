import { defineAsyncComponent } from 'vue'
export { notifications, type NotificationsOptions } from './module'
export { createNotificationsApi, NOTIFICATIONS_API, type NotificationsApi } from './api'
export { notificationsMessages } from './messages'
export {
  escapeFor,
  escapeHtml,
  examplesOf,
  renderMessage,
  renderTemplate,
  TemplateRefusal,
  type MessageInput,
  type RenderedMessage,
  type TemplateValues,
} from './render'
export { telegramHtml } from './telegram'
export { addresseesOf, snippetOf, variablesOf } from './variables'
export { provideChannel, useChannel, type ChannelContext } from './context'
export const WxNotificationsPage = defineAsyncComponent(() => import('./NotificationsPage.vue'))
export const WxNotificationsChannelPage = defineAsyncComponent(() => import('./ChannelPage.vue'))
export { default as WxNotificationsPreview } from './MessagePreview.vue'
export { default as WxNotificationsTemplateField } from './TemplateField.vue'
export { default as WxNotificationsChatsField } from './ChatsField.vue'
export { default as WxNotificationsEmailsField } from './EmailsField.vue'
export { default as WxNotificationChannelsField } from './ChannelsField.vue'
export type * from './types'
