import { useAdmin } from '@webx-ui/admin'
import { mediaMessages } from './messages'

const seeded = new WeakSet<object>()

/**
 * Put this package's English under the panel's dictionary, once per panel.
 *
 * The server's translations land on top of it when they arrive. What this prevents is the half
 * second — or the whole session, in a panel assembled without a server — where a screen shows
 * `manager.upload` instead of a word.
 */
export function useMediaMessages(): void {
  try {
    const { i18n } = useAdmin()

    if (!seeded.has(i18n)) {
      i18n.defaults('webx-media', mediaMessages)
      seeded.add(i18n)
    }
  } catch {
    // Outside a panel there is no dictionary to seed, and `useTranslate` falls back on its own.
  }
}
