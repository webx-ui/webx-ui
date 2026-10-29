import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { OrderCardData } from './types'

/**
 * What the open card shares with the node types on it (`wx-orders-status`, `wx-orders-items`,
 * `wx-orders-timeline`, `wx-orders-summary`): the order as the server last answered, and what
 * its actions do — the node draws, the page asks the server.
 */
export interface OrderCardContext {
  order: Ref<OrderCardData | null>
  busy: Ref<boolean>
  changeStatus(): Promise<void>
  mark(milestone: 'paid' | 'received'): Promise<void>
  cancel(): Promise<void>
  refund(): Promise<void>
  /** Link the order to a visitor, or to another one (§13.3). */
  linkVisitor(): Promise<void>
  unlinkVisitor(): Promise<void>
}

export const orderCardKey: InjectionKey<OrderCardContext> = Symbol('webx-orders-card')

export function provideOrderCard(context: OrderCardContext): void {
  provide(orderCardKey, context)
}

/** Outside a card — the screen's preview, a project's page — there is nothing to show. */
export function useOrderCard(): OrderCardContext | null {
  return inject(orderCardKey, null)
}
