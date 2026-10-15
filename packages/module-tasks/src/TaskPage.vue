<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  createModal,
  toast,
  WxAccordion,
  WxAccordionItem,
  WxAlert,
  WxBadge,
  WxButton,
  WxCard,
  WxSkeleton,
  WxText,
  WxTextarea,
  WxTimeline,
  WxTimelineItem,
  type AccordionModelValue,
} from '@webx-ui/core'
import TasksMarkdown from './TasksMarkdown.vue'
import TasksTextDialog from './TasksTextDialog.vue'
import { createTasksApi } from './api'
import { useTasksMessages } from './i18n'
import { priorityTones, stageTones, taskTones } from './tones'
import type { JournalEntry, Stage, TaskDetail } from './types'

/**
 * One task (§6.2): what it is for, its stages in order, and everything that happened to it.
 *
 * The buttons are the moves §3 allows at the status the task or stage is in, offered only to
 * whoever may make them — accepting and sending back to a reviewer, answering an agent's question
 * and cancelling to whoever plans, releasing a hung stage to whoever plans or works. A move the
 * server still refuses (somebody got there first) comes back as its reason, and the task is read
 * again, because the screen is then out of date.
 *
 * The stage that waits for somebody is opened on arrival: on a task with six stages it is the one
 * the person came for.
 */
defineOptions({ name: 'WxTaskPage' })

const props = withDefaults(defineProps<{ id: number; base?: string }>(), { base: '/tasks' })

const context = useAdmin()
const api = createTasksApi(context)
const router = useRouter()
useTasksMessages()

const t = useTranslate('webx-tasks')
const message = useErrorText()

const task = ref<TaskDetail | null>(null)
const loading = ref(true)
const busy = ref(false)
const open = ref<AccordionModelValue>([])
const comment = ref('')

const canManage = context.can('tasks.manage')
const canReview = context.can('tasks.review')
const canWork = context.can('tasks.work')
const canComment = canManage || canReview || canWork

const ask = createModal<string, InstanceType<typeof TasksTextDialog>['$props']>(TasksTextDialog)

const closed = computed(() => task.value?.status === 'done' || task.value?.status === 'cancelled')

/** The stages a person may be waiting on, first — what is opened on arrival. */
function waiting(detail: TaskDetail): Stage | undefined {
  return (
    detail.stages.find((stage) => stage.status === 'review' || stage.status === 'blocked') ??
    detail.stages.find((stage) => !['done', 'cancelled', 'pending'].includes(stage.status))
  )
}

function take(detail: TaskDetail): void {
  task.value = detail

  if ((open.value as string[]).length === 0) {
    const first = waiting(detail)
    open.value = first ? [String(first.id)] : []
  }
}

async function load(): Promise<void> {
  loading.value = true

  try {
    take(await api.task(props.id))
  } catch (error) {
    toast.danger(message(error))
    void router.push(props.base)
  } finally {
    loading.value = false
  }
}

watch(() => props.id, load, { immediate: true })

/**
 * A move, its toast, and the task as the server has it afterwards. A refusal reloads too: the
 * screen was showing a task that is not there any more.
 */
async function act(work: () => Promise<TaskDetail>, said: string): Promise<void> {
  if (busy.value) return

  busy.value = true

  try {
    take(await work())
    toast.success(said)
    // The badge in the menu counts what waits for a person; this may have changed it.
    void context.refreshManifest()
  } catch (error) {
    toast.danger(message(error))
    await load()
  } finally {
    busy.value = false
  }
}

async function start(): Promise<void> {
  const current = task.value
  if (current) await act(() => api.ready(current.id), t('panel.started'))
}

async function cancelTask(): Promise<void> {
  const current = task.value
  if (!current) return

  const reason = await ask({
    title: t('panel.cancel-task-title', { title: current.title }),
    message: t('panel.cancel-task-text'),
    label: t('panel.note'),
    confirmText: t('panel.cancel-task'),
    cancelText: t('panel.keep'),
    danger: true,
  })

  if (reason !== null && reason !== undefined) {
    await act(() => api.cancel(current.id, reason), t('panel.cancelled'))
  }
}

async function accept(stage: Stage): Promise<void> {
  const agreed = await confirm({
    title: t('panel.accept-title', { stage: stage.title }),
    message: t('panel.accept-text'),
    confirmText: t('panel.accept'),
    cancelText: t('panel.keep'),
  })

  if (agreed) await act(() => api.accept(stage.id), t('panel.accepted'))
}

async function rework(stage: Stage): Promise<void> {
  const remarks = await ask({
    title: t('panel.rework-title', { stage: stage.title }),
    label: t('panel.rework-label'),
    confirmText: t('panel.rework'),
    cancelText: t('panel.keep'),
    required: true,
  })

  if (remarks) await act(() => api.rework(stage.id, remarks), t('panel.reworked'))
}

