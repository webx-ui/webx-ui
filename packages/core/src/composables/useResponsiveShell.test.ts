import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, ref, nextTick } from 'vue'
import { shellLayoutFor, useResponsiveShell, type ResponsiveShell } from './useResponsiveShell'

describe('shellLayoutFor', () => {
  it('gives the widest shape until something has been measured', () => {
    expect(shellLayoutFor(0, false)).toBe('sidebar')
    expect(shellLayoutFor(0, true)).toBe('rail')
  })

  it('reads the width before the preference', () => {
    expect(shellLayoutFor(1440, false)).toBe('sidebar')
    expect(shellLayoutFor(1440, true)).toBe('rail')
    expect(shellLayoutFor(800, false)).toBe('rail')
    expect(shellLayoutFor(500, false)).toBe('drawer')
  })

  it('takes breakpoints of its own', () => {
    expect(shellLayoutFor(700, false, { phone: 720 })).toBe('drawer')
    expect(shellLayoutFor(1100, false, { tablet: 1200 })).toBe('rail')
  })
})

/** A stand-in for the browser's observer: the test decides what width it reports. */
let emit: ((width: number) => void) | undefined

beforeEach(() => {
  vi.stubGlobal(
    'ResizeObserver',
    class {
      constructor(private callback: ResizeObserverCallback) {
        emit = (width: number) => {
          this.callback(
            [{ contentRect: { width } } as ResizeObserverEntry],
            this as unknown as ResizeObserver,
          )
        }
      }
      observe() {}
      unobserve() {}
      disconnect() {}
    },
  )
})

afterEach(() => {
  emit = undefined
  vi.unstubAllGlobals()
})

function mountShell(options = {}) {
  let shell!: ResponsiveShell

  const wrapper = mount(
    defineComponent({
      setup() {
        const el = ref<HTMLElement | null>(null)
        shell = useResponsiveShell(el, options)
        return () => h('div', { ref: el })
      },
    }),
  )

  return { wrapper, shell: () => shell }
}

describe('useResponsiveShell', () => {
  it('follows the width it is given', async () => {
    const { shell } = mountShell()

    emit?.(1440)
    await nextTick()
    expect(shell().layout.value).toBe('sidebar')
    expect(shell().collapsed.value).toBe(false)
    expect(shell().showAside.value).toBe(true)

    emit?.(900)
    await nextTick()
    expect(shell().layout.value).toBe('rail')
    expect(shell().collapsed.value).toBe(true)
    expect(shell().showAside.value).toBe(true)

    emit?.(480)
    await nextTick()
    expect(shell().layout.value).toBe('drawer')
    expect(shell().showAside.value).toBe(false)
  })

  it('collapses the sidebar where there is room for one', async () => {
    const { shell } = mountShell()

    emit?.(1440)
    await nextTick()

    shell().toggle()
    expect(shell().layout.value).toBe('rail')
    expect(shell().drawerOpen.value).toBe(false)

    shell().toggle()
    expect(shell().layout.value).toBe('sidebar')
  })

  it('opens the drawer where there is not', async () => {
    const { shell } = mountShell()

    emit?.(800)
    await nextTick()

    // On a tablet the button cannot collapse a rail that the width already forced.
    shell().toggle()
    expect(shell().drawerOpen.value).toBe(true)
    expect(shell().layout.value).toBe('rail')

    shell().close()
    expect(shell().drawerOpen.value).toBe(false)
  })

  it('keeps the drawer open until the menu has a place on the page again', async () => {
    const { shell } = mountShell()

    emit?.(480)
    await nextTick()
    shell().toggle()
    expect(shell().drawerOpen.value).toBe(true)

    // Still no room for the menu itself, so the drawer stands.
    emit?.(800)
    await nextTick()
    expect(shell().drawerOpen.value).toBe(true)

    emit?.(1440)
    await nextTick()
    expect(shell().drawerOpen.value).toBe(false)
  })

  it('can start collapsed', async () => {
    const { shell } = mountShell({ collapsed: true })

    emit?.(1440)
    await nextTick()

    expect(shell().layout.value).toBe('rail')
  })
})
