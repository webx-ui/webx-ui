import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { WebxUI } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import AdminsPage from './AdminsPage.vue'

/** An empty page of whatever is asked for, with the filters the call log adds. */
const empty = {
  data: [],
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null },
  filters: { users: [], tools: [] },
}

function panel(permissions: string[], current: 'admins' | 'roles' | 'calls' = 'admins') {
  const get = vi
    .fn()
    .mockImplementation((url: string) =>
      Promise.resolve(url.endsWith('/roles') ? { data: [] } : empty),
    )
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    basePath: '/cms',
    http: { get },
    types: {},
    assetUrls: null,
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: (permission: string) => permissions.includes(permission),
  } as unknown as AdminContext

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/:all(.*)', component: { template: '<div />' } },
    ],
  })

  return {
    get,
    router,
    wrapper: mount(AdminsPage, {
      props: { base: '/admins', current },
      global: {
        plugins: [WebxUI, router],
        provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
      },
    }),
  }
}

describe('WxAdminsPage', () => {
  it('offers each view only to somebody who may see it, and no strip for one view', async () => {
    // Reading the list is not managing it and not auditing it; the roles are read with it.
    const { wrapper } = panel(['admins.view'])
    await flushPromises()

    expect(wrapper.findAll('.wx-tabs__tab').map((tab) => tab.text())).toEqual([
      'Administrators',
      'Roles',
    ])
    expect(wrapper.find('.wx-admin-list').exists()).toBe(true)

    // The log alone is one view, and the address of the people opens it instead of a 403.
    const { wrapper: auditor } = panel(['admins.audit'])
    await flushPromises()

    expect(auditor.find('.wx-tabs').exists()).toBe(false)
    expect(auditor.find('.wx-call-list').exists()).toBe(true)
    expect(auditor.find('.wx-admin-list').exists()).toBe(false)

    // Everybody's connections are the same question as everybody's accounts, and a key speaks
    // for somebody's account.
    const { wrapper: manager } = panel(['admins.manage'])
    await flushPromises()

    expect(manager.findAll('.wx-tabs__tab').map((tab) => tab.text())).toEqual([
      'Administrators',
      'Roles',
      'Connections',
      'Keys',
    ])
  })

  it('lets whoever edits roles add one, and shows the rest of them the list only', async () => {
    const { wrapper: editor } = panel(['roles.manage'], 'roles')
    await flushPromises()

    expect(editor.find('.wx-role-list').exists()).toBe(true)
    expect(editor.text()).toContain('Add')

    const { wrapper: reader } = panel(['admins.view'], 'roles')
    await flushPromises()

    expect(reader.find('.wx-role-list').exists()).toBe(true)
    expect(reader.text()).not.toContain('Add')

    // Somebody with none of it — an address typed by hand — is told so.
    const { wrapper: nobody } = panel([], 'roles')
    await flushPromises()

    expect(nobody.find('.wx-role-list').exists()).toBe(false)
    expect(nobody.find('.wx-result').exists()).toBe(true)
  })

  it('draws the log on its own address, and keeps `Add` for the people', async () => {
    const { wrapper, get, router } = panel(['admins.manage', 'admins.audit'], 'calls')
    await flushPromises()

    expect(wrapper.find('.wx-call-list').exists()).toBe(true)
    expect(wrapper.find('.wx-admin-list').exists()).toBe(false)
    expect(get).toHaveBeenCalledWith('/api/cms/auth/mcp-calls', expect.anything())
    expect(wrapper.text()).not.toContain('Add')

    // Switching a view is a navigation, so the filters of each survive the other.
    const push = vi.spyOn(router, 'push').mockResolvedValue(undefined)
    await wrapper.findAll('.wx-tabs__tab')[0]!.trigger('mousedown')
    await flushPromises()

    expect(push).toHaveBeenCalledWith('/admins')
  })
})
