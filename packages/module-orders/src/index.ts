import { defineAsyncComponent } from 'vue'

export { orders, type OrdersOptions } from './module'
export { ordersMessages } from './messages'
export const WxOrdersPage = defineAsyncComponent(() => import('./OrdersPage.vue'))
