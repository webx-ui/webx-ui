import { afterEach, describe, expect, it, vi } from 'vitest'

/**
 * The one question every tooltip asks before it opens.
 *
 * Fresh modules each time: the answer is cached for the whole application on purpose — one
 * query and one listener however many buttons are on the screen — so a test that wants a
 * different device has to start from a module that has not asked yet.
 */
async function ask(matches: boolean, listen = vi.fn()) {
  vi.resetModules()

  const query = { matches, addEventListener: listen }

  vi.stubGlobal(
    'matchMedia',
    vi.fn(() => query),
  )

  const { useHoverPointer } = await import('./useHoverPointer')

  return { hoverable: useHoverPointer(), query }
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('useHoverPointer', () => {
  it('says yes where there is a pointer that can hover', async () => {
    const { hoverable } = await ask(true)

    expect(hoverable.value).toBe(true)
  })

  it('says no on a touch screen', async () => {
    const { hoverable } = await ask(false)

    expect(hoverable.value).toBe(false)
  })

  it('changes its mind when a mouse arrives', async () => {
    const listeners: ((event: { matches: boolean }) => void)[] = []
    const { hoverable } = await ask(
      false,
      vi.fn((_name, fn) => listeners.push(fn)),
    )

    listeners[0]!({ matches: true })

    expect(hoverable.value).toBe(true)
  })

  it('assumes hover where the question cannot be asked at all', async () => {
    vi.resetModules()
    vi.stubGlobal('matchMedia', undefined)

    const { useHoverPointer } = await import('./useHoverPointer')

    expect(useHoverPointer().value).toBe(true)
  })
})
