import { describe, expect, it } from 'vitest'
import { users } from './module'
import { userSearch } from './api'

describe('users()', () => {
  it('is three sections the server groups, under one path', () => {
    const modules = users()

    expect(modules.map((module) => module.id)).toEqual(['users', 'users-groups', 'users-settings'])
    expect(modules.map((module) => module.path)).toEqual([
      '/users',
      '/users/groups',
      '/users/settings',
    ])
    expect(modules[0]!.routes!.map((route) => route.path)).toEqual([
      '/users',
      '/users/new',
      '/users/:id(\\d+)',
    ])
    expect(Object.keys(modules[0]!.types ?? {})).toEqual(['wx-users-state', 'wx-users-ways'])
  })

  it('moves with its path', () => {
    expect(users({ path: '/clients' }).map((module) => module.path)).toEqual([
      '/clients',
      '/clients/groups',
      '/clients/settings',
    ])
  })
})

describe('userSearch()', () => {
  it('sends only the filters that are set, a yes or no as 1 or 0', () => {
    expect(
      userSearch({
        search: 'anna',
        group: undefined,
        verified: false,
        blocked: true,
        page: 2,
      }).toString(),
    ).toBe('search=anna&verified=0&blocked=1&page=2')
  })
})
