import type { AdminModule } from '@webx-ui/module-admin'
import ProjectsPage from './ProjectsPage.vue'
import TaskFormPage from './TaskFormPage.vue'
import TaskPage from './TaskPage.vue'
import TasksPage from './TasksPage.vue'
import TasksSettingsPage from './TasksSettingsPage.vue'

export interface TasksOptions {
  /** Where the section lives inside the panel. */
  path?: string
}

/**
 * «Tasks» as a section of the panel (§6): the list, one task, its form, the projects, and the
 * notification settings (§6a).
 *
 * The id matches the module the server reports, which is what makes the entry appear in the
 * navigation — with the count of what waits for a person beside it.
 */
export function tasks(options: TasksOptions = {}): AdminModule {
  const path = options.path ?? '/tasks'
  const base = { base: path }
  const id = (value: string | string[]) => Number(Array.isArray(value) ? value[0] : value)

  return {
    id: 'tasks',
    path,
    routes: [
      { path, name: 'webx.tasks', component: TasksPage, props: base },
      {
        path: `${path}/projects`,
        name: 'webx.tasks.projects',
        component: ProjectsPage,
        props: base,
      },
      {
        path: `${path}/settings`,
        name: 'webx.tasks.settings',
        component: TasksSettingsPage,
        props: base,
      },
      {
        path: `${path}/new`,
        name: 'webx.tasks.new',
        component: TaskFormPage,
        props: { ...base, id: null },
      },
      {
        path: `${path}/:id(\\d+)`,
        name: 'webx.tasks.task',
        component: TaskPage,
        props: (route) => ({ ...base, id: id(route.params.id as string) }),
      },
      {
        path: `${path}/:id(\\d+)/edit`,
        name: 'webx.tasks.edit',
        component: TaskFormPage,
        props: (route) => ({ ...base, id: id(route.params.id as string) }),
      },
    ],
  }
}
