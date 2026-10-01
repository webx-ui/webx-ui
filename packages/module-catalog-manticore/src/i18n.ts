import { useAdmin } from '@webx-ui/module-admin'
import { catalogManticoreMessages } from './messages'

const seeded = new WeakSet<object>()

/**
 * Put this package's English under the panel's dictionary, once per panel — so a page opened
 * before the server's dictionary arrives, or without one, still has words.
 */
export function useCatalogManticoreMessages(): void {
  try {
    const { i18n } = useAdmin()

    if (!seeded.has(i18n)) {
      i18n.defaults('webx-catalog-manticore', catalogManticoreMessages)
      seeded.add(i18n)
    }
  } catch {
    // Outside a panel there is no dictionary to seed, and `useTranslate` falls back on its own.
  }
}
