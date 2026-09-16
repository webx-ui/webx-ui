import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick } from 'vue'
import { openMediaFiles, openMediaPicker } from './openMediaPicker'
import type { MediaFile } from './types'

/*
 * The manager is stood in for: it is the library, and everything it does is a request. What is
 * under test is the dialog around it — what it hands down, and what it hands back.
 */
const seen = vi.hoisted(() => ({ props: {} as Record<string, unknown> }))

vi.mock('./MediaManager.vue', () => ({
  default: defineComponent({
    name: 'MediaManagerStub',
    props: {
      picking: Boolean,
      multiple: Boolean,
      accept: { type: null, default: null },
      max: { type: null, default: null },
    },
    emits: ['pick', 'selection'],
    setup(props, { emit }) {
      seen.props = props

      return () =>
        h('div', { class: 'manager' }, [
          h('button', { class: 'choose', onClick: () => emit('selection', files(2)) }, 'Choose'),
          h('button', { class: 'open', onClick: () => emit('pick', files(1)[0]) }, 'Open'),
        ])
    },
  }),
}))

function files(count: number): MediaFile[] {
  return Array.from({ length: count }, (_, index) => ({ id: index + 1 }) as MediaFile)
}

function click(selector: string): void {
  document.querySelector<HTMLElement>(selector)?.click()
}

function button(): HTMLButtonElement | null {
  return document.querySelector<HTMLButtonElement>('.wx-media-picker__confirm')
}

/** A modal takes its own node away on a timer; leaving one behind fails the next file. */
afterEach(async () => {
  const deadline = Date.now() + 2000

  while (document.querySelectorAll('.wx-modal-host').length > 0 && Date.now() < deadline) {
    await new Promise((resolve) => setTimeout(resolve, 10))
  }
})

describe('the picker that takes several files', () => {
  it('hands the manager the limit and its own selection back', async () => {
    const chosen = openMediaFiles({ max: 2 })
    await nextTick()

    expect(seen.props.multiple).toBe(true)
    expect(seen.props.max).toBe(2)

    // Nothing marked, nothing to hand back: the button is the only way out with an answer.
    expect(button()?.disabled).toBe(true)

    click('.choose')
    await nextTick()

    expect(button()?.disabled).toBe(false)

    click('.wx-media-picker__confirm')

    expect(await chosen).toHaveLength(2)
  })

  it('leaves the one-file picker with one file and no button', async () => {
    const chosen = openMediaPicker()
    await nextTick()

    expect(seen.props.multiple).toBe(false)
    // A dialog that answers on a double-click has nothing to confirm.
    expect(button()).toBeNull()

    click('.open')

    expect(await chosen).toEqual({ id: 1 })
  })
})
