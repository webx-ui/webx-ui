import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { localesKey, modalKey } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import MenuItemDialog from './MenuItemDialog.vue'
import type { MenuItemInput, MenuItemRow, MenuRow } from './types'

const menu: MenuRow = {
  id: 1,
  key: 'header',
  title: 'Header',
  declared: true,
  items_count: 1,
  variants: ['link', 'button'],
  cache: { enabled: true, built_at: null },
  can: { rename: false, delete: false },
}

const item: MenuItemRow = {
  id: 7,
  parent_id: null,
  depth: 0,
  title: { en: 'Speak to Katia' },
  label: 'Speak to Katia',
  target: 'url',
  entity_type: null,
  entity_id: null,
  url: 'https://example.com/book',
  hash: 'slot',
  href: 'https://example.com/book#slot',
  variant: 'button',
  is_heading: false,
  new_tab: true,
  rel: ['sponsored'],
  locales: ['en'],
  visible: true,
  available: true,
  resolved: null,
  children: [],
}

// The dialog is teleported, so it would outlive its wrapper and leak into the next test.
enableAutoUnmount(afterEach)

/** The one button in the dialog with this word on it — it is drawn into `<body>`, not the wrapper. */
function button(word: string): HTMLElement {
  const found = [...document.body.querySelectorAll<HTMLElement>('.wx-dialog button')].find(
    (candidate) => candidate.textContent?.trim() === word,
  )

  if (found === undefined) throw new Error(`No “${word}” in the dialog.`)

  return found
}

function open(row: MenuItemRow | null) {
  const resolve = vi.fn()

  const i18n = createI18n()
  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: {
      get: vi.fn().mockResolvedValue({ data: [] }),
      post: vi.fn().mockResolvedValue({ data: [] }),
    },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  return {
    resolve,
    wrapper: mount(MenuItemDialog, {
      props: { menu, item: row },
      attachTo: document.body,
      global: {
        provide: {
          [adminKey as symbol]: admin,
          [i18nKey as symbol]: i18n,
          [localesKey as symbol]: { list: ref([{ code: 'en' }]), active: ref('en') },
          [modalKey as symbol]: { isModal: true, open: ref(true), resolve, dismiss: vi.fn() },
        },
      },
    }),
  }
}

/**
 * What the dialog hands back. Where the link points is `WxLinkPicker`'s own business and is
 * tested with it; what is checked here is that the form opens on what was saved and gives it
 * back unchanged, which is the failure a form full of defaults produces silently.
 */
describe('WxMenuItemDialog', () => {
  it('opens on the item that was saved and hands it back as it was', async () => {
    const { resolve } = open(item)

    await flushPromises()

    button('Save').click()
    await flushPromises()

    const sent = resolve.mock.calls[0]![0] as MenuItemInput

    expect(sent.title).toEqual({ en: 'Speak to Katia' })
    expect(sent.variant).toBe('button')
    expect(sent.locales).toEqual(['en'])
    expect(sent.visible).toBe(true)
    // The link travels as one value, the same one every link field in the panel holds.
    expect(sent.link).toEqual({
      target: 'url',
      entity_type: null,
      entity_id: null,
      url: 'https://example.com/book',
      hash: 'slot',
      new_tab: true,
      rel: ['sponsored'],
    })
  })

  it('starts a new item on an empty link and the first look the menu offers', async () => {
    const { resolve } = open(null)

    await flushPromises()

    button('Save').click()
    await flushPromises()

    const sent = resolve.mock.calls[0]![0] as MenuItemInput

    expect(sent.variant).toBe('link')
    expect(sent.is_heading).toBe(false)
    expect(sent.link.target).toBe('entity')
    expect(sent.link.entity_id).toBeNull()
  })
})
