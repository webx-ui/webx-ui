import type { AdminContext } from '@webx-ui/module-admin'
import type {
  NotificationSettings,
  Project,
  ProjectInput,
  TaskDetail,
  TaskInput,
  TaskQuery,
  TasksPage,
} from './types'

export interface TasksApi {
  projects(): Promise<Project[]>
  createProject(input: ProjectInput): Promise<Project>
  saveProject(id: number, input: ProjectInput): Promise<Project>
  /** A page of tasks under a view, with the count of every view. */
  tasks(query?: TaskQuery): Promise<TasksPage>
  task(id: number): Promise<TaskDetail>
  /** A draft with its stages. */
  create(input: TaskInput): Promise<TaskDetail>
  /** Within the rules of §3: refused with 409 and the reason when the task has started. */
  save(id: number, input: TaskInput): Promise<TaskDetail>
  ready(id: number): Promise<TaskDetail>
  cancel(id: number, reason?: string): Promise<TaskDetail>
  comment(id: number, body: string, stageId?: number | null): Promise<TaskDetail>
  accept(stage: number, note?: string): Promise<TaskDetail>
  rework(stage: number, remarks: string): Promise<TaskDetail>
  unblock(stage: number, answer: string): Promise<TaskDetail>
  release(stage: number, reason?: string): Promise<TaskDetail>
  cancelStage(stage: number, reason?: string): Promise<TaskDetail>
  /** The notification settings; the bot token only as whether one is set. */
  settings(): Promise<NotificationSettings>
  saveSettings(
    values: Record<string, unknown>,
    forgetToken?: boolean,
  ): Promise<NotificationSettings>
  /** Sends one message now; refused with 409 and Telegram's own words when it does not get through. */
  testNotice(): Promise<string>
}

/** The queue's endpoints under the panel's API path; every change answers the whole task. */
export function createTasksApi(admin: AdminContext): TasksApi {
  const base = `${admin.apiPath}/tasks`
  const data = <T>(body: { data: T }): T => body.data

  const task = (path: string, body: Record<string, unknown> = {}) =>
    admin.http.post<{ data: TaskDetail }>(`${base}/${path}`, body).then(data)

  return {
    projects: () => admin.http.get<{ data: Project[] }>(`${base}/projects`).then(data),

    createProject: (input) =>
      admin.http.post<{ data: Project }>(`${base}/projects`, input).then(data),

    saveProject: (id, input) =>
      admin.http.put<{ data: Project }>(`${base}/projects/${id}`, input).then(data),

    /* `undefined` drops a parameter; `null` and `''` would be sent as words. */
    tasks: (query = {}) =>
      admin.http.get<TasksPage>(`${base}/tasks`, {
        query: {
          view: query.view && query.view !== 'all' ? query.view : undefined,
          project: query.project ?? undefined,
          q: query.q || undefined,
          page: query.page,
          per_page: query.per_page,
        },
      }),

    task: (id) => admin.http.get<{ data: TaskDetail }>(`${base}/tasks/${id}`).then(data),

    create: (input) => admin.http.post<{ data: TaskDetail }>(`${base}/tasks`, input).then(data),

    save: (id, input) =>
      admin.http.put<{ data: TaskDetail }>(`${base}/tasks/${id}`, input).then(data),

    ready: (id) => task(`tasks/${id}/ready`),
    cancel: (id, reason) => task(`tasks/${id}/cancel`, { reason }),
    comment: (id, body, stageId) => task(`tasks/${id}/comments`, { body, stage_id: stageId }),
    accept: (stage, note) => task(`stages/${stage}/accept`, { note }),
    rework: (stage, remarks) => task(`stages/${stage}/rework`, { remarks }),
    unblock: (stage, answer) => task(`stages/${stage}/unblock`, { answer }),
    release: (stage, reason) => task(`stages/${stage}/release`, { reason }),
    cancelStage: (stage, reason) => task(`stages/${stage}/cancel`, { reason }),

    settings: () => admin.http.get<{ data: NotificationSettings }>(`${base}/settings`).then(data),

    saveSettings: (values, forgetToken = false) =>
      admin.http
        .put<{ data: NotificationSettings }>(`${base}/settings`, {
          values,
          forget_token: forgetToken || undefined,
        })
        .then(data),

    testNotice: () =>
      admin.http
        .post<{ message: string }>(`${base}/settings/test`, {})
        .then((body) => body.message),
  }
}
