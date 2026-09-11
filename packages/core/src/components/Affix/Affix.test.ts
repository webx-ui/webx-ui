import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxAffix from './Affix.vue'

/** A stand-in for the browser's observer: the test decides whether it is in view. */
let report: ((intersecting: boolean) => void) | undefined

beforeEach(() => {
  vi.stubGlobal(
    'IntersectionObserver',
    class {
      constructor(private callback: IntersectionObserverCallback) {
        report = (intersecting: boolean) => {
          this.callback(
            [{ isIntersecting: intersecting } as IntersectionObserverEntry],
            this as unknown as IntersectionObserver,
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
  report = undefined
  vi.unstubAllGlobals()
})

describe('WxAffix', () => {
  it('sticks with CSS rather than by measuring', () => {
    const wrapper = mount(WxAffix, { props: { offset: 16 }, slots: { default: 'Toolbar' } })

    // `position: sticky` has neither of the two bugs a `fixed` switch inherits.
    const style = wrapper.get('.wx-affix__content').attributes('style')
    expect(style).toContain('position: sticky')
    expect(style).toContain('top: 16px')
  })

  it('sticks to the bottom when asked', () => {
    const wrapper = mount(WxAffix, { props: { position: 'bottom', offset: 8 } })

    expect(wrapper.get('.wx-affix__content').attributes('style')).toContain('bottom: 8px')
  })

  it('reports sticking, and letting go', async () => {
    const wrapper = mount(WxAffix)

    report?.(false)
    await nextTick()
    expect(wrapper.emitted('change')?.at(-1)).toEqual([true])
    expect(wrapper.get('.wx-affix__content').classes()).toContain('is-stuck')

    report?.(true)
    await nextTick()
    expect(wrapper.emitted('change')?.at(-1)).toEqual([false])
  })

  it('says nothing twice about the same state', async () => {
    const wrapper = mount(WxAffix)

    report?.(false)
    report?.(false)
    await nextTick()

    expect(wrapper.emitted('change')).toHaveLength(1)
  })

  it('goes back into the flow when disabled', () => {
    const wrapper = mount(WxAffix, { props: { disabled: true } })

    expect(wrapper.get('.wx-affix__content').attributes('style')).toBeUndefined()
    expect(wrapper.find('.wx-affix__sentinel').exists()).toBe(false)
  })

  it('hands the stuck state to its slot', () => {
    const wrapper = mount(WxAffix, {
      slots: { default: '<template #default="{ stuck }">{{ stuck }}</template>' },
    })

    expect(wrapper.text()).toBe('false')
  })
})
