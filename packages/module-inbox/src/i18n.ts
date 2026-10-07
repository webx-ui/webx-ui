import { inject } from 'vue'
import { localizedValue, useLocales } from '@webx-ui/core'
import { i18nKey, useAdmin } from '@webx-ui/module-admin'
import { inboxMessages } from './messages'
import type { InboxStatus } from './types'

const seeded = new WeakSet<object>()

/** Puts this package's English under the panel's dictionary, once per panel. */
export function useInboxMessages(): void {
  try {
    const { i18n } = useAdmin()

    if (!seeded.has(i18n)) {
      i18n.defaults('webx-inbox', inboxMessages)
      seeded.add(i18n)
    }
  } catch {
    // Outside a panel there is no dictionary to seed, and `useTranslate` falls back on its own.
  }
}

/**
 * A status's name in the language the panel is drawn in.
 *
 * A status is a panel word — "New", "In progress", "Done" — read by whoever works the inbox,
 * never by a visitor, so it follows the interface and not the content language picked for
 * editing; that one only stands in when the status has no line in the panel's language. Then
 * any line it has, then its key.
 */
export function useStatusName(): (status: InboxStatus) => string {
  const panel = inject(i18nKey, null)
  const locales = useLocales()

  return (status) => {
    const locale = panel?.state.locale
    const own = locale === undefined ? undefined : status.title[locale]

    return own || localizedValue(status.title, locales.active.value, status.key)
  }
}
