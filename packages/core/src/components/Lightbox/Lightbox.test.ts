import { afterEach, describe, expect, it } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { defineComponent, nextTick } from 'vue'
import WxLightbox from './Lightbox.vue'
import WxImage from '../Image/Image.vue'
import WxImageGroup from '../ImageGroup/ImageGroup.vue'
import { youTubeId } from './items'
import { openLightbox } from '../../composables/lightbox'
import type { LightboxSource } from './types'

// The gallery is teleported, so it would outlive its wrapper and leak into the next test.
enableAutoUnmount(afterEach)

const PHOTOS: LightboxSource[] = [
  { src: '/a.jpg', alt: 'First' },
  { src: '/b.jpg', alt: 'Second', caption: 'The second one' },
  { src: '/c.jpg', alt: 'Third', original: '/c-full.jpg' },
]

function factory(props: Record<string, unknown> = {}) {
  return mount(WxLightbox, {
    props: { items: PHOTOS, open: true, ...props },
    attachTo: document.body,
  })
}

function $<T extends HTMLElement = HTMLElement>(selector: string) {
  return document.body.querySelector<T>(selector)
}

function $$(selector: string) {
  return [...document.body.querySelectorAll<HTMLElement>(selector)]
}

function counterText() {
  return $('.wx-lightbox__counter')?.textContent?.trim()
}

function key(name: string) {
  $('.wx-lightbox__frame')?.dispatchEvent(
    new KeyboardEvent('keydown', { key: name, bubbles: true }),
  )
}

function pointer(target: EventTarget, type: string, x: number, y: number, id = 1) {
  target.dispatchEvent(
    new PointerEvent(type, {
      bubbles: true,
      clientX: x,
      clientY: y,
      button: 0,
      pointerId: id,
      pointerType: 'touch',
    }),
  )
}

