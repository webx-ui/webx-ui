import { h, type Component } from 'vue'
import type { AdminModule } from '@webx-ui/module-admin'
import AdminsPage from './AdminsPage.vue'
import ConnectPage from './ConnectPage.vue'

export interface AdminsOptions {
  /** Where the section lives inside the panel. */
  path?: string
  /**
   * The field the photograph is picked with — `WxMediaField` in a panel that has the library.
   *
   * Handed in rather than imported: this package does not depend on the media one, and a panel
   * without a library still edits everything else about a person.
   */
  avatarField?: Component
  /** Turns an avatar key into an address. Omitted, rows and forms show initials. */
  resolveAvatar?: (key: string) => Promise<string | null>
}

/** Which of the three views of the administrators section a route opens. */
export type AdminsView = 'admins' | 'calls' | 'connections'

/**
 * The administrators, as a section of the panel.
 *
 * The id matches the module the server reports, which is what makes the entry appear in the
 * navigation: a section shows up when both halves are installed.
 */
export function admins(options: AdminsOptions = {}): AdminModule {
  const path = options.path ?? '/admins'

  const page = (current: AdminsView) => ({
    render: () =>
      h(AdminsPage, {
        base: path,
        current,
        avatarField: options.avatarField,
        resolveAvatar: options.resolveAvatar,
      }),
  })

  return {
    id: 'admins',
    path,
    routes: [
      { path, name: 'webx.admins', component: page('admins') },
      // What their agents did, and which agents they let in: two more views of the same
      // section, each with an address of its own so that its filters survive a trip to the
      // people and back.
      { path: `${path}/calls`, name: 'webx.admins.calls', component: page('calls') },
      {
        path: `${path}/connections`,
        name: 'webx.admins.connections',
        component: page('connections'),
      },
    ],
  }
}

export interface ConnectOptions {
  /** Where the page lives inside the panel. */
  path?: string
}

/**
 * The page that tells a person how to connect their own agent.
 *
 * A section of its own rather than a corner of the profile dialog, because it is the page
 * somebody is sent a link to — a client who has never opened this panel, on a call, with
 * somebody reading the steps out. The server reports it only where there is a door to connect
 * to, so a panel with the agent server switched off simply has no such entry.
 *
 * It carries no permission: whoever got into the panel may connect an agent, and the agent
 * will not be able to do anything they cannot.
 */
export function connect(options: ConnectOptions = {}): AdminModule {
  const path = options.path ?? '/connect'

  return {
    id: 'connect',
    path,
    routes: [{ path, name: 'webx.connect', component: ConnectPage }],
  }
}
