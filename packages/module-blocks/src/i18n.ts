import { useAdmin, type I18n } from '@webx-ui/module-admin'
import { blocksMessages } from './messages'

const seeded = new WeakSet<object>()

/**
 * Puts this package's English under a panel's dictionary, once per panel. For what runs outside
 * a component's setup — the label of a tab on the panel on the site is asked for by the frame.
 */
export function seedBlocksMessages(i18n: I18n): void {
  if (!seeded.has(i18n)) {
    i18n.defaults('webx-blocks', blocksMessages)
    seeded.add(i18n)
  }
}

/** Puts this package's English under the panel's dictionary, once per panel. */
export function useBlocksMessages(): void {
  try {
    seedBlocksMessages(useAdmin().i18n)
  } catch {
    // Outside a panel there is no dictionary to seed, and `useTranslate` falls back on its own.
  }
}
