import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { useBodyKeys } from './keys'

function press(target: EventTarget): void {
  target.dispatchEvent(new KeyboardEvent('keydown', { key: 's', ctrlKey: true, bubbles: true }))
}

describe('useBodyKeys', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('passes on a key from <body>, not one from something else, and stops when unmounted', () => {
    const handler = vi.fn()
    const wrapper = mount(
      defineComponent({
        setup() {
          useBodyKeys(handler)
          return () => h('div')
        },
      }),
      { attachTo: document.body },
    )

    const elsewhere = document.body.appendChild(document.createElement('input'))

    press(document.body)
    expect(handler).toHaveBeenCalledTimes(1)

    // A dialog, a menu, a field of another form: theirs.
    press(elsewhere)
    expect(handler).toHaveBeenCalledTimes(1)

    wrapper.unmount()
    press(document.body)
    expect(handler).toHaveBeenCalledTimes(1)
  })
})
