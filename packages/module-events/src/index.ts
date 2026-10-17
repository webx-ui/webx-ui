import { defineAsyncComponent } from 'vue'
export { events, eventCategoriesOptions, type EventsOptions } from './module'
export { createEventsApi, EVENTS_API, type EventsApi } from './api'
export { eventsMessages } from './messages'
export {
  provideEventEditor,
  eventEditorKey,
  useEventEditor,
  type EventEditorContext,
} from './editor'
export const WxEventsPage = defineAsyncComponent(() => import('./EventsPage.vue'))
export { default as WxEventCreateDialog } from './EventCreateDialog.vue'
export const WxEventEditorPage = defineAsyncComponent(() => import('./EventEditorPage.vue'))
export { default as WxEventHistory } from './EventHistory.vue'
export type {
  EventConflict,
  EventDetail,
  EventInput,
  EventQuery,
  EventRow,
  EventSave,
  EventsPage,
  EventStatus,
  EventTermRef,
  EventVersion,
  EventWhen,
} from './types'
