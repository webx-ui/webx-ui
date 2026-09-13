import { h, type Component } from 'vue'
import type { AdminModule } from '@webx-ui/admin'
import AdminsPage from './AdminsPage.vue'

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

/**
 * The administrators, as a section of the panel.
 *
 * The id matches the module the server reports, which is what makes the entry appear in the
 * navigation: a section shows up when both halves are installed.
 */
export function admins(options: AdminsOptions = {}): AdminModule {
  // The section is the administrators, and the address says so. The id stays 'users': it is
  // what the server reports, and the permissions and MCP tools carry it in their names.
  const path = options.path ?? '/admins'

  return {
    id: 'users',
    path,
    routes: [
      {
        path,
        name: 'webx.admins',
        component: {
          render: () =>
            h(AdminsPage, {
              avatarField: options.avatarField,
              resolveAvatar: options.resolveAvatar,
            }),
        },
      },
    ],
  }
}
