import type { AdminContext } from './admin'

/** Who wrote a note. `name` is null once that account has been deleted. */
export interface NoteAuthor {
  id: number
  name: string | null
}

/** One line somebody left on a record for whoever picks it up next. */
export interface EntityNote {
  id: number
  body: string
  author: NoteAuthor | null
  /**
   * Whether the reader is the author — answered by the server, because who may edit a note is
   * a rule, and a rule the browser works out for itself is the copy that drifts.
   */
  is_mine: boolean
  created_at: string | null
  updated_at: string | null
}

export interface NotesApi {
  list(): Promise<EntityNote[]>
  add(body: string): Promise<EntityNote>
  save(id: number, body: string): Promise<EntityNote>
  remove(id: number): Promise<void>
}

/**
 * The notes of one record.
 *
 * `type` is the alias the server registered the model under — never a class name — and the
 * address is the panel's own, not the section's: the same feed hangs off a submission, an
 * order and a client, and a second copy of it in each module is a second place for the rules
 * about who may write one to be got wrong.
 */
export function createNotesApi(admin: AdminContext, type: string, id: number | string): NotesApi {
  const base = `${admin.apiPath}/entities/${type}/${id}/notes`
  const one = (noteId: number) => `${admin.apiPath}/notes/${noteId}`
  const data = <T>(body: { data: T }): T => body.data

  return {
    list: () => admin.http.get<{ data: EntityNote[] }>(base).then(data),
    add: (body) => admin.http.post<{ data: EntityNote }>(base, { body }).then(data),
    save: (id, body) => admin.http.put<{ data: EntityNote }>(one(id), { body }).then(data),
    remove: (id) => admin.http.delete(one(id)).then(() => undefined),
  }
}
