import { defineAsyncComponent } from 'vue'
export { services, serviceCategoriesOptions, type ServicesOptions } from './module'
export { createServicesApi, SERVICES_API, type ServicesApi } from './api'
export { servicesMessages } from './messages'
export {
  provideServiceEditor,
  serviceEditorKey,
  useServiceEditor,
  type ServiceEditorContext,
} from './editor'
export const WxServicesPage = defineAsyncComponent(() => import('./ServicesPage.vue'))
export { default as WxServiceCreateDialog } from './ServiceCreateDialog.vue'
export const WxServiceEditorPage = defineAsyncComponent(() => import('./ServiceEditorPage.vue'))
export { default as WxServiceHistory } from './ServiceHistory.vue'
export type {
  ServiceCategoryRef,
  ServiceConflict,
  ServiceCover,
  ServiceDetail,
  ServiceInput,
  ServiceQuery,
  ServiceRow,
  ServiceSave,
  ServicesList,
  ServiceStatus,
  ServiceVersion,
} from './types'
