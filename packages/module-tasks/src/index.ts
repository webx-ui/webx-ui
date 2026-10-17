import { defineAsyncComponent } from 'vue'
export { tasks, type TasksOptions } from './module'
export { createTasksApi, type TasksApi } from './api'
export { tasksMessages } from './messages'
export const WxTasksPage = defineAsyncComponent(() => import('./TasksPage.vue'))
export const WxTaskPage = defineAsyncComponent(() => import('./TaskPage.vue'))
export const WxTaskFormPage = defineAsyncComponent(() => import('./TaskFormPage.vue'))
export const WxTasksProjectsPage = defineAsyncComponent(() => import('./ProjectsPage.vue'))
export const WxTasksSettingsPage = defineAsyncComponent(() => import('./TasksSettingsPage.vue'))
export type {
  CurrentStage,
  JournalEntry,
  JournalKind,
  NotificationSettings,
  Priority,
  Project,
  ProjectInput,
  ReviewMode,
  Stage,
  StageInput,
  StageStatus,
  TaskDetail,
  TaskInput,
  TaskQuery,
  TaskRow,
  TasksPage,
  TaskStatus,
  TaskView,
} from './types'
