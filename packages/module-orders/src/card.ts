import { inject, provide, type InjectionKey, type Ref } from 'vue'
import type { OrderCardData } from './types'

/**
 * What the open card shares with the node types on it (`wx-orders-status`, `wx-orders-items`,
 * `wx-orders-timeline`, `wx-orders-summary`, `wx-orders-notes`): the order as the server last answered, and what
 * its actions do — the node draws, the page asks the server.
 */
export interface OrderCardContext {
  order: Ref<OrderCardData | null>
  busy: Ref<boolean>
  changeStatus(): Promise<void>
  mark(milestone: 'paid' | 'received'): Promise<void>
  cancel(): Promise<void>
  refund(): Promise<void>
  /** Reverse a refund record made by mistake (decision 98), then offer the right one. */
  reverseRefund(refundId: number): Promise<void>
  /** Keep a note for whoever picks the order up next; true once it is kept. */
  note(body: string): Promise<boolean>
  /** Pin the standing note over the feed, or unpin it with null (decision 91); true once kept. */
  pin(text: string | null): Promise<boolean>
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
