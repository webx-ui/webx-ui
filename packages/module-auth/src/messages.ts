import type { Messages } from '@webx-ui/admin'

/**
 * What this package says, in English.
 *
 * The same keys `webx-ui/module-auth` ships as `lang/en/*.php`. Kept here so a card placed by
 * hand, with no WebX UI server behind it, still has words — and so a key never reaches the
 * screen when a translation is missing.
 */
export const authMessages: Record<string, Messages> = {
  card: {
    email: 'Email',
    password: 'Password',
    remember: 'Stay signed in',
    submit: 'Sign in',
    'caps-lock': 'Caps Lock is on.',
    reveal: 'Show the password',
    hide: 'Hide the password',
    throttled: 'Too many attempts. Try again in :seconds s.',
    failed: 'Those details do not match an account.',
  },
  menu: {
    'sign-out': 'Sign out',
    language: 'Interface language',
  },
}
