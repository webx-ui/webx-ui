<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  useAdmin,
  useBodyKeys,
  useErrorText,
  useTranslate,
  WxSaveState,
  WxScreen,
  WxScreenHead,
} from '@webx-ui/module-admin'
import {
  confirm,
  toast,
  WxActionBar,
  WxAlert,
  WxBadge,
  WxButton,
  WxCard,
  WxSkeleton,
  WxText,
} from '@webx-ui/core'
import type { Patch, ScreenModel } from '@webx-ui/schema'
import { createTasksApi } from './api'
import { useTasksMessages } from './i18n'
import { stageTones } from './tones'
import type { Project, Stage, StageInput, TaskDetail, TaskInput } from './types'

/**
 * The form of a task (§6.3): the described screen `tasks.form` — so a project can move or relabel
 * a field with a patch — with the rules of §3 laid over it by the page.
 *
 * A draft is the author's: every field, and the stages as one list to add to, reorder and remove
 * from. Once the task is in work the agents have read it, and what they read must stay what it
 * was: the task's own fields are shown but locked, except the priority — that is the order of the
 * queue, not the content of the work. Stages somebody already took are listed above the editor and
 * cannot be touched; the repeater holds only the ones nobody has started, and new ones go after
 * them. On save the list is put back together around the started stages, which keep their places.
 */
defineOptions({ name: 'WxTaskFormPage' })

const props = withDefaults(defineProps<{ id?: number | null; base?: string }>(), {
  id: null,
  base: '/tasks',
})

const context = useAdmin()
const api = createTasksApi(context)
const route = useRoute()
const router = useRouter()
useTasksMessages()

const t = useTranslate('webx-tasks')
const message = useErrorText()

const task = ref<TaskDetail | null>(null)
const projects = ref<Project[]>([])
const values = ref<ScreenModel>({})
const snapshot = ref('')
const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = context.can('tasks.manage')

const draft = computed(() => task.value === null || task.value.status === 'draft')

/** Taken, reported, done or cancelled — the stages the rules keep as they are. */
const locked = computed<Stage[]>(() =>
  draft.value
    ? []
    : (task.value?.stages ?? []).filter(
        (stage) => stage.status !== 'pending' && stage.status !== 'ready',
      ),
)

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value || props.id === null ? 'unsaved' : 'saved'
})

/** What only the page knows: the projects to choose from, and what the rules lock. */
const patch = computed<Patch>(() => {
  const ops: Patch = [
    {
      op: 'set',
      target: 'project',
      props: {
        options: projects.value
          .filter((project) => !project.archived || project.id === values.value.project_id)
          .map((project) => ({ label: project.title, value: project.id })),
        // A task never moves between projects: its number is counted inside one.
        disabled: props.id !== null,
      },
    },
  ]

  if (!draft.value) {
    for (const target of ['title', 'branch', 'spec', 'acceptance']) {
      ops.push({ op: 'set', target, props: { disabled: true } })
    }
  }

  return ops
})

function editable(stage: Stage): StageInput {
  return {
    id: stage.id,
    title: stage.title,
    brief: stage.brief ?? '',
    acceptance: stage.acceptance ?? '',
    review_mode: stage.review_mode,
  }
}

function take(detail: TaskDetail | null): void {
  task.value = detail

  const model: ScreenModel = detail
    ? {
        project_id: projects.value.find((project) => project.slug === detail.project)?.id ?? null,
        title: detail.title,
        priority: detail.priority,
        branch: detail.branch ?? '',
        spec: detail.spec ?? '',
        acceptance: detail.acceptance ?? '',
        stages: detail.stages
          .filter(
            (stage) => detail.status === 'draft' || ['pending', 'ready'].includes(stage.status),
          )
          .map(editable),
      }
    : {
        project_id:
          Number(route.query.project) ||
          projects.value.find((project) => !project.archived)?.id ||
          null,
        title: '',
        priority: 'normal',
        branch: '',
        spec: '',
        acceptance: '',
        stages: [{ title: '', brief: '', acceptance: '', review_mode: 'auto' }],
      }

  values.value = model
  snapshot.value = JSON.stringify(model)
}

async function load(): Promise<void> {
  loading.value = true

  try {
    projects.value = await api.projects()
    take(props.id === null ? null : await api.task(props.id))
  } catch (error) {
    toast.danger(message(error))
    void router.push(props.base)
  } finally {
    loading.value = false
  }
}

/**
 * The stages as the server wants them (§3): in a draft, the list as it stands. In work, every
 * stage by id — the started ones in the places they had, the rest in the order of the editor —
 * and the new ones last.
 */
