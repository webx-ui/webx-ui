import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick, reactive } from 'vue'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { WebxUI } from '@webx-ui/core'
import { routeLocationKey } from 'vue-router'
import { adminKey } from './admin'
import { createHelp, helpKey, type Help, type HelpPage } from './help'
import HelpPanel from './HelpPanel.vue'
import { createI18n, i18nKey } from './i18n'
import { adminMessages } from './messages'

const field = (id: string, label: string | null, body: string | null = `About ${id}`) => ({
  id,
  label,
  hint: null,
  required: false,
  body,
})

const PAGE: HelpPage = {
  key: 'a.form',
  title: 'Status',
  locale: 'en',
  about: 'Why **this** exists.',
  howto: [{ title: 'How to hide it', body: 'Switch it off.' }],
  groups: [
    { id: 'general', label: 'General', fields: [field('title', 'Title')] },
    { id: 'seo', label: 'SEO', fields: [field('slug', 'Address')] },
  ],
  guide: null,
}

const many = Array.from({ length: 13 }, (_, index) => field(`f${index}`, `Field ${index}`))

let wrapper: VueWrapper | null = null

async function draw(
  page: HelpPage | ((key: string) => Promise<HelpPage>),
  options: { locale?: string; route?: { meta: Record<string, unknown> } } = {},
) {
  const i18n = createI18n({ locale: options.locale ?? 'en' })
  i18n.defaults('webx-admin', adminMessages)

  const loadHelp = vi.fn(typeof page === 'function' ? page : async () => page)
  const state = { manifest: { help: ['a.form', 'b.form'] } }
  const help: Help = createHelp({ state } as never)
  const route = reactive(options.route ?? { meta: {} })

  wrapper = mount(HelpPanel, {
    global: {
      plugins: [WebxUI],
      provide: {
        [i18nKey as symbol]: i18n,
        [adminKey as symbol]: { i18n, loadHelp, state },
        [helpKey as symbol]: help,
        [routeLocationKey as symbol]: route,
      },
    },
    attachTo: document.body,
  })

  help.toggle('a.form', null)
  await flushPromises()
  await nextTick()

  return { help, loadHelp, route }
}

const panelText = () => document.querySelector('.wx-help-panel')?.textContent ?? ''

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  document.body.innerHTML = ''
})

describe('WxHelpPanel', () => {
  it('draws the page: why, how to, and the fields of the first tab', async () => {
    await draw(PAGE)

    expect(panelText()).toContain('Help: Status')
    expect(document.querySelector('.wx-help strong')?.textContent).toBe('this')
    expect(panelText()).toContain('How to hide it')
    expect(panelText()).toContain('About title')
    expect(panelText()).not.toContain('About slug')
  })

  it('shows a field with no label of its own by its description alone', async () => {
    await draw({ ...PAGE, groups: [{ id: '_screen', label: null, fields: [field('x', null)] }] })

    expect(document.querySelector('.wx-help-panel__label')).toBeNull()
    expect(panelText()).toContain('About x')
  })

  it('says so when the text is not in the language of the panel', async () => {
    await draw(PAGE, { locale: 'de' })
    expect(panelText()).toContain('Text in English')
  })

  it('says nothing about the language when the text is in it', async () => {
    await draw(PAGE)
    expect(panelText()).not.toContain('Text in English')
  })

  it('has no search up to twelve fields', async () => {
    await draw(PAGE)
    expect(document.querySelector('.wx-help-panel input')).toBeNull()
  })

  it('searches every tab at once', async () => {
    await draw({
      ...PAGE,
      groups: [
        { id: 'general', label: 'General', fields: many },
        { id: 'seo', label: 'SEO', fields: [field('slug', 'Address')] },
      ],
    })

    const input = document.querySelector<HTMLInputElement>('.wx-help-panel input')!
    input.value = 'address'
    input.dispatchEvent(new Event('input'))
    await nextTick()

    expect(panelText()).toContain('About slug')
    expect(panelText()).not.toContain('About f0')
  })

  it('offers the way to try again, and the second try draws the page', async () => {
    let attempts = 0

    await draw(async () => {
      attempts += 1
      if (attempts === 1) throw new Error('No')

      return PAGE
    })

    expect(document.querySelector('.wx-help-panel [role="alert"], .wx-alert')).not.toBeNull()

    document.querySelector<HTMLButtonElement>('.wx-help-panel .wx-alert button')?.click()
    await flushPromises()

    expect(panelText()).toContain('About title')
  })

  it('links the guide only when the server gives its address', async () => {
    await draw(PAGE)
    expect(document.querySelector('.wx-help-panel__guide')).toBeNull()
  })

  it('links the guide in a new tab', async () => {
    await draw({ ...PAGE, guide: 'https://example.com/guide' })

    const link = document.querySelector('.wx-help-panel__guide')
    expect(link?.getAttribute('target')).toBe('_blank')
    expect(link?.getAttribute('rel')).toBe('noopener')
  })

  it('follows the reader to a screen that has help', async () => {
    const { help, route } = await draw(PAGE)

    route.meta = { help: 'b.form' }
    await nextTick()

    expect([help.open.value, help.key.value]).toEqual([true, 'b.form'])
  })

  it('closes on a screen that has none', async () => {
    const { help, route } = await draw(PAGE, { route: { meta: { help: 'a.form' } } })

    route.meta = {}
    await nextTick()

    expect(help.open.value).toBe(false)
  })

  it('keeps the newer page when the older answer comes last', async () => {
    let finish: (page: HelpPage) => void = () => {}

    const { help } = await draw((key) =>
      key === 'a.form'
        ? new Promise<HelpPage>((resolve) => {
            finish = resolve
          })
        : Promise.resolve({ ...PAGE, key: 'b.form', title: 'Newer' }),
    )

    help.show('b.form')
    await flushPromises()
    finish({ ...PAGE, title: 'Older' })
    await flushPromises()

    expect(panelText()).toContain('Help: Newer')
  })
})
