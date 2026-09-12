import { h, watch } from 'vue'
import type { Admin, AdminPlugin } from '@webx-ui/admin'
import LoginCard from './LoginCard.vue'
import { createAuthSession, provideAuth } from './session'

export interface AuthOptions {
  /** Where the sign-in screen lives, inside the panel. */
  path?: string
  /** Props handed to the card — the labels, mostly. */
  card?: Record<string, unknown>
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

      provideAuth(admin.app, session)
      admin.context.useSessionLoader(() => session.me())

      admin.router.addRoute({
        path,
        name: 'webx.login',
        meta: { public: true },
        component: { render: () => h(LoginCard, { ...options.card }) },
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

          if (status === 'ready' && current.path === path) {
            const next = current.query.next

            void admin.router.replace(typeof next === 'string' ? next : '/')
          }
        },
      )
    },
  }
}

export { createAuthSession, provideAuth, useAuth, authKey } from './session'
export type { AuthSession, Credentials } from './session'
export { default as WxLoginCard } from './LoginCard.vue'
export { default as WxUserMenu } from './UserMenu.vue'