function stagesToSend(): StageInput[] {
  const rows = (Array.isArray(values.value.stages) ? values.value.stages : []) as StageInput[]
  const clean = (row: StageInput): StageInput => ({
    ...(row.id ? { id: row.id } : {}),
    title: row.title ?? '',
    brief: row.brief || null,
    acceptance: row.acceptance || null,
    review_mode: row.review_mode ?? 'auto',
  })

  if (draft.value) return rows.map(clean)

  const existing = rows.filter((row) => row.id)
  const fresh = rows.filter((row) => !row.id)
  const slots: (StageInput | undefined)[] = []

  for (const stage of locked.value) slots[stage.position - 1] = { id: stage.id }

  let next = 0
  const total = locked.value.length + existing.length

  for (let index = 0; index < total; index += 1) {
    if (slots[index] === undefined) slots[index] = clean(existing[next++]!)
  }

  return [...(slots as StageInput[]), ...fresh.map(clean)]
}

function input(): TaskInput {
  const v = values.value

  if (!draft.value) {
    return { priority: v.priority as TaskInput['priority'], stages: stagesToSend() }
  }

  return {
    project_id: typeof v.project_id === 'number' ? v.project_id : undefined,
    title: String(v.title ?? ''),
    priority: v.priority as TaskInput['priority'],
    branch: v.branch ? String(v.branch) : null,
    spec: v.spec ? String(v.spec) : null,
    acceptance: v.acceptance ? String(v.acceptance) : null,
    stages: stagesToSend(),
  }
}

async function save(): Promise<void> {
  if (saving.value || !canManage) return

  saving.value = true
  errors.value = {}

  try {
    const made = props.id === null
    const detail = made ? await api.create(input()) : await api.save(props.id as number, input())

    // Nothing left to protect: the page is about to be left for the task.
    snapshot.value = current.value
    toast.success(made ? t('panel.created') : t('panel.saved'))
    await router.push(`${props.base}/${detail.id}`)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('panel.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

function leaveGuard(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

function onKeydown(event: KeyboardEvent): void {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
    event.preventDefault()
    void save()
  }
}

useBodyKeys(onKeydown)

onMounted(() => {
  void load()
  window.addEventListener('beforeunload', leaveGuard)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', leaveGuard)
})

onBeforeRouteLeave(async () => {
  if (!dirty.value || !canManage) return true

  return await confirm({
    title: t('panel.leave-title'),
    message: t('panel.leave-text'),
    confirmText: t('panel.leave'),
    cancelText: t('panel.keep'),
    tone: 'danger',
  })
})

const title = computed(() =>
  props.id === null ? t('panel.new') : task.value?.title || t('panel.untitled'),
)
</script>

<template>
  <div class="wx-task-form" @keydown="onKeydown">
    <wx-skeleton v-if="loading" title :rows="8" />

    <template v-else>
      <wx-screen-head
        :back="props.id === null ? props.base : `${props.base}/${props.id}`"
        :back-label="t('panel.back')"
        :title="title"
        :subtitle="task?.ref"
      />

      <wx-alert v-if="props.id === null && projects.length === 0" type="info">
        {{ t('panel.need-project') }}
      </wx-alert>

      <wx-alert v-if="!draft" type="info">{{ t('panel.locked-fields') }}</wx-alert>

      <wx-card v-if="locked.length > 0" :title="t('panel.locked-stages')">
        <wx-text size="sm" tone="muted">{{ t('panel.locked-help') }}</wx-text>
        <ol class="wx-task-form__locked">
          <li v-for="stage in locked" :key="stage.id">
            <wx-text truncate>{{ stage.position }}. {{ stage.title }}</wx-text>
            <wx-badge size="sm" :type="stageTones[stage.status]">
              {{ t(`panel.stage-${stage.status}`) }}
            </wx-badge>
          </li>
        </ol>
      </wx-card>

      <wx-screen
        v-model="values"
        name="tasks.form"
        :patch="patch"
        :errors="errors"
        :disabled="!canManage || saving"
      />

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button
          type="primary"
          :loading="saving"
          :disabled="(!dirty && props.id !== null) || projects.length === 0"
          @click="save"
        >
          {{ t('panel.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-task-form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
  container-type: inline-size;
}

.wx-task-form__locked {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  margin: var(--wx-space-12) 0 0;
  padding: 0;
  list-style: none;
}

.wx-task-form__locked li {
  display: flex;
  gap: var(--wx-space-8);
  align-items: center;
  justify-content: space-between;
  min-width: 0;
}
</style>
