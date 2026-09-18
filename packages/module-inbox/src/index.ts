export { inbox, type InboxOptions } from './module'
export { createInboxApi, type InboxApi } from './api'
export { inboxMessages } from './messages'
export { option } from './options'
export { default as WxInboxPage } from './InboxPage.vue'
export { default as WxInboxFormEditor } from './FormEditorPage.vue'
export { default as WxInboxStatusesPage } from './StatusesPage.vue'
export { default as WxInboxFieldList } from './FieldList.vue'
export { FIELD_TYPES, STATUS_COLORS } from './types'
export type {
  FieldChoice,
  FieldInput,
  FieldOptions,
  FieldType,
  FormInput,
  FormOptions,
  InboxField,
  InboxForm,
  InboxRecipient,
  InboxStatus,
  Recipient,
  StatusColor,
  StatusInput,
} from './types'
