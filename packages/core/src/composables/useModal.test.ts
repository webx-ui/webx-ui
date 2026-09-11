import { afterEach, describe, expect, it } from 'vitest'
import { createApp, defineComponent, h, inject, nextTick } from 'vue'
import { confirm } from './confirm'
import { connectModals, createModal, openModal, useModal } from './useModal'

/*
 * No Test Utils here: the whole point of these is that they mount themselves, outside
 * any wrapper, so the DOM is what there is to look at.
 */
const Browser = defineComponent({
  name: 'Browser',
  props: { multiple: Boolean },
  emits: ['select', 'cancel'],
  setup(props, { emit }) {
    return () =>
      h('div', { class: 'browser' }, [
        h('span', { class: 'mode' }, String(props.multiple)),
        h('button', { class: 'pick', onClick: () => emit('select', { id: 7 }) }, 'Pick'),
        h('button', { class: 'drop', onClick: () => emit('cancel') }, 'Cancel'),
      ])
  },
})

/** One that knows it is in a modal, and closes itself. */
const Aware = defineComponent({
  name: 'Aware',
  setup() {
    const { open, resolve, dismiss, isModal } = useModal<string>()
    return () =>
      h('div', { class: 'aware', 'data-open': String(open.value), 'data-modal': String(isModal) }, [
        h('button', { class: 'yes', onClick: () => resolve('yes') }, 'Yes'),
        h('button', { class: 'no', onClick: () => dismiss() }, 'No'),
      ])
  },
})

function click(selector: string) {
  document.querySelector<HTMLElement>(selector)?.click()
}

function hosts() {
  return document.querySelectorAll('.wx-modal-host').length
}

afterEach(() => {
  document.querySelectorAll('.wx-modal-host').forEach((host) => host.remove())
})

describe('openModal', () => {
  it('mounts the component and answers with what it emits', async () => {
    const answer = openModal<{ id: number }>(Browser, {
      props: { multiple: true },
      resolveOn: 'select',
      duration: 0,
    })

    expect(document.querySelector('.mode')?.textContent).toBe('true')

    click('.pick')

    expect(await answer).toEqual({ id: 7 })
  })

  it('answers with nothing when it is dismissed', async () => {
    const answer = openModal(Browser, { resolveOn: 'select', duration: 0 })

    click('.drop')

    expect(await answer).toBeUndefined()
  })

  it('takes the node away once the closing animation has had its time', async () => {
    const answer = openModal(Browser, { resolveOn: 'select', duration: 0 })
    expect(hosts()).toBe(1)

    click('.pick')
    await answer
    /* Settled already, still on the page. */
    expect(hosts()).toBe(1)

    await new Promise((resolve) => setTimeout(resolve, 0))
    expect(hosts()).toBe(0)
  })

  it('lets the component close itself, and tells it that it is in a modal', async () => {
    const answer = openModal<string>(Aware, { duration: 0 })

    expect(document.querySelector('.aware')?.getAttribute('data-modal')).toBe('true')
    expect(document.querySelector('.aware')?.getAttribute('data-open')).toBe('true')

    click('.yes')
    expect(await answer).toBe('yes')

    await nextTick()
    expect(document.querySelector('.aware')?.getAttribute('data-open')).toBe('false')
  })

  it('answers once, whatever happens after', async () => {
    const answer = openModal<string>(Aware, { duration: 0 })

    click('.yes')
    click('.no')

    expect(await answer).toBe('yes')
  })

  it('can be closed from outside', async () => {
    const answer = openModal<string>(Aware, { duration: 0 })

    answer.close('gone')

    expect(await answer).toBe('gone')
  })

  it('renders in the app it was connected to, so provides carry', async () => {
    const app = createApp({ render: () => null })
    app.provide('greeting', 'Доброго дня')
    connectModals(app)

    const Probe = defineComponent({
      setup() {
        const greeting = inject<string>('greeting', 'nothing')
        return () => h('button', { class: 'probe', onClick: () => undefined }, greeting)
      },
    })

    const answer = openModal(Probe, { duration: 0 })
    expect(document.querySelector('.probe')?.textContent).toBe('Доброго дня')

    answer.close()
    await answer
  })

  it('rejects rather than hanging when the component throws', async () => {
    const Broken = defineComponent({
      setup() {
        return () => {
          throw new Error('no')
        }
      },
    })

    await expect(openModal(Broken, { duration: 0 })).rejects.toThrow('no')
    expect(hosts()).toBe(0)
  })
})

describe('createModal', () => {
  it('wraps a component in a function that opens it', async () => {
    const browse = createModal<{ id: number }, { multiple?: boolean }>(Browser, {
      resolveOn: 'select',
      duration: 0,
      props: { multiple: true },
    })

    const answer = browse({ multiple: false })
    expect(document.querySelector('.mode')?.textContent).toBe('false')

    click('.pick')
    expect(await answer).toEqual({ id: 7 })
  })
})

describe('useModal', () => {
  it('is harmless in a component nobody opened', () => {
    const app = createApp(Aware)
    const root = document.createElement('div')
    document.body.append(root)
    app.mount(root)

    expect(document.querySelector('.aware')?.getAttribute('data-modal')).toBe('false')
    expect(() => click('.yes')).not.toThrow()

    app.unmount()
    root.remove()
  })
})

describe('confirm', () => {
  it('answers true when it is confirmed and false when it is not', async () => {
    const yes = confirm('Delete this product?')
    await nextTick()

    const buttons = document.querySelectorAll<HTMLElement>('.wx-dialog__foot button')
    expect(buttons).toHaveLength(2)
    buttons[1].click()
    expect(await yes).toBe(true)

    const no = confirm({ title: 'Delete?', tone: 'danger' })
    await nextTick()
    document.querySelectorAll<HTMLElement>('.wx-dialog__foot button')[0].click()
    expect(await no).toBe(false)
  })
})
