import { inject, provide, type ComputedRef, type InjectionKey } from 'vue'

/** What the settings page tells its nodes: the trial number as the form now stands (§11.4). */
export interface OrdersSettingsContext {
  sample: ComputedRef<string>
}

const KEY: InjectionKey<OrdersSettingsContext> = Symbol('webx-orders-settings')

export function provideOrdersSettings(context: OrdersSettingsContext): void {
  provide(KEY, context)
}

export function useOrdersSettings(): OrdersSettingsContext | null {
  return inject(KEY, null)
}

const numeric = (value: unknown): number | null => {
  if (value === null || value === undefined || value === '') return null
  const number = Number(value)

  return Number.isFinite(number) ? Math.trunc(number) : null
}

/**
 * The number an order with this id gets in this format — prefix, start + id, padded — the way the
 * server's `OrderNumbers::compose()` makes it, so the screen shows it while it is typed.
 */
export function sampleNumber(values: Record<string, unknown>, id: number): string {
  const prefix = values['orders.number.prefix']
  const start = numeric(values['orders.number.start'])
  const pad = numeric(values['orders.number.pad'])
  const number = String((start === null ? 1000 : Math.max(0, start)) + id)

  return (
    (typeof prefix === 'string' ? prefix : '') +
    number.padStart(pad === null ? 0 : Math.min(20, Math.max(0, pad)), '0')
  )
}