describe('WxLightbox', () => {
  it('shows nothing while closed', async () => {
    factory({ open: false })
    await nextTick()

    expect($('.wx-lightbox')).toBeNull()
  })

  it('shows the item it was opened on, with a counter and a caption', async () => {
    factory({ index: 1 })
    await nextTick()

    expect($<HTMLImageElement>('.wx-lightbox__picture')?.getAttribute('src')).toBe('/b.jpg')
    expect(counterText()).toBe('2 of 3')
    expect($('.wx-lightbox__caption')?.textContent?.trim()).toBe('The second one')
  })

  it('falls back to alt for the caption', async () => {
    factory({ index: 0 })
    await nextTick()

    expect($('.wx-lightbox__caption')?.textContent?.trim()).toBe('First')
  })

  it('moves with the arrows and stops at the ends', async () => {
    const wrapper = factory({ index: 0 })
    await nextTick()

    const prev = $<HTMLButtonElement>('.wx-lightbox__arrow--prev')!
    const next = $<HTMLButtonElement>('.wx-lightbox__arrow--next')!
    expect(prev.disabled).toBe(true)

    next.click()
    await nextTick()
    expect(counterText()).toBe('2 of 3')
    expect(wrapper.emitted('update:index')?.at(-1)).toEqual([1])
    expect(wrapper.emitted('change')?.at(-1)).toEqual([1])

    next.click()
    await nextTick()
    expect(next.disabled).toBe(true)
  })

  it('goes round with loop', async () => {
    factory({ index: 2, loop: true })
    await nextTick()

    $<HTMLButtonElement>('.wx-lightbox__arrow--next')!.click()
    await nextTick()

    expect(counterText()).toBe('1 of 3')
  })

  it('answers the keyboard', async () => {
    factory({ index: 0 })
    await nextTick()

    key('ArrowRight')
    await nextTick()
    expect(counterText()).toBe('2 of 3')

    key('End')
    await nextTick()
    expect(counterText()).toBe('3 of 3')

    key('ArrowLeft')
    await nextTick()
    expect(counterText()).toBe('2 of 3')

    key('Home')
    await nextTick()
    expect(counterText()).toBe('1 of 3')
  })

  it('is closed by its button', async () => {
    const wrapper = factory()
    await nextTick()

    $$('.wx-lightbox__tool').at(-1)!.click()
    await nextTick()

    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
  })

  it('jumps from the strip of thumbnails', async () => {
    const wrapper = factory({ index: 0 })
    await nextTick()

    const thumbs = $$('.wx-lightbox__thumb')
    expect(thumbs).toHaveLength(3)
    expect(thumbs[0]?.getAttribute('aria-current')).toBe('true')

    thumbs[2]!.click()
    await nextTick()

    expect(counterText()).toBe('3 of 3')
    expect(wrapper.emitted('change')?.at(-1)).toEqual([2])
  })

  it('has no strip, arrows or counter for one picture', async () => {
    factory({ items: ['/only.jpg'] })
    await nextTick()

    expect($('.wx-lightbox__picture')).not.toBeNull()
    expect($('.wx-lightbox__thumbs')).toBeNull()
    expect($('.wx-lightbox__arrow')).toBeNull()
    expect($('.wx-lightbox__counter')).toBeNull()
  })

  it('leaves the strip out when asked to', async () => {
    factory({ thumbnails: false })
    await nextTick()

    expect($('.wx-lightbox__thumbs')).toBeNull()
  })

  it('links to the original, which is the picture unless said otherwise', async () => {
    factory({ index: 2 })
    await nextTick()

    expect($('a.wx-lightbox__tool')?.getAttribute('href')).toBe('/c-full.jpg')
    expect($('a.wx-lightbox__tool')?.getAttribute('rel')).toContain('noopener')

    key('Home')
    await nextTick()
    expect($('a.wx-lightbox__tool')?.getAttribute('href')).toBe('/a.jpg')
  })

  it('takes its words as props', async () => {
    factory({
      index: 1,
      counterText: (index: number, total: number) => `${index}/${total}`,
      nextLabel: 'Weiter',
    })
    await nextTick()

    expect(counterText()).toBe('2/3')
    expect($('.wx-lightbox__arrow--next')?.getAttribute('aria-label')).toBe('Weiter')
  })

  it('swipes to the next and the previous', async () => {
    factory({ index: 1 })
    await nextTick()

    const stage = $('.wx-lightbox__stage')!
    pointer(stage, 'pointerdown', 300, 200)
    pointer(stage, 'pointermove', 180, 205)
    pointer(stage, 'pointerup', 180, 205)
    await nextTick()
    expect(counterText()).toBe('3 of 3')

    pointer(stage, 'pointerdown', 100, 200)
    pointer(stage, 'pointermove', 220, 200)
    pointer(stage, 'pointerup', 220, 200)
    await nextTick()
    expect(counterText()).toBe('2 of 3')
  })

  it('closes on a swipe down', async () => {
    const wrapper = factory()
    await nextTick()

    const stage = $('.wx-lightbox__stage')!
    pointer(stage, 'pointerdown', 200, 100)
    pointer(stage, 'pointermove', 205, 300)
    pointer(stage, 'pointerup', 205, 300)
    await nextTick()

    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
  })

  it('shows a video as its poster until play is pressed, then a YouTube player', async () => {
    factory({
      items: [{ src: '/poster.jpg', video: 'https://youtu.be/dQw4w9WgXcQ' }],
    })
    await nextTick()

    expect($('iframe')).toBeNull()
    expect($<HTMLImageElement>('.wx-lightbox__picture')?.getAttribute('src')).toBe('/poster.jpg')

    $('.wx-lightbox__play')!.click()
    await nextTick()

    const src = $('iframe')?.getAttribute('src') ?? ''
    expect(src).toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ')
    expect(src).toContain('autoplay=1')
  })

  it('plays a file in a video element, and stops it on the next item', async () => {
    factory({
      items: [{ src: '/poster.jpg', video: '/clip.mp4' }, '/b.jpg'],
    })
    await nextTick()

    $('.wx-lightbox__play')!.click()
    await nextTick()
    expect($('video')?.getAttribute('src')).toBe('/clip.mp4')

    $<HTMLButtonElement>('.wx-lightbox__arrow--next')!.click()
    await nextTick()
    expect($('video')).toBeNull()
  })

  it('offers no zoom on a video', async () => {
    factory({ items: [{ src: '/poster.jpg', video: { src: '/clip.webm' } }] })
    await nextTick()

    expect($('.wx-lightbox__tool[aria-label="Zoom in"]')).toBeNull()
  })
})