async function answer(stage: Stage): Promise<void> {
  const text = await ask({
    title: t('panel.answer-title'),
    message: question(stage)?.body ?? undefined,
    label: t('panel.answer-label'),
    confirmText: t('panel.send'),
    cancelText: t('panel.keep'),
    required: true,
  })

  if (text) await act(() => api.unblock(stage.id, text), t('panel.answered'))
}

async function release(stage: Stage): Promise<void> {
  const agreed = await confirm({
    title: t('panel.release-title', { stage: stage.title }),
    message: t('panel.release-text'),
    confirmText: t('panel.release'),
    cancelText: t('panel.keep'),
    tone: 'danger',
  })

  if (agreed) await act(() => api.release(stage.id), t('panel.released'))
}

async function cancelStage(stage: Stage): Promise<void> {
  const agreed = await confirm({
    title: t('panel.cancel-stage-title', { stage: stage.title }),
    message: t('panel.cancel-stage-text'),
    confirmText: t('panel.cancel-stage'),
    cancelText: t('panel.keep'),
    tone: 'danger',
  })

  if (agreed) await act(() => api.cancelStage(stage.id), t('panel.stage-cancelled'))
}

async function send(): Promise<void> {
  const current = task.value
  const body = comment.value.trim()

  if (!current || body === '') return

  await act(() => api.comment(current.id, body), t('panel.commented'))
  comment.value = ''
}

/** The last question asked on a stage, when it is waiting for its answer. */
function question(stage: Stage): JournalEntry | undefined {
  return [...(task.value?.journal ?? [])]
    .reverse()
    .find((entry) => entry.stage_id === stage.id && entry.kind === 'question')
}

/** Who may do what to this stage now (§3, §5). */
function moves(stage: Stage): { key: string; label: string; primary?: boolean; run: () => void }[] {
  if (closed.value) return []

  const list: { key: string; label: string; primary?: boolean; run: () => void }[] = []

  if (stage.status === 'review' && canReview) {
    list.push({
      key: 'accept',
      label: t('panel.accept'),
      primary: true,
      run: () => void accept(stage),
    })
    list.push({ key: 'rework', label: t('panel.rework'), run: () => void rework(stage) })
  }

  if (stage.status === 'blocked' && canManage) {
    list.push({
      key: 'answer',
      label: t('panel.answer'),
      primary: true,
      run: () => void answer(stage),
    })
  }

  if (stage.status === 'in_progress' && (canManage || canWork)) {
    list.push({ key: 'release', label: t('panel.release'), run: () => void release(stage) })
  }

  if (canManage && !['done', 'cancelled'].includes(stage.status)) {
    list.push({ key: 'cancel', label: t('panel.cancel-stage'), run: () => void cancelStage(stage) })
  }

  return list
}

function stageNumber(entry: JournalEntry): number | null {
  return task.value?.stages.find((stage) => stage.id === entry.stage_id)?.position ?? null
}

/** What a line of the journal says happened, in the reader's words. */
function said(entry: JournalEntry): string {
  const from = typeof entry.meta?.from === 'string' ? entry.meta.from : null
  const to = typeof entry.meta?.to === 'string' ? entry.meta.to : null

  if (entry.kind === 'status' && to !== null) {
    const words = (status: string) =>
      entry.stage_id === null ? t(`panel.status-${status}`) : t(`panel.stage-${status}`)

    return from === null ? words(to) : `${words(from)} → ${words(to)}`
  }

  return t(`panel.kind-${entry.kind}`)
}

const actions = computed<ScreenAction[]>(() => {
  const current = task.value

  if (!current || !canManage) return []

  const list: ScreenAction[] = []

  if (current.status === 'draft') {
    list.push({
      key: 'start',
      label: t('panel.start'),
      icon: 'play',
      primary: true,
      run: () => void start(),
    })
  }

  if (!closed.value) {
    list.push({
      key: 'edit',
      label: t('panel.edit'),
      icon: 'edit',
      run: () => void router.push(`${props.base}/${current.id}/edit`),
    })
    list.push({
      key: 'cancel',
      label: t('panel.cancel-task'),
      icon: 'close',
      danger: true,
      menu: true,
      run: () => void cancelTask(),
    })
  }

  return list
})
</script>

