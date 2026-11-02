import { useAdmin, useTranslate, type Translate } from '@webx-ui/module-admin'
import { deliveryMessages } from './messages'

const seeded = new WeakSet<object>()

/** Puts this package's English under the panel's dictionary, once per panel. */
export function useDeliveryMessages(): void {
  try {
    const { i18n } = useAdmin()

    if (!seeded.has(i18n)) {
      i18n.defaults('webx-delivery', deliveryMessages)
      seeded.add(i18n)
    }
  } catch {
    // Outside a panel there is no dictionary to seed, and `useTranslate` falls back on its own.
  }
}

/** The section's words: `t('method.new')` reads `webx-delivery::panel.method.new`. */
export function useDeliveryWords(): Translate {
  useDeliveryMessages()

  const t = useTranslate('webx-delivery')

  return (key, params) => t(`panel.${key}`, params)
}
