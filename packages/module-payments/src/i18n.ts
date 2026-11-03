import { useAdmin, useTranslate, type Translate } from '@webx-ui/module-admin'
import { paymentsMessages } from './messages'

const seeded = new WeakSet<object>()

/** Puts this package's English under the panel's dictionary, once per panel. */
export function usePaymentsMessages(): void {
  try {
    const { i18n } = useAdmin()

    if (!seeded.has(i18n)) {
      i18n.defaults('webx-payments', paymentsMessages)
      seeded.add(i18n)
    }
  } catch {
    // Outside a panel there is no dictionary to seed, and `useTranslate` falls back on its own.
  }
}

/** The section's words: `t('method.new')` reads `webx-payments::panel.method.new`. */
export function usePaymentsWords(): Translate {
  usePaymentsMessages()

  const t = useTranslate('webx-payments')

  return (key, params) => t(`panel.${key}`, params)
}

/** Any line of the dictionary by its group: `w('statuses.statuses.paid')`, `w('exchange.kinds.notify')`. */
export function usePaymentsLines(): Translate {
  usePaymentsMessages()

  return useTranslate('webx-payments')
}