describe('youTubeId', () => {
  it('reads every form of the address', () => {
    expect(youTubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10')).toBe('dQw4w9WgXcQ')
    expect(youTubeId('https://youtu.be/dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ')
    expect(youTubeId('https://www.youtube.com/shorts/dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ')
    expect(youTubeId('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')).toBe('dQw4w9WgXcQ')
    expect(youTubeId('https://example.com/watch?v=dQw4w9WgXcQ')).toBeNull()
    expect(youTubeId('/clip.mp4')).toBeNull()
  })
})

describe('openLightbox', () => {
  it('opens from code and settles when closed', async () => {
    const shown = openLightbox(PHOTOS, 1, { duration: 0 })
    await nextTick()

    expect(counterText()).toBe('2 of 3')

    $$('.wx-lightbox__tool').at(-1)!.click()
    await expect(shown).resolves.toBeUndefined()
    await flushPromises()
  })

  it('takes props alongside the start', async () => {
    const shown = openLightbox(PHOTOS, { start: 2, loop: true }, { duration: 0 })
    await nextTick()

    $<HTMLButtonElement>('.wx-lightbox__arrow--next')!.click()
    await nextTick()
    expect(counterText()).toBe('1 of 3')

    shown.close()
    await shown
    await flushPromises()
  })
})

describe('WxImage preview', () => {
  async function loadAll() {
    for (const img of $$('.wx-image__img')) img.dispatchEvent(new Event('load'))
    await nextTick()
  }

  it('opens the one picture when it is alone', async () => {
    mount(WxImage, { props: { src: '/a.jpg', alt: 'A', preview: true }, attachTo: document.body })
    await loadAll()

    $('.wx-image__preview')!.click()
    await nextTick()

    expect($<HTMLImageElement>('.wx-lightbox__picture')?.getAttribute('src')).toBe('/a.jpg')
    expect($('.wx-lightbox__counter')).toBeNull()
  })

  it('opens a whole list from a cover, on the cover', async () => {
    mount(WxImage, {
      props: { src: '/b.jpg', preview: true, previewList: ['/a.jpg', '/b.jpg', '/c.jpg'] },
      attachTo: document.body,
    })
    await loadAll()

    $('.wx-image__preview')!.click()
    await nextTick()

    expect(counterText()).toBe('2 of 3')
  })

  it('opens the group it is in, in the order of the page', async () => {
    const Host = defineComponent({
      components: { WxImage, WxImageGroup },
      template: `
        <wx-image-group class="thumbs" loop>
          <wx-image src="/a.jpg" alt="A" preview />
          <div><wx-image src="/b.jpg" alt="B" preview /></div>
          <wx-image src="/c.jpg" alt="C" preview />
          <wx-image src="/not-in.jpg" alt="No preview" />
        </wx-image-group>
      `,
    })

    const wrapper = mount(Host, { attachTo: document.body })
    await loadAll()

    expect(wrapper.get('.wx-image-group').classes()).toContain('thumbs')

    $$('.wx-image__preview')[1]!.click()
    await nextTick()

    expect(counterText()).toBe('2 of 3')
    expect($('.wx-lightbox__caption')?.textContent?.trim()).toBe('B')

    /* `loop` went through to the lightbox. */
    key('End')
    await nextTick()
    $<HTMLButtonElement>('.wx-lightbox__arrow--next')!.click()
    await nextTick()
    expect(counterText()).toBe('1 of 3')
  })
})
