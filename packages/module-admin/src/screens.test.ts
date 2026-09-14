import { describe, expect, it, vi } from 'vitest'
import { h } from 'vue'
import { createAdminContext } from './admin'
import { createI18n } from './i18n'
import type { Http } from './http'
import type { Manifest } from './types'

const english = {
  code: 'en',
  name: 'English',
  nativeName: 'English',
  direction: 'ltr',
  default: true,
} as const

const manifest: Manifest = {
  title: 'WebX UI',
  path: '/cms',
  apiPath: '/api/cms',
  locale: 'en',
  locales: [english],
  panelLocales: [english],
  groups: [{ id: 'system', title: 'System', order: 900 }],
  modules: [
    {
      id: 'media',
      title: 'Files',
      icon: 'folder',
      order: 300,
      group: null,
      permissions: [],
      meta: {},
    },
    {
      id: 'settings',
      title: 'Settings',
      icon: 'settings',
      order: 800,
      group: 'system',
      permissions: [],
      meta: {},
    },
    {
      id: 'admins',
      title: 'Administrators',
      icon: 'users',
      order: 900,
      group: 'system',
      permissions: [],
      meta: {},
    },
    {
      id: 'orphan',
      title: 'Orphan',
      icon: null,
      order: 950,
      group: 'nowhere',
      permissions: [],
      meta: {},
    },
  ],
  screens: ['settings.index'],
}

const page = { render: () => h('div') }

function context(get: ReturnType<typeof vi.fn>, extra: Record<string, unknown> = {}) {
  const i18n = createI18n({ locale: 'en' })
  const http = { get } as unknown as Http

  return createAdminContext({
    http,
    basePath: '/cms',
    apiPath: '/api/cms',
    i18n,
    modules: [
      { id: 'media', routes: [{ path: '/media', component: page }] },
      { id: 'settings', routes: [{ path: '/settings', component: page }] },
      { id: 'admins', routes: [{ path: '/admins', component: page }] },
      { id: 'orphan', routes: [{ path: '/orphan', component: page }] },
    ],
    loadManifest: async () => manifest,
    ...extra,
  })
}

describe('navigation groups', () => {
  it('puts sections under the groups the server declares and leaves the rest on top', async () => {
    const admin = context(vi.fn())
    await admin.reload()

    const { top, groups } = admin.groups.value

    expect(top.map((entry) => entry.id)).toEqual(['media', 'orphan'])
    expect(groups).toHaveLength(1)
    expect(groups[0]?.title).toBe('System')
    expect(groups[0]?.entries.map((entry) => entry.id)).toEqual(['settings', 'admins'])
  })
})

describe('screens', () => {
  it('fetches a screen once per language', async () => {
    const get = vi.fn().mockResolvedValue({ data: { screen: 'settings.index', root: [] } })
    const admin = context(get)

    await admin.loadScreen('settings.index')
    await admin.loadScreen('settings.index')
    expect(get).toHaveBeenCalledTimes(1)
    expect(get).toHaveBeenCalledWith('/api/cms/screens/settings.index')

    // The tree is translated on the server, so another language is another request.
    admin.i18n.state.locale = 'ru'
    await admin.loadScreen('settings.index')
    expect(get).toHaveBeenCalledTimes(2)
  })

  it('forgets a request that failed, so the next open tries again', async () => {
    const get = vi
      .fn()
      .mockRejectedValueOnce(new Error('down'))
      .mockResolvedValue({
        data: { screen: 'settings.index', root: [] },
      })
    const admin = context(get)

    await expect(admin.loadScreen('settings.index')).rejects.toThrow('down')
    await expect(admin.loadScreen('settings.index')).resolves.toEqual([])
    expect(get).toHaveBeenCalledTimes(2)
  })

  it('hands out the project patch for a screen, and nothing for the others', () => {
    const patch = [{ op: 'remove' as const, target: 'project-logo' }]
    const admin = context(vi.fn(), { screens: { 'settings.index': patch } })

    expect(admin.screenPatch('settings.index')).toBe(patch)
    expect(admin.screenPatch('admins.form')).toEqual([])
  })

  it('merges the modules types under the project types', () => {
    const fromModule = { component: page, kind: 'field' as const }
    const fromProject = { component: page, kind: 'display' as const }
    const admin = createAdminContext({
      http: {} as Http,
      basePath: '/cms',
      apiPath: '/api/cms',
      i18n: createI18n({ locale: 'en' }),
      modules: [{ id: 'media', types: { 'wx-media': fromModule, map: fromModule } }],
      types: { map: fromProject },
      loadManifest: async () => manifest,
    })

    expect(admin.types['wx-media']).toBe(fromModule)
    expect(admin.types.map).toBe(fromProject)
  })
})
