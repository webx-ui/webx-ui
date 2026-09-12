import { describe, expect, it } from 'vitest'
import { createI18n } from './i18n'

describe('i18n', () => {
  it('falls back to the strings a package ships when the server has not sent any', () => {
    // Which is what makes a package usable on its own: a story, a test, a card placed by
    // hand. Without it every label would be a key until a server answered.
    const i18n = createI18n()
    i18n.defaults('webx-admin', { shell: { retry: 'Try again' } })

    expect(i18n.scope('webx-admin')('shell.retry')).toBe('Try again')
  })

  it('prefers what the server sent', () => {
    const i18n = createI18n()
    i18n.defaults('webx-admin', { shell: { retry: 'Try again' } })
    i18n.load({ 'webx-admin': { shell: { retry: 'Повторить' } } }, 'ru')

    expect(i18n.scope('webx-admin')('shell.retry')).toBe('Повторить')
    expect(i18n.state.locale).toBe('ru')
  })

  it('keeps the built-in strings for lines the server did not send', () => {
    // The server merges per line, but it can only merge groups it has. A namespace it has
    // never heard of must not take the package's own words down with it.
    const i18n = createI18n()
    i18n.defaults('webx-admin', { shell: { retry: 'Try again', loading: 'Loading…' } })
    i18n.load({ 'webx-admin': { shell: { retry: 'Повторить' } } }, 'ru')

    expect(i18n.scope('webx-admin')('shell.loading')).toBe('Loading…')
  })

  it('shows the key rather than nothing when a line is missing everywhere', () => {
    // A key on screen is ugly and says which key. A blank label is ugly and says nothing.
    const i18n = createI18n()

    expect(i18n.scope('webx-admin')('shell.nowhere')).toBe('shell.nowhere')
  })

  it('fills :name placeholders the way the lang files write them', () => {
    const i18n = createI18n()
    i18n.defaults('webx-auth', { card: { throttled: 'Try again in :seconds s.' } })

    expect(i18n.scope('webx-auth')('card.throttled', { seconds: 30 })).toBe('Try again in 30 s.')
  })

  it('lets a scoped translator reach another namespace by naming it', () => {
    const i18n = createI18n()
    i18n.defaults('webx-admin', { nav: { menu: 'Menu' } })

    expect(i18n.scope('webx-auth')('webx-admin::nav.menu')).toBe('Menu')
  })

  it('reads a group nested as deeply as the file that produced it', () => {
    const i18n = createI18n()
    i18n.load({ 'webx-auth': { card: { errors: { invalid: 'No such account.' } } } }, 'en')

    expect(i18n.scope('webx-auth')('card.errors.invalid')).toBe('No such account.')
  })

  it('survives an answer that is not the shape it expected', () => {
    // Degrading to English beats a panel with no words in it.
    const i18n = createI18n()
    i18n.defaults('webx-admin', { shell: { retry: 'Try again' } })
    i18n.load(undefined as never, undefined as never)

    expect(i18n.scope('webx-admin')('shell.retry')).toBe('Try again')
  })
})
