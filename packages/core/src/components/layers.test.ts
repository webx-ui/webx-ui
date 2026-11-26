import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, mount } from '@vue/test-utils'
import { defineComponent, h, nextTick, ref } from 'vue'
import { openModal, useModal } from '../composables/useModal'
import WxDialog from './Dialog/Dialog.vue'
import WxPopover from './Popover/Popover.vue'

enableAutoUnmount(afterEach)

/* A modal mounts itself outside any wrapper, so nothing above takes it away between tests. */
const modals: { close: () => void }[] = []

afterEach(() => {
  modals.splice(0).forEach((modal) => modal.close())
  document.querySelectorAll('.wx-modal-host').forEach((host) => host.remove())
})

/** The z-index a component's stylesheet gives a selector, as written. */
function zIndexOf(component: string, selector: string): string | undefined {
  // `import.meta.url` under jsdom is an address of the dev server, not a path on the disk.
  const file = resolve(__dirname, component, `${component}.vue`)
  const source = readFileSync(file, 'utf8')
  const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const rule = new RegExp(`(?:^|\\n)${escaped}\\s*\\{([^}]*)\\}`).exec(source)

  return rule ? /z-index:\s*([^;]+);/.exec(rule[1])?.[1].trim() : undefined
}

/*
 * jsdom draws nothing, so the order on the screen is checked as the two things that make it:
 * every layer that opens over the page is on one z-index, and the one opened later is later in
 * the document. A layer on a z-index of its own ranks by kind — a popover over the dialog it
 * opened, which is how a link's file picker ended up under the link's panel.
 */
describe('the layers over the page', () => {
  it.each([
    ['Popover', '.wx-popover'],
    ['Dropdown', '.wx-dropdown'],
    ['Select', '.wx-select__content'],
    ['Dialog', '.wx-dialog__overlay'],
    ['Dialog', '.wx-dialog__viewport'],
    ['Drawer', '.wx-drawer__overlay'],
    ['Drawer', '.wx-drawer'],
    ['Lightbox', '.wx-lightbox__overlay'],
    ['Lightbox', '.wx-lightbox'],
  ])('%s puts %s on the one shared layer', (component, selector) => {
    expect(zIndexOf(component, selector)).toBe('var(--wx-z-index-popover)')
  })

  /* A dialog that answers with a file, the way the media picker does. */
  const Picker = defineComponent({
    name: 'Picker',
    setup() {
      const { open, resolve } = useModal<string>()

      return () =>
        h(
          WxDialog,
          { open: open.value, 'onUpdate:open': (value: boolean) => (open.value = value) },
          {
            default: () =>
              h('button', { class: 'choose', onClick: () => resolve('brochure.pdf') }, 'Choose'),
          },
        )
    },
  })

  function linkPanel() {
    const chosen = ref<string | null>(null)

    const wrapper = mount(
      defineComponent({
        setup() {
          const open = ref(true)

          async function pick() {
            const modal = openModal<string>(Picker)

            modals.push(modal)
            chosen.value = (await modal) ?? null
          }

          return () =>
            h(
              WxPopover,
              { open: open.value, 'onUpdate:open': (value: boolean) => (open.value = value) },
              {
                trigger: () => h('button', { class: 'field' }, 'Link'),
                default: () => [
                  h('button', { class: 'pick', onClick: pick }, 'Choose a file'),
                  h('span', { class: 'value' }, chosen.value ?? ''),
                ],
              },
            )
        },
      }),
      { attachTo: document.body },
    )

    return { wrapper, chosen }
  }

  async function settle() {
    for (let i = 0; i < 4; i++) await nextTick()
  }

  function escape() {
    document.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }),
    )
  }

  it('puts a dialog opened from a popover after the popover, so it covers it', async () => {
    linkPanel()
    await settle()

    document.querySelector<HTMLElement>('.pick')?.click()
    await settle()

    const popover = document.querySelector('.wx-popover')
    const overlay = document.querySelector('.wx-dialog__overlay')
    const dialog = document.querySelector('.wx-dialog__viewport')

    expect(popover).not.toBeNull()
    expect(overlay).not.toBeNull()
    // Later in the document on the same layer: the dimming covers the popover, the dialog both.
    expect(
      popover!.compareDocumentPosition(overlay!) & Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy()
    expect(
      overlay!.compareDocumentPosition(dialog!) & Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy()
  })

  it('closes only the dialog on Escape, and the popover stays', async () => {
    linkPanel()
    await settle()

    document.querySelector<HTMLElement>('.pick')?.click()
    await settle()

    escape()
    await settle()

    expect(document.querySelector('.wx-dialog')?.getAttribute('data-state')).not.toBe('open')
    expect(document.querySelector('.wx-popover')?.getAttribute('data-state')).toBe('open')
  })

  it('hands the choice to the popover, which is still open on it', async () => {
    const { chosen } = linkPanel()
    await settle()

    document.querySelector<HTMLElement>('.pick')?.click()
    await settle()

    document.querySelector<HTMLElement>('.choose')?.click()
    // The answer travels through the modal's promise and the caller's `await`, past a few ticks.
    await vi.waitFor(() => expect(chosen.value).toBe('brochure.pdf'))
    await settle()

    expect(document.querySelector('.wx-popover')?.getAttribute('data-state')).toBe('open')
    expect(document.querySelector('.wx-popover .value')?.textContent).toBe('brochure.pdf')
  })
})
