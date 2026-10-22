import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import type * as Core from '@webx-ui/core'

const openLightbox = vi.fn()

vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<typeof Core>()),
  openLightbox: (...args: unknown[]) => openLightbox(...args),
}))

const { default: PreviewThumb } = await import('./PreviewThumb.vue')
const { useLightbox } = await import('./lightbox')

const items = [{ src: '/a.jpg', thumb: '/a-160.jpg' }, { src: '/b.jpg' }, { src: '/c.jpg' }]

beforeEach(() => openLightbox.mockReset())
afterEach(() => vi.restoreAllMocks())

describe('WxPreviewThumb', () => {
  it('opens the whole set on the picture that was clicked, with the panel’s words', async () => {
    const wrapper = mount(PreviewThumb, {
      props: { items, start: 1 },
      slots: { default: '<img src="/b-160.jpg" alt="" />' },
    })

    await wrapper.get('button').trigger('click')

    expect(openLightbox).toHaveBeenCalledTimes(1)

    const [given, options] = openLightbox.mock.calls[0]!

    expect(given).toEqual(items)
    // A test installs no dictionary, so the words are the keys.
    expect(options).toMatchObject({ start: 1, closeLabel: 'lightbox.close' })
    expect(options.counterText(2, 3)).toBe('lightbox.counter')
  })

  it('keeps its click to itself: a thumbnail in a row opens the picture, not the record', async () => {
    const row = vi.fn()
    const wrapper = mount(
      defineComponent(() => () => h('div', { onClick: row }, [h(PreviewThumb, { items })])),
    )

    await wrapper.get('button').trigger('click')

    expect(openLightbox).toHaveBeenCalledTimes(1)
    expect(row).not.toHaveBeenCalled()
  })

  it('says what it does, and does nothing with nothing to show', async () => {
    const wrapper = mount(PreviewThumb, { props: { items: [] } })

    expect(wrapper.get('button').attributes('aria-label')).toBe('lightbox.view')
    expect(wrapper.get('button').attributes('disabled')).toBeDefined()
  })
})

describe('useLightbox', () => {
  function withLightbox() {
    let lightbox!: ReturnType<typeof useLightbox>

    mount(
      defineComponent(() => {
        lightbox = useLightbox()

        return () => null
      }),
    )

    return lightbox
  }

  it('leaves a modified click to the browser, so a thumbnail that is a link opens in a tab', async () => {
    const lightbox = withLightbox()
    const event = new MouseEvent('click', { ctrlKey: true, cancelable: true })

    lightbox.click(event, items)
    await flushPromises()

    expect(openLightbox).not.toHaveBeenCalled()
    expect(event.defaultPrevented).toBe(false)
  })

  it('opens on a plain click instead of following the link', () => {
    const lightbox = withLightbox()
    const event = new MouseEvent('click', { cancelable: true })

    lightbox.click(event, items, 2)

    expect(openLightbox).toHaveBeenCalledWith(items, expect.objectContaining({ start: 2 }))
    expect(event.defaultPrevented).toBe(true)
  })

  it('opens on the last picture rather than past it, and not at all on none', () => {
    const lightbox = withLightbox()

    lightbox.open(items, 7)
    lightbox.open([])

    expect(openLightbox).toHaveBeenCalledTimes(1)
    expect(openLightbox.mock.calls[0]![1]).toMatchObject({ start: 2 })
  })
})
