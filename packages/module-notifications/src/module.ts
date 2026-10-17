import type { AdminModule } from '@webx-ui/module-admin'
import ChannelsField from './ChannelsField.vue'
import ChatsField from './ChatsField.vue'
import EmailsField from './EmailsField.vue'
import TemplateField from './TemplateField.vue'

export interface NotificationsOptions {
  /** Where the section lives inside the panel. */
  path?: string
}

/**
 * Notification channels as a section of the panel (§10): the channels, the log, the bin, and a
 * page per channel.
 *
 * The fields of a channel's form are node types the server's screen names — the chats of a bot,
 * the addresses of a letter, the template — and so is the picker a consumer chooses channels with
 * (`wx-notifications-channels`), which any screen or block may use. They come with this package
 * because what they list is this section's.
 *
 * A section whose server half is not installed, or that the person may not open — everybody but a
 * super administrator until a role is given `notifications.view` — never appears: the entry is
 * built from the manifest.
 */
export function notifications(options: NotificationsOptions = {}): AdminModule {
  const path = options.path ?? '/notifications'

  return {
    id: 'notifications',
    path,
    routes: [
      {
        path,
        name: 'webx.notifications',
        component: () => import('./NotificationsPage.vue'),
        props: { base: path },
      },
      {
        path: `${path}/channels/new`,
        name: 'webx.notifications.new',
        component: () => import('./ChannelPage.vue'),
        props: { base: path },
      },
      {
        path: `${path}/channels/:id(\\d+)`,
        name: 'webx.notifications.channel',
        component: () => import('./ChannelPage.vue'),
        props: { base: path },
      },
    ],
    types: {
      'wx-notifications-template': { component: TemplateField, kind: 'field', wide: true },
      'wx-notifications-chats': { component: ChatsField, kind: 'field' },
      'wx-notifications-emails': { component: EmailsField, kind: 'field' },
      'wx-notifications-channels': {
        component: ChannelsField,
        kind: 'field',
        bind: (node) => ({ event: node.props?.event, base: path }),
      },
    },
  }
}
