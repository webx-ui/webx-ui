import { inject, type App, type Component, type InjectionKey } from 'vue'
import type { AdminContext, AdminUser, ThemePreference } from '@webx-ui/module-admin'

export interface Credentials {
  email: string
  password: string
  remember?: boolean
}

export interface AuthSession {
  /** Who is signed in, or null. A 401 is an answer, not a failure. */
  me(): Promise<AdminUser | null>
  /** Signs in and tells the panel to load itself. Throws `HttpError` on a refusal. */
  login(credentials: Credentials): Promise<AdminUser>
  logout(): Promise<void>
  /**
   * Change the language this administrator reads the panel in.
   *
   * Stored on the person rather than in this browser, so it follows them to the next machine
   * and so the server writes its own messages — a 422 under a field — in the same language as
   * the label above it.
   */
  setLocale(code: string): Promise<void>
  /**
   * Write down which theme this administrator reads the panel in.
   *
   * Only writes it down: the screen has already changed by the time this is called. Painting
   * is instant and local, and an account that has not caught up yet costs nobody anything —
   * whereas a panel that waits for the network before it changes colour looks broken.
   */
  setTheme(preference: ThemePreference): Promise<void>
  /**
   * Change what is yours to change about yourself: your name, your photograph, your password.
   *
   * Not the administrators endpoint. That one is behind `admins.manage`, which is a permission
   * about other people — an editor who may not manage anybody still has a name to spell and
   * a password to rotate.
   */
  updateProfile(profile: ProfileInput): Promise<AdminUser>
}

/** What somebody may send about themselves. A blank password is "leave it alone". */
export interface ProfileInput {
  name: string
  avatar: string | null
  password?: string
  /** Required by the server whenever `password` is filled in. */
  current_password?: string
}

export const authKey: InjectionKey<AuthSession> = Symbol('webx-auth')

export function useAuth(): AuthSession {
  const session = inject(authKey, null)

  if (session === null) {
    throw new Error('useAuth() was called outside a panel that installed the auth plugin.')
  }

  return session
}

/**
 * The three calls `webx-ui/module-auth` answers, and nothing else.
 *
 * The session lives in a cookie, so there is nothing to keep here: the source of truth is the
 * server, and the panel asks it rather than remembering.
 */
export function createAuthSession(admin: AdminContext): AuthSession {
  const base = `${admin.apiPath}/auth`

  return {
    async me() {
      try {
        const body = await admin.http.get<{ data: AdminUser | null }>(`${base}/me`)

        return body.data
      } catch (error) {
        if (isUnauthenticated(error)) {
          return null
        }

        throw error
      }
    },

    async login(credentials) {
      const body = await admin.http.post<{ data: AdminUser }>(`${base}/login`, {
        email: credentials.email,
        password: credentials.password,
        remember: credentials.remember ?? false,
      })

      admin.setUser(body.data)

      // Signing in is the moment the panel becomes knowable: the manifest needs a session,
      // so it could not have been loaded before now.
      await admin.reload()

      return body.data
    },

    async setLocale(code) {
      // The panel redraws only after the server has accepted the choice: a language that
      // half took — menus switched, error messages not — is worse than one that did not.
      const body = await admin.http.put<{ data: AdminUser }>(`${base}/locale`, { locale: code })

      admin.setUser(body.data)

      await admin.setLocale(code)
    },

    async setTheme(preference) {
      // Null rather than the word: "follow the machine" is the absence of a choice, and the
      // column says so the same way the language column does.
      const body = await admin.http.put<{ data: AdminUser }>(`${base}/theme`, {
        theme: preference === 'system' ? null : preference,
      })

      admin.setUser(body.data)
    },

    async updateProfile(profile) {
      const body = await admin.http.put<{ data: AdminUser }>(
        `${base}/me`,
        profile as unknown as Record<string, unknown>,
      )

      // The corner of the panel is drawn from this, so the new name and the new photograph
      // are on screen before the dialog has finished closing.
      admin.setUser(body.data)

      return body.data
    },

    async logout() {
      try {
        await admin.http.post(`${base}/logout`)
      } finally {
        // Whatever the server said, this browser is done: leaving the panel drawn behind a
        // failed sign-out would be worse than signing out optimistically.
        admin.setUser(null)
        admin.state.status = 'unauthenticated'
        admin.state.manifest = null
      }
    },
  }
}

/** Turns the key an avatar is stored under into an address the browser can load. */
export type AvatarResolver = (key: string) => Promise<string | null>

/**
 * The resolver the panel handed to `auth()`, for whatever draws the signed-in person — the
 * menu in the corner, first of all. Null in a panel that has no library to resolve against,
 * which is what initials are for.
 */
export const avatarResolverKey: InjectionKey<AvatarResolver | null> = Symbol('webx-auth-avatar')

/**
 * The field the panel picks photographs with, for the profile dialog behind the corner menu.
 *
 * Provided rather than passed: the shell renders the user menu itself, and a component it
 * builds is not a place to hand a component down through. Null in a panel with no library —
 * the profile still opens, with everything but the photograph.
 */
export const avatarFieldKey: InjectionKey<Component | null> = Symbol('webx-auth-avatar-field')

export function provideAuth(
  app: App,
  session: AuthSession,
  resolveAvatar: AvatarResolver | null = null,
  avatarField: Component | null = null,
): void {
  app.provide(authKey, session)
  app.provide(avatarResolverKey, resolveAvatar)
  app.provide(avatarFieldKey, avatarField)
}

function isUnauthenticated(error: unknown): boolean {
  return (
    typeof error === 'object' &&
    error !== null &&
    'status' in error &&
    (error as { status: unknown }).status === 401
  )
}
