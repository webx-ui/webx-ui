import { beforeEach, describe, expect, it } from 'vitest'
import { createTheme } from './theme'

describe('createTheme', () => {
  beforeEach(() => {
    localStorage.clear()
    document.documentElement.removeAttribute('data-theme')
  })

  it('follows the machine until somebody chooses', () => {
    const theme = createTheme()

    expect(theme.state.preference).toBe('system')
    // Saying nothing is how "whatever the machine says" is said: the stylesheet follows the
    // media query for everything that is not pinned to light.
    expect(document.documentElement.hasAttribute('data-theme')).toBe(false)
  })

  it('paints a choice and remembers it in this browser', () => {
    const theme = createTheme()

    theme.set('dark')

    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')
    expect(theme.state.resolved).toBe('dark')
    expect(localStorage.getItem('webx.theme')).toBe('dark')
  })

  it('starts from what this browser remembers', () => {
    localStorage.setItem('webx.theme', 'light')

    expect(createTheme().state.preference).toBe('light')
    expect(document.documentElement.getAttribute('data-theme')).toBe('light')
  })

  it('takes up the choice stored against the administrator', () => {
    const theme = createTheme()

    theme.adopt('dark')

    expect(theme.state.preference).toBe('dark')
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')
  })

  // Null is an answer and undefined is not: one is somebody who chose to follow their
  // machine, the other is a server that has never heard of themes.
  it('reads null as the machine and undefined as no answer at all', () => {
    const theme = createTheme()

    theme.set('dark')
    theme.adopt(undefined)
    expect(theme.state.preference).toBe('dark')

    theme.adopt(null)
    expect(theme.state.preference).toBe('system')
    expect(document.documentElement.hasAttribute('data-theme')).toBe(false)
  })

  it('keeps nothing when asked to keep nothing', () => {
    const theme = createTheme({ storageKey: null })

    theme.set('dark')

    expect(localStorage.getItem('webx.theme')).toBeNull()
  })
})
