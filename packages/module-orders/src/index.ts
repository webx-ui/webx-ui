import { defineAsyncComponent } from 'vue'

export { orders, type OrdersOptions } from './module'
export { ordersMessages } from './messages'
export { createOrdersApi, orderSearch, type OrdersApi } from './api'
export { useOrderCard, type OrderCardContext } from './card'
export type * from './types'
export const WxOrdersPage = defineAsyncComponent(() => import('./OrdersPage.vue'))
export const WxOrderPage = defineAsyncComponent(() => import('./OrderPage.vue'))
