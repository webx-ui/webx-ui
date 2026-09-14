import type { AdminContext } from '@webx-ui/module-admin'
import type { SettingsValues } from './types'

export interface SettingsApi {
  /** Every value the screen describes, as stored. */
  load(): Promise<SettingsValues>
  /** Sends the values back; answers with what the server kept. A 422 lands as `HttpError`. */
  save(values: SettingsValues): Promise<SettingsValues>
}

/** `GET` and `PUT` on `/settings`, under the panel's API path. */
export function createSettingsApi(admin: AdminContext): SettingsApi {
  const base = `${admin.apiPath}/settings`

  return {
    async load() {
      const body = await admin.http.get<{ data: { values: SettingsValues } }>(base)

      return body.data.values
    },
    async save(values) {
      const body = await admin.http.put<{ data: { values: SettingsValues } }>(base, { values })

      return body.data.values
    },
  }
}
