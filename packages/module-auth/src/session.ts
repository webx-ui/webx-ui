import { inject, type App, type InjectionKey } from 'vue'
import type { AdminContext, AdminUser } from '@webx-ui/admin'

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

export function provideAuth(app: App, session: AuthSession): void {
  app.provide(authKey, session)
}

function isUnauthenticated(error: unknown): boolean {
  return (
    typeof error === 'object' &&
    error !== null &&
    'status' in error &&
    (error as { status: unknown }).status === 401
  )
}
