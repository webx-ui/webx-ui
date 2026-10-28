import type { AdminModule } from '@webx-ui/module-admin'
import StateField from './StateField.vue'
import WaysField from './WaysField.vue'

export interface UsersOptions {
  /** Where the customers live inside the panel. The groups and the settings sit under it. */
  path?: string
}

/**
 * «Customers» as sections of the panel (spec §16): the people, their groups, the settings.
 *
 * Three modules, because the navigation is one entry per module; the server puts them in the
 * `users` group and decides who sees which — the settings are `users.settings` alone. The panel
 * includes them as `...users()`, the line `extra.webx.panel.register` of the composer package. A
 * section whose server half is not installed never appears.
 *
 * The two node types are the parts of the card a field cannot be: the state of the account and
 * the ways to sign in with «Unlink». They read the open customer from the page, not from the model.
 */
export function users(options: UsersOptions = {}): AdminModule[] {
  const path = options.path ?? '/users'
  const props = { base: path }

  return [
    {
      id: 'users',
      path,
      routes: [
        { path, name: 'webx.users', component: () => import('./UsersPage.vue'), props },
        {
          path: `${path}/new`,
          name: 'webx.users.new',
          component: () => import('./UserEditorPage.vue'),
          props,
        },
        {
          path: `${path}/:id(\\d+)`,
          name: 'webx.users.edit',
          component: () => import('./UserEditorPage.vue'),
          props,
        },
      ],
      types: {
        'wx-users-state': { component: StateField, kind: 'display' },
        'wx-users-ways': { component: WaysField, kind: 'display' },
      },
    },
    {
      id: 'users-groups',
      path: `${path}/groups`,
      routes: [
        {
          path: `${path}/groups`,
          name: 'webx.users.groups',
          component: () => import('./GroupsPage.vue'),
          props,
        },
      ],
    },
    {
      id: 'users-settings',
      path: `${path}/settings`,
      routes: [
        {
          path: `${path}/settings`,
          name: 'webx.users.settings',
          component: () => import('./SettingsPage.vue'),
          props,
        },
      ],
    },
  ]
}
