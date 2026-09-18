import type { AdminContext } from '@webx-ui/module-admin'
import type {
  FieldInput,
  FormInput,
  InboxField,
  InboxForm,
  InboxRecipient,
  InboxStatus,
  InboxSubmission,
  StatusInput,
  SubmissionInput,
  SubmissionMassInput,
  SubmissionQuery,
  SubmissionsPage,
} from './types'

export interface InboxApi {
  /** Every form, in the order of the column, with what is waiting in each. */
  forms(): Promise<InboxForm[]>
  /** One form with its fields — what the editor opens. */
  form(id: number): Promise<InboxForm>
  createForm(input: FormInput): Promise<InboxForm>
  saveForm(id: number, input: FormInput): Promise<InboxForm>
  /** Refused with a 422 when the form has submissions: it is switched off, not deleted. */
  removeForm(id: number): Promise<void>
  /** A copy, switched off, with the questions and none of the answers. */
  duplicateForm(id: number): Promise<InboxForm>
  sortForms(ids: number[]): Promise<void>

  fields(formId: number): Promise<InboxField[]>
  createField(formId: number, input: FieldInput): Promise<InboxField>
  saveField(id: number, input: FieldInput): Promise<InboxField>
  /** Softly: the answers already given through it keep reading. */
  removeField(id: number): Promise<void>
  sortFields(formId: number, ids: number[]): Promise<void>

  statuses(): Promise<InboxStatus[]>
  createStatus(input: StatusInput): Promise<InboxStatus>
  saveStatus(id: number, input: StatusInput): Promise<InboxStatus>
  /** Refused with a 422 while submissions are still in it. */
  removeStatus(id: number): Promise<void>
  sortStatuses(ids: number[]): Promise<void>

  /** The administrators a form can be told to write to: the ones who may read the section. */
  recipients(): Promise<InboxRecipient[]>

  /** One form's submissions, with the columns of the list and the counts of its tabs. */
  submissions(formId: number, query?: SubmissionQuery): Promise<SubmissionsPage>
  /**
   * One of them, open. Opening it marks it read.
   *
   * The filters travel with it so that the neighbours it reports are the ones either side of
   * it in the list somebody was actually looking at.
   */
  submission(id: number, query?: SubmissionQuery): Promise<InboxSubmission>
  /** Typed in by hand — a call that came by telephone. `fields` is by machine name. */
  createSubmission(formId: number, fields: Record<string, unknown>): Promise<InboxSubmission>
  saveSubmission(id: number, input: SubmissionInput): Promise<InboxSubmission>
  removeSubmission(id: number): Promise<void>
  /** A pile moved, marked or thrown away at once. */
  massSubmissions(input: SubmissionMassInput): Promise<{ count: number }>
  /**
   * Where the spreadsheet is. An address rather than a request: a download is the browser's
   * own job, and fetching a file into memory to hand it back to the browser is work for
   * nothing.
   */
  exportUrl(formId: number, query?: SubmissionQuery): string
}

/** Everything under `/inbox`, below the panel's API path. */
export function createInboxApi(admin: AdminContext): InboxApi {
  const base = `${admin.apiPath}/inbox`
  const data = <T>(body: { data: T }): T => body.data
  const nothing = (): void => undefined

  return {
    forms: () => admin.http.get<{ data: InboxForm[] }>(`${base}/forms`).then(data),
    form: (id) => admin.http.get<{ data: InboxForm }>(`${base}/forms/${id}`).then(data),
    createForm: (input) => admin.http.post<{ data: InboxForm }>(`${base}/forms`, input).then(data),
    saveForm: (id, input) =>
      admin.http.put<{ data: InboxForm }>(`${base}/forms/${id}`, input).then(data),
    removeForm: (id) => admin.http.delete(`${base}/forms/${id}`).then(nothing),
    duplicateForm: (id) =>
      admin.http.post<{ data: InboxForm }>(`${base}/forms/${id}/duplicate`, {}).then(data),
    sortForms: (ids) => admin.http.post(`${base}/forms/sorting`, { ids }).then(nothing),

    fields: (formId) =>
      admin.http.get<{ data: InboxField[] }>(`${base}/forms/${formId}/fields`).then(data),
    createField: (formId, input) =>
      admin.http.post<{ data: InboxField }>(`${base}/forms/${formId}/fields`, input).then(data),
    saveField: (id, input) =>
      admin.http.put<{ data: InboxField }>(`${base}/fields/${id}`, input).then(data),
    removeField: (id) => admin.http.delete(`${base}/fields/${id}`).then(nothing),
    sortFields: (formId, ids) =>
      admin.http.post(`${base}/forms/${formId}/fields/sorting`, { ids }).then(nothing),

    statuses: () => admin.http.get<{ data: InboxStatus[] }>(`${base}/statuses`).then(data),
    createStatus: (input) =>
      admin.http.post<{ data: InboxStatus }>(`${base}/statuses`, input).then(data),
    saveStatus: (id, input) =>
      admin.http.put<{ data: InboxStatus }>(`${base}/statuses/${id}`, input).then(data),
    removeStatus: (id) => admin.http.delete(`${base}/statuses/${id}`).then(nothing),
    sortStatuses: (ids) => admin.http.post(`${base}/statuses/sorting`, { ids }).then(nothing),

    recipients: () => admin.http.get<{ data: InboxRecipient[] }>(`${base}/recipients`).then(data),

    submissions: (formId, query = {}) =>
      admin.http
        .get<{
          data: SubmissionsPage['data']
          meta: Omit<SubmissionsPage, 'data' | 'columns' | 'counts'>
          columns: SubmissionsPage['columns']
          counts: SubmissionsPage['counts']
        }>(`${base}/forms/${formId}/submissions`, { query: listQuery(query) })
        .then((body) => ({
          ...body.meta,
          data: body.data,
          columns: body.columns,
          counts: body.counts,
        })),

    submission: (id, query = {}) =>
      admin.http
        .get<{ data: InboxSubmission }>(`${base}/submissions/${id}`, { query: listQuery(query) })
        .then(data),

    createSubmission: (formId, fields) =>
      admin.http
        .post<{ data: InboxSubmission }>(`${base}/forms/${formId}/submissions`, { fields })
        .then(data),

    saveSubmission: (id, input) =>
      admin.http.put<{ data: InboxSubmission }>(`${base}/submissions/${id}`, input).then(data),

    removeSubmission: (id) => admin.http.delete(`${base}/submissions/${id}`).then(nothing),

    massSubmissions: (input) =>
      admin.http.post<{ data: { count: number } }>(`${base}/submissions/mass`, input).then(data),

    exportUrl: (formId, query = {}) => {
      const search = new URLSearchParams()

      for (const [key, value] of Object.entries(listQuery(query))) {
        if (value !== undefined && value !== null && value !== '') {
          search.set(key, String(value))
        }
      }

      const suffix = search.toString()

      return `${base}/forms/${formId}/submissions/export${suffix === '' ? '' : `?${suffix}`}`
    },
  }
}

/**
 * The filters as query parameters, with the empty ones left out.
 *
 * `page` and `per_page` go too, because the same shape is what the export is asked for — the
 * server ignores them there, and a second almost-identical builder is a second thing to get
 * out of step.
 */
function listQuery(query: SubmissionQuery): Record<string, string | number | undefined> {
  return {
    view: query.view === undefined || query.view === '' ? undefined : query.view,
    search: query.search === undefined || query.search === '' ? undefined : query.search,
    assignee: query.assignee ?? undefined,
    sort: query.sort ?? undefined,
    page: query.page,
    per_page: query.per_page,
  }
}
