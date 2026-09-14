import type { Messages } from '@webx-ui/module-admin'

/**
 * What this package says, in English — the same keys `webx-ui/module-settings` ships as
 * `lang/en/*.php`, so a key never reaches the screen when a translation is missing.
 */
export const settingsMessages: Record<string, Messages> = {
  module: {
    title: 'Settings',
  },
  page: {
    save: 'Save',
    saved: 'Settings saved.',
    failed: 'Some values were not accepted. Check the highlighted fields.',
  },
}
