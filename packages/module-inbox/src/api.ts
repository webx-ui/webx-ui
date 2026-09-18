import type { AdminContext } from '@webx-ui/module-admin'
import type {
  FieldInput,
  FormInput,
  InboxField,
  InboxForm,
  InboxRecipient,
  InboxStatus,
  StatusInput,
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
  }
}
