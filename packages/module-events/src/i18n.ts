import { useAdmin } from '@webx-ui/module-admin'
import { eventsMessages } from './messages'

const seeded = new WeakSet<object>()

/** Puts this package's English under the panel's dictionary, once per panel. */
export function useEventsMessages(): void {
  try {
    const { i18n } = useAdmin()

    if (!seeded.has(i18n)) {
      i18n.defaults('webx-events', eventsMessages)
      seeded.add(i18n)
    }
  } catch {
    // Outside a panel there is no dictionary to seed, and `useTranslate` falls back on its own.
  }
}
