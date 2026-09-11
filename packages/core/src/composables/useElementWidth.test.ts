import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick, ref, type Ref } from 'vue'
import { useElementWidth } from './useElementWidth'

/** A stand-in for the browser's observer: the test decides what width it reports. */
let emit: ((width: number) => void) | undefined
let disconnected = 0

beforeEach(() => {
  disconnected = 0
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
      disconnect() {
        disconnected += 1
      }
    },
  )
})

afterEach(() => {
  emit = undefined
  vi.unstubAllGlobals()
})

function mountWidth() {
  let width!: Ref<number>

  const wrapper = mount(
    defineComponent({
      setup() {
        const el = ref<HTMLElement | null>(null)
        width = useElementWidth(el)
        return () => h('div', { ref: el })
      },
    }),
  )

  return { wrapper, width: () => width }
}

describe('useElementWidth', () => {
  it('starts at zero — nothing has been measured yet', () => {
    const { width } = mountWidth()

    expect(width().value).toBe(0)
  })

  it('follows what the observer reports', async () => {
    const { width } = mountWidth()

    emit?.(920)
    await nextTick()
    expect(width().value).toBe(920)

    emit?.(480.4)
    await nextTick()
    expect(width().value).toBe(480)
  })

  it('lets the element go when the component does', () => {
    const { wrapper } = mountWidth()

    wrapper.unmount()

    expect(disconnected).toBeGreaterThan(0)
  })
})
