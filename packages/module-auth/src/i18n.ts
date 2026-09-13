import { useAdmin } from '@webx-ui/admin'
import { authMessages } from './messages'

const seeded = new WeakSet<object>()

/**
 * Put this package's English under the panel's dictionary, once per panel.
 *
 * The plugin does this too, but a panel can use the administrators screen — or just the picker
 * — without installing the sign-in plugin at all, and a screen showing `admins.title` instead
 * of a word is not a thing to leave to chance.
 */
export function useAuthMessages(): void {
  try {
    const { i18n } = useAdmin()

    if (!seeded.has(i18n)) {
      i18n.defaults('webx-auth', authMessages)
      seeded.add(i18n)
    }
  } catch {
    // Outside a panel there is no dictionary to seed, and `useTranslate` falls back on its own.
  }
}
