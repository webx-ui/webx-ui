import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import { nextTick } from 'vue'
import WxToaster from './Toaster.vue'
import { removeToast, toast, toastQueue } from '../../composables/useToast'

let wrapper: VueWrapper | undefined

beforeEach(() => {
  for (const item of [...toastQueue()]) removeToast(item.id)
  vi.useFakeTimers()
  wrapper = mount(WxToaster, { attachTo: document.body })
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = undefined
  for (const item of [...toastQueue()]) removeToast(item.id)
  vi.useRealTimers()
  document.body.innerHTML = ''
})

/** Lets Reka start the toast's timer, then moves the clock. */
async function wait(ms: number) {
  await nextTick()
  await nextTick()
  vi.advanceTimersByTime(ms)
  await nextTick()
}

function viewport(): HTMLElement {
  return document.querySelector('.wx-toaster') as HTMLElement
}

describe('WxToaster', () => {
  it('lets a success go after five seconds', async () => {
    toast.success('Saved')

    await wait(4900)
    expect(toastQueue()[0]?.open).toBe(true)

    await wait(200)
    expect(toastQueue()[0]?.open).toBe(false)
  })

  it('gives an error eight seconds', async () => {
    toast.danger('Could not save')

    await wait(7900)
    expect(toastQueue()[0]?.open).toBe(true)

    await wait(200)
    expect(toastQueue()[0]?.open).toBe(false)
  })

  it('holds it while the pointer is over the toasts', async () => {
    toast.success('Saved')
    await wait(1000)

    viewport().dispatchEvent(new Event('pointermove', { bubbles: true }))
    await wait(10_000)
    expect(toastQueue()[0]?.open).toBe(true)

    // The count goes on from where it stopped, not from the start.
    viewport().dispatchEvent(new Event('pointerleave'))
    await wait(4100)
    expect(toastQueue()[0]?.open).toBe(false)
  })

  it('keeps a persistent one until it is dismissed', async () => {
    toast.danger('Copy this by hand', { persistent: true })

    await wait(60_000)
    expect(toastQueue()[0]?.open).toBe(true)
  })
})