<template>
  <div class="wx-task">
    <wx-skeleton v-if="loading && !task" title :rows="8" />

    <template v-else-if="task">
      <wx-screen-head
        :back="props.base"
        :back-label="t('panel.back')"
        :title="task.title || t('panel.untitled')"
        :subtitle="task.ref"
        :actions="actions"
      >
        <template #title-after>
          <wx-badge :type="taskTones[task.status]">{{ t(`panel.status-${task.status}`) }}</wx-badge>
        </template>
      </wx-screen-head>

      <wx-card>
        <div class="wx-task__about">
          <div class="wx-task__facts">
            <wx-badge variant="outline" :type="priorityTones[task.priority]">
              {{ t(`panel.priority-${task.priority}`) }}
            </wx-badge>
            <wx-text v-if="task.branch" size="sm" tone="muted" mono>
              {{ t('panel.branch') }}: {{ task.branch }}
            </wx-text>
          </div>

          <section class="wx-task__section">
            <wx-text as="h3" weight="semibold">{{ t('panel.spec') }}</wx-text>
            <tasks-markdown :source="task.spec" :empty="t('panel.no-spec')" />
          </section>

          <section v-if="task.acceptance" class="wx-task__section">
            <wx-text as="h3" weight="semibold">{{ t('panel.acceptance') }}</wx-text>
            <tasks-markdown :source="task.acceptance" />
          </section>
        </div>
      </wx-card>

      <wx-card :title="t('panel.stages')">
        <wx-accordion v-model="open" multiple>
          <wx-accordion-item
            v-for="stage in task.stages"
            :key="stage.id"
            :value="String(stage.id)"
            :title="`${stage.position}. ${stage.title}`"
            :subtitle="
              [
                stage.assignee,
                stage.attempt > 1 ? t('panel.attempt', { attempt: stage.attempt }) : null,
                stage.review_mode === 'human' ? t('panel.by-person') : null,
              ]
                .filter(Boolean)
                .join(' · ')
            "
          >
            <template #extra>
              <wx-badge size="sm" :type="stageTones[stage.status]">
                {{ t(`panel.stage-${stage.status}`) }}
              </wx-badge>
            </template>

            <div class="wx-task__stage">
              <wx-alert
                v-if="stage.status === 'blocked' && question(stage)"
                type="warning"
                :title="t('panel.question')"
              >
                <tasks-markdown :source="question(stage)?.body" />
              </wx-alert>

              <section class="wx-task__section">
                <wx-text as="h4" size="sm" weight="semibold">{{ t('panel.brief') }}</wx-text>
                <tasks-markdown :source="stage.brief" empty="—" />
              </section>

              <section v-if="stage.acceptance" class="wx-task__section">
                <wx-text as="h4" size="sm" weight="semibold">
                  {{ t('panel.stage-acceptance') }}
                </wx-text>
                <tasks-markdown :source="stage.acceptance" />
              </section>

              <section class="wx-task__section">
                <wx-text as="h4" size="sm" weight="semibold">{{ t('panel.report') }}</wx-text>
                <tasks-markdown :source="stage.report" :empty="t('panel.no-report')" />
                <wx-text v-if="stage.result_ref" size="sm" mono class="wx-task__result">
                  {{ t('panel.result') }}: {{ stage.result_ref }}
                </wx-text>
              </section>

              <div v-if="moves(stage).length > 0" class="wx-task__moves">
                <wx-button
                  v-for="move in moves(stage)"
                  :key="move.key"
                  size="sm"
                  :type="move.primary ? 'primary' : 'default'"
                  :variant="move.primary ? undefined : 'outline'"
                  :disabled="busy"
                  @click="move.run"
                >
                  {{ move.label }}
                </wx-button>
              </div>
            </div>
          </wx-accordion-item>
        </wx-accordion>
      </wx-card>

      <wx-card :title="t('panel.journal')">
        <div v-if="canComment && !closed" class="wx-task__comment">
          <wx-textarea
            v-model="comment"
            :rows="2"
            :placeholder="t('panel.comment-placeholder')"
            :aria-label="t('panel.comment')"
          />
          <wx-button size="sm" :disabled="busy || comment.trim() === ''" @click="send">
            {{ t('panel.comment') }}
          </wx-button>
        </div>

        <wx-text v-if="task.journal.length === 0" tone="muted">{{
          t('panel.journal-empty')
        }}</wx-text>

        <wx-timeline v-else size="sm">
          <wx-timeline-item
            v-for="entry in [...task.journal].reverse()"
            :key="entry.id"
            :type="entry.actor_type === 'agent' ? 'primary' : undefined"
            :hollow="entry.actor_type === 'system'"
          >
            <div class="wx-task__entry">
              <div class="wx-task__entry-head">
                <wx-text size="sm" weight="medium">
                  {{ entry.actor_type === 'system' ? t('panel.system') : entry.actor_name }}
                </wx-text>
                <wx-text size="sm" tone="muted">
                  {{ said(entry) }}
                  <template v-if="stageNumber(entry) !== null"
                    >· #{{ stageNumber(entry) }}</template
                  >
                </wx-text>
                <wx-date :value="entry.created_at" compact size="sm" tone="muted" />
              </div>
              <!-- A report is read on its stage; here it would be the same pages twice. -->
              <tasks-markdown v-if="entry.body && entry.kind !== 'report'" :source="entry.body" />
            </div>
          </wx-timeline-item>
        </wx-timeline>
      </wx-card>
    </template>
  </div>
</template>

<style scoped>
.wx-task {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
  container-type: inline-size;
}

.wx-task__about,
.wx-task__stage {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-task__facts {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-12);
  align-items: center;
}

.wx-task__section {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-task__section > :first-child {
  margin: 0;
}

.wx-task__result {
  overflow-wrap: anywhere;
}

.wx-task__moves {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}

.wx-task__comment {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  align-items: flex-end;
  margin-block-end: var(--wx-space-16);
}

.wx-task__entry {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-task__entry-head {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  align-items: baseline;
}
</style>
