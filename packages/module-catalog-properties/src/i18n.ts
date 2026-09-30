import { useAdmin } from '@webx-ui/module-admin'
import { catalogPropertiesMessages } from './messages'

/** The server's dictionary for this package: `webx-catalog-properties::`. */
export const NAMESPACE = 'webx-catalog-properties'

const seeded = new WeakSet<object>()

/** Puts this package's English under the panel's dictionary, once per panel. */
export function useCatalogPropertiesMessages(): void {
  try {
    const { i18n } = useAdmin()

    if (!seeded.has(i18n)) {
      i18n.defaults(NAMESPACE, catalogPropertiesMessages)
      seeded.add(i18n)
    }
  } catch {
    // Outside a panel there is no dictionary to seed, and `useTranslate` falls back on its own.
  }
}
