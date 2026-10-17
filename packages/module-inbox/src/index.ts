import { defineAsyncComponent } from 'vue'
export { inbox, type InboxOptions } from './module'
export { createInboxApi, type InboxApi } from './api'
export { inboxMessages } from './messages'
export { option } from './options'
export const WxInboxPage = defineAsyncComponent(() => import('./InboxPage.vue'))
export const WxInboxFormEditor = defineAsyncComponent(() => import('./FormEditorPage.vue'))
export const WxInboxStatusesPage = defineAsyncComponent(() => import('./StatusesPage.vue'))
export { default as WxInboxSubmissionList } from './SubmissionList.vue'
export const WxInboxSubmissionPage = defineAsyncComponent(() => import('./SubmissionPage.vue'))
export { default as WxInboxFieldList } from './FieldList.vue'
export { default as WxInboxFormField } from './FormField.vue'
export { FIELD_TYPES, STATUS_COLORS } from './types'
export type {
  ChannelDelivery,
  FieldChoice,
  FieldInput,
  FieldOptions,
  FieldType,
  FormCandidate,
  FormChannel,
  FormInput,
  FormOptions,
  InboxField,
  InboxForm,
  InboxAdmin,
  InboxPage,
  InboxRecipient,
  InboxStatus,
  InboxSubmission,
  Recipient,
  RecipientProblem,
  RecipientState,
  StatusColor,
  StatusInput,
  SubmissionAttachment,
  SubmissionColumn,
  SubmissionCounts,
  SubmissionEvent,
  SubmissionInput,
  SubmissionMassInput,
  SubmissionMeta,
  SubmissionQuery,
  SubmissionRow,
  SubmissionsPage,
  SubmissionValue,
} from './types'
