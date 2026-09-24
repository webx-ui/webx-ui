import { createAdmin, type Admin, type AdminPlugin } from '@webx-ui/module-admin'
import { admins, connect } from '@webx-ui/module-auth'
import { blocks } from '@webx-ui/module-blocks'
import { blog } from '@webx-ui/module-blog'
import { faq } from '@webx-ui/module-faq'
import { inbox } from '@webx-ui/module-inbox'
import { media } from '@webx-ui/module-media'
import { menu } from '@webx-ui/module-menu'
import { pages } from '@webx-ui/module-pages'
import { seo } from '@webx-ui/module-seo'
import { services } from '@webx-ui/module-services'
/* The opt-in typeface; the tokens themselves come in with the core stylesheet. */
import '@webx-ui/tokens/fonts.css'
import UserMenu from './UserMenu.vue'

/**
 * The panel itself, against a server that lives in the Vite config.
 *
 * Not a copy of the screens and not a harness around them: this is `createAdmin` with the
 * module packages in it, exactly as a site assembles them, so what is polished here is what
 * ships. The only thing the playground supplies is the data — `server/panel` answers
 * `/api/cms/*` out of fixtures in memory.
 */

/** What an auth module would do, minus signing anybody in. */
const session: AdminPlugin = {
  install(admin: Admin) {
    admin.context.useSessionLoader(async () => ({
      id: 1,
      name: 'Анна Ковальчук',
      email: 'anna@webx-demo.test',
      isSuper: true,
      permissions: [],
      locale: null,
      avatar: null,
    }))
  },
}

const admin = createAdmin({
  el: '#panel',
  basePath: '/panel',
  apiPath: '/api/cms',
  // The administrators section without the sign-in plugin: the session above stands in for
  // it, and what is looked at here is the list and the trail of what agents did.
  modules: [
    inbox(),
    pages(),
    ...blog(),
    ...services(),
    ...faq(),
    menu(),
    media(),
    blocks(),
    seo(),
    admins(),
    connect(),
  ],
  plugins: [session],
  userMenu: UserMenu,
})

void admin.mount()
