import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { clearToasts, removeToast, toast, toastQueue } from './useToast'

/* The queue is module state on purpose, so every test starts by emptying it. */
beforeEach(() => {
  for (const item of [...toastQueue()]) removeToast(item.id)
  vi.useFakeTimers()
})

afterEach(() => vi.useRealTimers())

describe('useToast', () => {
  it('raises a toast from a plain string', () => {
    toast('Saved')

    expect(toastQueue()).toHaveLength(1)
    expect(toastQueue()[0].description).toBe('Saved')
    expect(toastQueue()[0].type).toBe('default')
  })

  it('takes an options bag as well', () => {
    toast({ title: 'Could not save', description: 'The server said no', type: 'danger' })

    expect(toastQueue()[0].title).toBe('Could not save')
    expect(toastQueue()[0].type).toBe('danger')
  })

  it('colours the shorthands', () => {
    toast.success('Done')
    toast.warning('Careful')
    toast.info('For your information')

    expect(toastQueue().map((item) => item.type)).toEqual(['success', 'warning', 'info'])
  })

  it('keeps an error until it is dismissed', () => {
    toast.danger('Broken')

    // An error that vanishes on its own is an error nobody read.
    expect(toastQueue()[0].duration).toBe(0)
  })

  it('lets a caller override what the shorthand chose', () => {
    toast.danger('Broken', { duration: 2000 })

    expect(toastQueue()[0].duration).toBe(2000)
  })

  it('hands back a handle that dismisses it', () => {
    const handle = toast('Working')
    expect(toastQueue()).toHaveLength(1)

    handle.dismiss()
    expect(toastQueue()[0].open).toBe(false)

    // Taken off the queue once it has had time to leave, animation or not.
    vi.advanceTimersByTime(300)
    expect(toastQueue()).toHaveLength(0)
  })

  it('ignores a second dismissal of the same toast', () => {
    const handle = toast('Working')
    handle.dismiss()
    handle.dismiss()

    vi.advanceTimersByTime(300)
    expect(toastQueue()).toHaveLength(0)
  })

  it('clears everything at once', () => {
    toast('One')
    toast('Two')
    toast('Three')

    clearToasts()
    vi.advanceTimersByTime(300)

    expect(toastQueue()).toHaveLength(0)
  })

  it('gives every toast an id of its own', () => {
    const first = toast('One')
    const second = toast('Two')

    expect(first.id).not.toBe(second.id)
  })
})
