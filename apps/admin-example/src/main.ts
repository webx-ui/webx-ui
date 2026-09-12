import { h } from 'vue'
import { createAdmin, useAdmin, type AdminModule } from '@webx-ui/admin'
import { auth, WxUserMenu } from '@webx-ui/module-auth'
/* The opt-in typeface; the tokens themselves come in with the core stylesheet. */
import '@webx-ui/tokens/fonts.css'

/**
 * What a site's own admin project looks like: a list of modules, a plugin for signing in, and
 * nothing else. Everything on screen comes from the packages and from what the server says it
 * has.
 */

/** Stands in for the module packages that do not exist yet. */
const usersModule: AdminModule = {
  id: 'users',
  path: '/users',
  routes: [
    {
      path: '/users',
      name: 'users',
      component: {
        setup() {
          const admin = useAdmin()

          return () =>
            h('div', [
              h('h1', 'Administrators'),
              h('p', `Signed in as ${admin.state.user?.email ?? 'nobody'}.`),
              h('p', `May manage administrators: ${admin.can('users.manage') ? 'yes' : 'no'}.`),
            ])
        },
      },
    },
  ],
}

const admin = createAdmin({
  basePath: '/cms',
  modules: [usersModule],
  plugins: [auth()],
  userMenu: WxUserMenu,
  routes: [{ path: '/', redirect: '/users' }],
})

void admin.mount()
