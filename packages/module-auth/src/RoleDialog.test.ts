import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { WebxUI } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import RoleDialog from './RoleDialog.vue'
import type { PermissionRow, Role } from './types'

/* The editor holds `pages.view` and nothing else of the pages; of the media, nothing. */
const matrix: PermissionRow[] = [
  {
    module: 'pages',
    title: 'Pages',
    permissions: [
      { name: 'pages.view', label: 'View', held: true },
      { name: 'pages.manage', label: 'Edit', held: false },
    ],
  },
  {
    module: 'media',
    title: 'Files',
    permissions: [{ name: 'media.view', label: 'View', held: false }],
  },
]

function dialog(role: Role | null) {
  const get = vi.fn().mockResolvedValue({ data: matrix })
  const put = vi
    .fn()
    .mockImplementation((_url: string, body: unknown) =>
      Promise.resolve({ data: { ...role, ...(body as object) } }),
    )
  const post = vi.fn().mockResolvedValue({ data: role })
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    http: { get, put, post },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
  } as unknown as AdminContext

  const wrapper = mount(RoleDialog, {
    props: { role },
    attachTo: document.body,
    global: {
      plugins: [WebxUI],
      provide: { [adminKey as symbol]: admin, [i18nKey as symbol]: i18n },
    },
  })

  return { wrapper, put, post }
}

/** The boxes of the matrix by permission, from the page — the dialog draws into the body. */
function boxes(): Record<string, HTMLInputElement> {
  const found: Record<string, HTMLInputElement> = {}

  for (const cell of document.querySelectorAll('.wx-role-form__cell')) {
    const code = cell.querySelector('.wx-role-form__code')?.textContent?.trim() ?? ''
    const input = cell.querySelector('input')

    if (input) found[code] = input
  }

  return found
}

function rowBox(index: number): HTMLInputElement {
  return document.querySelectorAll<HTMLInputElement>('.wx-role-form__module input')[index]!
}

const role: Role = {
  id: 7,
  slug: 'editors',
  name: 'Editors',
  permissions: ['pages.view', 'media.view', 'gone.view'],
  users: 2,
  held: false,
}

describe('WxRoleDialog', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('draws rule 2: what the editor does not hold cannot be ticked, but can be cleared', async () => {
    dialog(role)
    await flushPromises()

    const cells = boxes()

    expect(cells['pages.view']!.checked).toBe(true)
    expect(cells['pages.view']!.disabled).toBe(false)
    expect(cells['pages.manage']!.disabled).toBe(true)
    // Not held, but already in the role: taking it away hands out nothing.
    expect(cells['media.view']!.checked).toBe(true)
    expect(cells['media.view']!.disabled).toBe(false)
    // Left by a module no longer installed, and shown so it can be cleared.
    expect(cells['gone.view']!.checked).toBe(true)
  })

  it('ticks a whole module with one box, only as far as the editor may', async () => {
    const { put } = dialog({ ...role, permissions: [] })
    await flushPromises()

    rowBox(0).click()
    await flushPromises()

    expect(boxes()['pages.view']!.checked).toBe(true)
    expect(boxes()['pages.manage']!.checked).toBe(false)
    // Nothing in the media the editor could tick: the box of the row is off.
    expect(rowBox(1).disabled).toBe(true)

    const save = [...document.querySelectorAll('button')].find((b) =>
      b.textContent?.includes('Save'),
    )
    save!.click()
    await flushPromises()

    expect(put).toHaveBeenCalledWith('/api/cms/auth/roles/7', {
      name: 'Editors',
      slug: 'editors',
      permissions: ['pages.view'],
    })
  })

  it('opens a role the editor holds read-only (rule 5)', async () => {
    dialog({ ...role, held: true })
    await flushPromises()

    expect(document.body.textContent).toContain('You hold this role')
    expect(Object.values(boxes()).every((input) => input.disabled)).toBe(true)
    expect(
      [...document.querySelectorAll('button')].some((b) => b.textContent?.includes('Save')),
    ).toBe(false)
  })
})
