import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, ref, nextTick } from 'vue'
import { shellLayoutFor, useResponsiveShell, type ResponsiveShell } from './useResponsiveShell'

describe('shellLayoutFor', () => {
  it('gives the widest shape until something has been measured', () => {
    expect(shellLayoutFor(0, null)).toBe('sidebar')
    expect(shellLayoutFor(0, true)).toBe('rail')
  })

  it('lets the width choose when nothing has been asked for', () => {
    expect(shellLayoutFor(1440, null)).toBe('sidebar')
    expect(shellLayoutFor(800, null)).toBe('rail')
    expect(shellLayoutFor(500, null)).toBe('drawer')
  })

  it('lets the reader override the rail at any width that has room for one', () => {
    expect(shellLayoutFor(1440, true)).toBe('rail')
    expect(shellLayoutFor(800, false)).toBe('sidebar')
  })

  it('keeps the drawer absolute — a phone has nowhere to put a column', () => {
    expect(shellLayoutFor(500, false)).toBe('drawer')
  })

  it('takes breakpoints of its own', () => {
    expect(shellLayoutFor(700, null, { phone: 720 })).toBe('drawer')
    expect(shellLayoutFor(1100, null, { tablet: 1200 })).toBe('rail')
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

  it('expands the rail in place on a tablet rather than opening a drawer', async () => {
    const { shell } = mountShell()

    emit?.(800)
    await nextTick()
    expect(shell().layout.value).toBe('rail')

    // The width suggested the rail; the reader is still allowed to disagree with it.
    shell().toggle()
    expect(shell().layout.value).toBe('sidebar')
    expect(shell().drawerOpen.value).toBe(false)
  })

  it('holds a sidebar that was closed by hand at every width', async () => {
    const { shell } = mountShell()

    emit?.(1440)
    await nextTick()
    shell().toggle()
    expect(shell().layout.value).toBe('rail')

    emit?.(800)
    await nextTick()
    expect(shell().layout.value).toBe('rail')

    // Still shut on the way back: closing is a decision, not a reaction to a width.
    emit?.(1440)
    await nextTick()
    expect(shell().layout.value).toBe('rail')
  })

  it('lets an opened sidebar go back to following the width', async () => {
    const { shell } = mountShell()

    emit?.(800)
    await nextTick()
    shell().toggle()
    expect(shell().layout.value).toBe('sidebar')

    // "Open, here" was answered; a wider screen opens it anyway.
    emit?.(1440)
    await nextTick()
    expect(shell().layout.value).toBe('sidebar')

    // And a tablet collapses it again rather than holding the reader to that answer.
    emit?.(800)
    await nextTick()
    expect(shell().layout.value).toBe('rail')
  })

  it('opens the drawer only where the menu has left the page', async () => {
    const { shell } = mountShell()

    emit?.(480)
    await nextTick()
    expect(shell().layout.value).toBe('drawer')

    shell().toggle()
    expect(shell().drawerOpen.value).toBe(true)

    shell().close()
    expect(shell().drawerOpen.value).toBe(false)
  })

  it('closes the drawer once the menu is back on the page', async () => {
    const { shell } = mountShell()

    emit?.(480)
    await nextTick()
    shell().toggle()
    expect(shell().drawerOpen.value).toBe(true)

    emit?.(1440)
    await nextTick()

    expect(shell().drawerOpen.value).toBe(false)
    expect(shell().layout.value).toBe('sidebar')
  })

  it('can start collapsed', async () => {
    const { shell } = mountShell({ collapsed: true })

    emit?.(1440)
    await nextTick()

    expect(shell().layout.value).toBe('rail')
  })
})

describe('useResponsiveShell with persist', () => {
  beforeEach(() => {
    window.localStorage.clear()
  })

  it('remembers a sidebar that was closed by hand', async () => {
    const first = mountShell({ persist: 'test-shell' })

    emit?.(1440)
    await nextTick()
    first.shell().toggle()

    expect(window.localStorage.getItem('wx-shell:test-shell')).toBe('{"collapsed":true}')

    const second = mountShell({ persist: 'test-shell' })
    emit?.(1440)
    await nextTick()

    expect(second.shell().layout.value).toBe('rail')
  })

  it('forgets it as soon as it is opened again', async () => {
    window.localStorage.setItem('wx-shell:test-shell', '{"collapsed":true}')

    const { shell } = mountShell({ persist: 'test-shell' })
    emit?.(1440)
    await nextTick()
    expect(shell().layout.value).toBe('rail')

    shell().toggle()

    // An open sidebar is the default; only the closing is worth keeping.
    expect(window.localStorage.getItem('wx-shell:test-shell')).toBeNull()
  })

  it('keeps nothing without a key', async () => {
    const { shell } = mountShell()

    emit?.(1440)
    await nextTick()
    shell().toggle()

    expect(window.localStorage.length).toBe(0)
  })
})
