import { defineAsyncComponent } from 'vue'
import { h, watch, type Component } from 'vue'
import { toast } from '@webx-ui/core'
import type { Admin, AdminPlugin } from '@webx-ui/module-admin'
import LoginCard from './LoginCard.vue'
import { libraryAvatarField, libraryAvatarResolver } from './avatars'
import { authMessages } from './messages'
import { createAuthSession, provideAuth, type AvatarResolver } from './session'

export interface AuthOptions {
  /** Where the sign-in screen lives, inside the panel. */
  path?: string
  /** Props handed to the card — the labels, mostly. */
  card?: Record<string, unknown>
  /**
   * Turns the key the signed-in person's photograph is stored under into an address, so the
   * menu in the corner shows the picture rather than initials. The same function `admins()`
   * takes: this package does not depend on the media one, and the panel is the only place
   * that knows both are installed.
   */
  resolveAvatar?: AvatarResolver
  /**
   * The field that picks a photograph — `WxMediaField` in a panel with the library. Handed
   * in for the same reason as the resolver, and used by the profile dialog in the corner
   * menu. Without it a person edits everything about themselves except their picture.
   */
  avatarField?: Component
}

/**
 * Signing in, as a plugin rather than a section: it owns a route and a guard, and it has no
 * place in the menu.
 *
 * Installing it also tells the panel how to find out who is signed in, which is why the panel
 * itself knows nothing about authentication — it holds the answer, not the question.
 */
export function auth(options: AuthOptions = {}): AdminPlugin {
  const path = options.path ?? '/login'

  return {
    install(admin: Admin) {
      const session = createAuthSession(admin.context)

      // The floor under this module's labels: what the server has not translated, and what
      // a panel assembled without a server has to fall back on.
      admin.i18n.defaults('webx-auth', authMessages)

      // Handed in, they win; otherwise the panel's library, when it has one.
      provideAuth(
        admin.app,
        session,
        options.resolveAvatar ?? libraryAvatarResolver(admin.context),
        options.avatarField ?? libraryAvatarField(admin.context),
      )
      admin.context.useSessionLoader(() => session.me())

      admin.router.addRoute({
        path,
        name: 'webx.login',
        meta: { public: true },
        component: { render: () => h(LoginCard, { ...options.card }) },
      })

      // Also after the sign-in that a link opened in a fresh browser goes through first.
      admin.router.afterEach(() => {
        if (admin.context.state.status === 'ready') announceEmailChange(admin)
      })

      admin.router.beforeEach((to) => {
        if (to.meta.public === true) {
          return true
        }

        if (admin.context.state.status !== 'unauthenticated') {
          return true
        }

        return { path, query: to.fullPath === '/' ? {} : { next: to.fullPath } }
      })

      // Both directions are driven by the state rather than by an event, because the card
      // cannot be relied on to announce its own success: signing in flips the panel to
      // `ready`, the shell swaps the branch it renders, and the card is unmounted before the
      // line after `await` runs.
      watch(
        () => admin.context.state.status,
        (status) => {
          const current = admin.router.currentRoute.value

          // A session can also end between requests — a sign-out in another tab, an account
          // switched off. The panel learns that from a 401 on whatever it asked for next,
          // which is not a navigation, so nothing would move without this.
          if (status === 'unauthenticated' && current.path !== path) {
            void admin.router.replace({ path, query: { next: current.fullPath } })

            return
          }

          if (status === 'ready') void admin.router.isReady().then(() => announceEmailChange(admin))

          if (status === 'ready' && current.path === path) {
            const next = current.query.next

            // A whole address rather than a path is a page outside the panel that sent the
            // person here to sign in — the consent screen an agent opens — and it is a page
            // to load, not a route to resolve. Only on this site: an address elsewhere would
            // make the sign-in form a way to send people anywhere.
            if (typeof next === 'string' && isPageOnThisSite(next)) {
              location.assign(next)

              return
            }

            void admin.router.replace(typeof next === 'string' ? next : '/')
          }
        },
      )
    },
  }
}

/**
 * The link in the letter that moves a sign-in address lands on the panel with a word for how it
 * went (`?email-change=done|expired|taken`). Said once the panel is drawn — after a sign-in, if
 * the link was opened in a browser that had none — and taken off the address, so a reload does
 * not say it again.
 */
const announced = new WeakSet<object>()

function announceEmailChange(admin: Admin): void {
  const route = admin.router.currentRoute.value
  const outcome = route.query['email-change']

  if (outcome !== 'done' && outcome !== 'expired' && outcome !== 'taken') return

  // The arrival and the panel becoming ready can both see the same address before it is
  // cleaned: said once.
  if (announced.has(route)) return
  announced.add(route)

  const t = admin.i18n.scope('webx-auth')
  const query = { ...route.query }

  delete query['email-change']
  void admin.router.replace({ path: route.path, query, hash: route.hash })

  if (outcome === 'done') toast.success(t('profile.email-done'))
  else toast.danger(t(`profile.email-${outcome}`))
}

/** An absolute address on the same origin as the panel — the only kind worth following whole. */
function isPageOnThisSite(next: string): boolean {
  if (!/^https?:\/\//i.test(next)) {
    return false
  }

  try {
    return new URL(next).origin === location.origin
  } catch {
    return false
  }
}

export { admins, connect, type AdminsOptions, type AdminsView, type ConnectOptions } from './module'
export { createAdminsApi, type AdminsApi } from './admins'
export { createConnectionsApi, type ConnectionsApi } from './connections'
export { createKeysApi, type KeysApi } from './keys'
export { selectAdmin, selectAdmins, type AdminPickerOptions } from './selectAdmin'
export { default as WxAdminsPage } from './AdminsPage.vue'
export { default as WxAdminList } from './AdminList.vue'
export { default as WxAgentCallList } from './CallList.vue'
export { default as WxConnectionList } from './ConnectionList.vue'
export const WxConnectPage = defineAsyncComponent(() => import('./ConnectPage.vue'))
export { default as WxKeyList } from './KeyList.vue'
export { default as WxRoleList } from './RoleList.vue'
export { default as WxRoleDialog } from './RoleDialog.vue'
export type {
  Admin,
  AdminInput,
  AdminPage,
  AdminQuery,
  AdminRole,
  AgentCall,
  AgentCallFilters,
  AgentCallOutcome,
  AgentCallPage,
  AgentCallQuery,
  Connection,
  ConnectionScope,
  Connections,
  IssuedMcpKey,
  McpKey,
  McpKeyInput,
  McpKeys,
  PermissionCell,
  PermissionRow,
  Role,
  RoleDetails,
  RoleInput,
} from './types'
export {
  createAuthSession,
  provideAuth,
  useAuth,
  authKey,
  avatarFieldKey,
  avatarResolverKey,
  type EmailChangeSent,
  type ProfileInput,
} from './session'
export { default as WxProfileDialog } from './ProfileDialog.vue'
export type { AuthSession, AvatarResolver, Credentials } from './session'
export { default as WxLoginCard } from './LoginCard.vue'
export { default as WxUserMenu } from './UserMenu.vue'
export { authMessages } from './messages'
