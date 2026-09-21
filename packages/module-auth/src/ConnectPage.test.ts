import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { WebxUI } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import ConnectPage from './ConnectPage.vue'

const URL_ = 'https://webx-demo.test/api/cms/mcp'

function panel(url: string = URL_) {
  const get = vi
    .fn()
    .mockResolvedValue({ data: [], meta: { scope: 'mine', can_see_everybody: false } })
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get, delete: vi.fn() },
    i18n,
    state: {
      // Where the address comes from: the server prints it, absolute, because it is pasted
      // into a program on another machine.
      manifest: { modules: [{ id: 'connect', meta: { url } }] },
      user: null,
      status: 'ready',
      error: null,
    },
    can: () => true,
  } as unknown as AdminContext

  return mount(ConnectPage, {
    global: {
      plugins: [WebxUI],
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      stubs: { RouterLink: true },
    },
  })
}

/** Reka's tab triggers listen for `mousedown` and `focus`; a click passes them by. */
async function open(wrapper: ReturnType<typeof panel>, label: string): Promise<void> {
  const tab = wrapper.findAll('.wx-tabs__tab').find((one) => one.text() === label)

  expect(tab, `no tab called ${label}`).toBeDefined()
  await tab!.trigger('mousedown')
  await flushPromises()
}

describe('WxConnectPage', () => {
  it('prints the address the server gave it, and builds every line out of that one address', async () => {
    const wrapper = panel()

    await flushPromises()

    expect(wrapper.text()).toContain(URL_)

    // The lines for the terminal live behind their own tab, and a tab is opened with
    // `mousedown` — Reka does not listen for a click on the trigger.
    await open(wrapper, 'Claude Code')

    // The server's name in a client's list is the site's own, not "webx" three times over.
    expect(wrapper.text()).toContain(`claude mcp add --transport http webx-demo ${URL_}`)

    await open(wrapper, 'Codex')

    expect(wrapper.text()).toContain('[mcp_servers.webx-demo]')
    expect(wrapper.text()).toContain(`url = "${URL_}"`)
  })

  it('hands Cursor and VS Code a link each, carrying the address and nothing secret', async () => {
    const wrapper = panel()

    await flushPromises()

    const links = wrapper.findAll('a').map((one) => one.attributes('href') ?? '')
    const cursor = links.find((href) => href.startsWith('cursor://'))
    const code = links.find((href) => href.startsWith('vscode:'))

    expect(cursor).toBeDefined()
    expect(code).toBeDefined()

    // Cursor takes base64 of the config, VS Code takes the JSON itself; both come back as
    // the address this panel printed.
    const config = new globalThis.URL(cursor!.replace('cursor://', 'https://')).searchParams.get(
      'config',
    )

    expect(JSON.parse(atob(config!))).toEqual({ url: URL_ })
    expect(JSON.parse(decodeURIComponent(code!.slice('vscode:mcp/install?'.length)))).toEqual({
      name: 'webx-demo',
      type: 'http',
      url: URL_,
    })
  })

  it('is still a page when the server said nothing, rather than one about "undefined"', async () => {
    const wrapper = panel('')

    await flushPromises()

    expect(wrapper.text()).toContain('Connect an agent')
    // Nothing to copy and nothing to install: the buttons say so rather than sending
    // somebody to an address that is not one.
    expect(wrapper.findAll('button').some((one) => one.classes('is-disabled'))).toBe(true)
  })
})
