<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  createModal,
  toast,
  WxBadge,
  WxButton,
  WxCard,
  WxSkeleton,
  WxSortableList,
  WxText,
} from '@webx-ui/core'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxListScreen,
  WxRowMenu,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
import ConsequencesDialog from './ConsequencesDialog.vue'
import PublicStatusDialog from './PublicStatusDialog.vue'
import RemoveGroupDialog from './RemoveGroupDialog.vue'
import StageDialog from './StageDialog.vue'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import { toneOf } from './tones'
import type {
  BoardGroup,
  BoardStatus,
  DeletedStatus,
  StatusArrangement,
  StatusBoardData,
  StatusConsequences,
} from './types'

/**
 * «Orders → Statuses» (spec §5.6, §11.4): what the buyer sees on one side, the stages of the shop
 * grouped under it on the other, with the orders standing in each.
 *
 * Both are dragged. The order of a list is saved as soon as it is dropped; a stage dropped into
 * another group is bound to it, and since its buyers then see another name — at once, without a
 * message — the server says how many first and the move waits for «Move». Deleting is soft and
 * said first too: the orders stay where they are, and «Deleted» at the bottom brings it back.
 * Behind `orders.statuses` alone.
 */
defineOptions({ name: 'WxOrdersStatusesPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/orders' })

const admin = useAdmin()
const router = useRouter()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const board = ref<StatusBoardData | null>(null)
const groups = ref<BoardGroup[]>([])
const loading = ref(true)

const canManage = computed(() => admin.can('orders.statuses'))

const stageDialog = createModal<
  StatusBoardData,
  {
    status: BoardStatus | null
    groups: BoardGroup[]
    groupId?: number
    colors: StatusBoardData['colors']
    milestones: StatusBoardData['milestones']
  }
>(StageDialog)
const publicDialog = createModal<StatusBoardData, { group: BoardGroup | null }>(PublicStatusDialog)
const removeGroup = createModal<StatusBoardData, { group: BoardGroup; groups: BoardGroup[] }>(
  RemoveGroupDialog,
)
const consequences = createModal<
  StatusBoardData,
  {
    title: string
    confirmText: string
    tone?: 'primary' | 'danger'
    preview: () => Promise<StatusConsequences>
    apply: () => Promise<StatusBoardData>
  }
>(ConsequencesDialog)

function take(answer: StatusBoardData): void {
  board.value = answer
  // Copies, so a drag rewrites these and not the answer a cancelled move goes back to.
  groups.value = answer.groups.map((group) => ({ ...group, statuses: [...group.statuses] }))
}

async function load(): Promise<void> {
  try {
    take(await api.statuses())
  } catch (failure) {
    toast.danger(message(failure))
  } finally {
    loading.value = false
  }
}

onMounted(() => void load())

/* ------------------------------------------------------------------------- dragging ----- */

function arrangement(): StatusArrangement {
  return {
    publics: groups.value.map((group) => group.id),
    groups: Object.fromEntries(
      groups.value.map((group) => [group.id, group.statuses.map((status) => status.id)]),
    ),
  }
}

let pending = false

/**
 * A drop between two lists rewrites both of them, one after the other: the arrangement is read
 * once both are done.
 */
function dropped(): void {
  if (pending) return
  pending = true

  setTimeout(() => {
    pending = false
    void arrange()
  }, 0)
}

async function arrange(): Promise<void> {
  const wanted = arrangement()

  try {
    const said = await api.previewArrange(wanted)

    if (said.refusal) {
      toast.danger(said.message ?? t('panel.statuses.reorder-failed'))
      if (board.value) take(board.value)

      return
    }

    if (said.rebound.length === 0) {
      take(await api.arrange(wanted))

      return
    }

    const answer = await consequences({
      title: t('panel.statuses.rebind-title'),
      confirmText: t('panel.statuses.move'),
      preview: async () => said,
      apply: () => api.arrange(wanted),
    })

    if (answer) take(answer)
    else if (board.value) take(board.value)
  } catch (failure) {
    toast.danger(message(failure, t('panel.statuses.reorder-failed')))
    await load()
  }
}

/* -------------------------------------------------------------------------- dialogs ----- */

async function editStage(status: BoardStatus | null, groupId?: number): Promise<void> {
  if (!board.value) return

  const answer = await stageDialog({
    status,
    groups: board.value.groups,
    groupId,
    colors: board.value.colors,
    milestones: board.value.milestones,
  })

  if (answer) {
    take(answer)
    toast.success(t('panel.saved'))
  }
}

async function editGroup(group: BoardGroup | null): Promise<void> {
  const answer = await publicDialog({ group })

  if (answer) {
    take(answer)
    toast.success(t('panel.saved'))
  }
}

async function deleteStage(status: BoardStatus): Promise<void> {
  const answer = await consequences({
    title: t('panel.statuses.delete-title', { status: status.label }),
    confirmText: t('panel.statuses.delete'),
    tone: 'danger',
    preview: () => api.previewRemoveStage(status.id),
    apply: () => api.removeStage(status.id),
  })

  if (answer) {
    take(answer)
    toast.success(t('panel.saved'))
  }
}

async function deleteGroup(group: BoardGroup): Promise<void> {
  const answer = await removeGroup({ group, groups: board.value?.groups ?? [] })

  if (answer) {
    take(answer)
    toast.success(t('panel.saved'))
  }
}

async function restore(run: () => Promise<StatusBoardData>): Promise<void> {
  try {
    take(await run())
    toast.success(t('panel.statuses.restored'))
  } catch (failure) {
    const errors = (failure as { errors?: Record<string, string[]> }).errors

    toast.danger(errors?.status?.[0] ?? message(failure))
  }
}

/* ---------------------------------------------------------------------------- words ----- */

const milestoneLabel = (key: string | null) =>
  board.value?.milestones.find((one) => one.value === key)?.label ?? ''

const isInitial = (status: BoardStatus) => board.value?.initial.includes(status.id) ?? false

function stageActions(status: BoardStatus): RowAction[] {
  if (!canManage.value) return []

  return [
    {
      key: 'edit',
      icon: 'edit',
      label: t('panel.statuses.edit'),
      run: () => void editStage(status),
    },
    {
      key: 'delete',
      icon: 'trash',
      label: t('panel.statuses.delete'),
      danger: true,
      run: () => void deleteStage(status),
    },
  ]
}

function groupActions(group: BoardGroup): RowAction[] {
  if (!canManage.value) return []

  return [
    {
      key: 'edit',
      icon: 'edit',
      label: t('panel.statuses.edit'),
      run: () => void editGroup(group),
    },
    {
      key: 'add',
      icon: 'plus',
      label: t('panel.statuses.add-here'),
      run: () => void editStage(null, group.id),
    },
    {
      key: 'delete',
      icon: 'trash',
      label: t('panel.statuses.delete'),
      danger: true,
      run: () => void deleteGroup(group),
    },
  ]
}

const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [
        {
          key: 'new-status',
          label: t('panel.statuses.new-status'),
          icon: 'plus',
          primary: true,
          run: () => void editStage(null),
        },
        {
          key: 'new-public',
          label: t('panel.statuses.new-public'),
          icon: 'plus',
          run: () => void editGroup(null),
        },
        {
          key: 'settings',
          label: t('panel.to-settings'),
          icon: 'settings',
          run: () => void router.push(`${props.base}/settings`),
        },
      ]
    : [],
)

const deleted = computed(() => board.value?.deleted ?? { groups: [], statuses: [] })
const hasDeleted = computed(
  () => deleted.value.groups.length > 0 || deleted.value.statuses.length > 0,
)

const deletedWhere = (status: DeletedStatus) =>
  status.public.deleted
    ? `${status.public.name} · ${t('panel.statuses.deleted-group')}`
    : status.public.name
</script>

<template>
  <div class="wx-orders-statuses">
    <wx-list-screen
      :title="t('panel.statuses.title')"
      :back="props.base"
      :back-label="t('module.orders')"
      :actions="actions"
      :card="false"
    >
      <p class="wx-orders-statuses__help">{{ t('panel.statuses.help') }}</p>

      <wx-card v-if="loading"><wx-skeleton :rows="6" /></wx-card>

      <div v-else class="wx-orders-statuses__board">
        <wx-sortable-list
          v-model="groups"
          :title="t('panel.statuses.public')"
          item-key="id"
          item-label="label"
          :drag-label="t('panel.statuses.reorder')"
          :disabled="!canManage"
          :aria-label="t('panel.statuses.public')"
          @move="dropped"
        >
          <template #default="{ item }">
            <div class="wx-orders-statuses__row">
              <wx-text weight="medium" truncate>{{ item.label }}</wx-text>
              <wx-text size="sm" tone="muted" truncate>
                <code>{{ item.code }}</code>
                · {{ t('panel.statuses.orders', { count: item.orders ?? 0 }) }}
              </wx-text>
            </div>
          </template>
          <template #actions="{ item }">
            <wx-row-menu :actions="groupActions(item)" :label="item.label" />
          </template>
        </wx-sortable-list>

        <div class="wx-orders-statuses__stages">
          <wx-text size="sm" tone="muted" weight="medium">
            {{ t('panel.statuses.internal') }}
          </wx-text>

          <wx-sortable-list
            v-for="group in groups"
            :key="group.id"
            v-model="group.statuses"
            group="wx-orders-stages"
            item-key="id"
            item-label="label"
            :title="group.label"
            :drag-label="t('panel.statuses.reorder')"
            :disabled="!canManage"
            :empty-text="t('panel.statuses.empty-group')"
            :aria-label="group.label"
            @update:model-value="dropped"
          >
            <template v-if="canManage" #extra>
              <wx-button
                size="sm"
                variant="text"
                icon="plus"
                :aria-label="t('panel.statuses.add-here')"
                @click="editStage(null, group.id)"
              >
                {{ t('panel.statuses.add-here') }}
              </wx-button>
            </template>

            <template #default="{ item }">
              <div class="wx-orders-statuses__row">
                <span class="wx-orders-statuses__name">
                  <wx-badge :type="toneOf(item.color)" dot size="sm">{{ item.label }}</wx-badge>
                </span>
                <wx-text size="sm" tone="muted" class="wx-orders-statuses__meta">
                  <span v-if="isInitial(item)">{{ t('panel.statuses.initial') }}</span>
                  <span v-if="item.milestone">
                    {{ t('panel.statuses.milestone') }}: {{ milestoneLabel(item.milestone) }}
                  </span>
                  <span v-if="!item.active">{{ t('panel.statuses.off') }}</span>
                  <span>{{ t('panel.statuses.orders', { count: item.orders }) }}</span>
                </wx-text>
              </div>
            </template>
            <template #actions="{ item }">
              <wx-row-menu :actions="stageActions(item)" :label="item.label" />
            </template>
          </wx-sortable-list>
        </div>

        <wx-card
          v-if="hasDeleted"
          :title="t('panel.statuses.deleted')"
          class="wx-orders-statuses__deleted"
        >
          <p class="wx-orders-statuses__help wx-orders-statuses__help--card">
            {{ t('panel.statuses.deleted-help') }}
          </p>

          <ul class="wx-orders-statuses__list">
            <li
              v-for="group in deleted.groups"
              :key="`g${group.id}`"
              class="wx-orders-statuses__gone"
            >
              <div class="wx-orders-statuses__row">
                <wx-text weight="medium" truncate>{{ group.label }}</wx-text>
                <wx-text size="sm" tone="muted"
                  ><code>{{ group.code }}</code></wx-text
                >
              </div>
              <wx-button
                v-if="canManage"
                size="sm"
                variant="outline"
                @click="restore(() => api.restorePublic(group.id))"
              >
                {{ t('panel.statuses.restore') }}
              </wx-button>
            </li>
            <li
              v-for="status in deleted.statuses"
              :key="`s${status.id}`"
              class="wx-orders-statuses__gone"
            >
              <div class="wx-orders-statuses__row">
                <span class="wx-orders-statuses__name">
                  <wx-badge :type="toneOf(status.color)" dot size="sm">{{ status.label }}</wx-badge>
                </span>
                <wx-text size="sm" tone="muted" class="wx-orders-statuses__meta">
                  <span>{{ deletedWhere(status) }}</span>
                  <span>{{ t('panel.statuses.orders', { count: status.orders }) }}</span>
                </wx-text>
              </div>
              <wx-button
                v-if="canManage"
                size="sm"
                variant="outline"
                @click="restore(() => api.restoreStage(status.id))"
              >
                {{ t('panel.statuses.restore') }}
              </wx-button>
            </li>
          </ul>
        </wx-card>
      </div>
    </wx-list-screen>
  </div>
</template>

<style scoped>
.wx-orders-statuses {
  container-type: inline-size;
}

.wx-orders-statuses__board {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--wx-gap, var(--wx-space-16));
  align-items: start;
}

/* The two levels side by side once the panel is wide enough for both to read. */
@container (min-width: 900px) {
  .wx-orders-statuses__board {
    grid-template-columns: minmax(0, 2fr) minmax(0, 3fr);
  }

  .wx-orders-statuses__deleted {
    grid-column: 1 / -1;
  }
}

.wx-orders-statuses__stages {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-orders-statuses__row {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  flex: 1 1 auto;
  min-width: 0;
}

.wx-orders-statuses__name {
  display: inline-flex;
  min-width: 0;
}

.wx-orders-statuses__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0 var(--wx-space-8);
}

.wx-orders-statuses__meta > span + span::before {
  content: '·';
  margin-inline-end: var(--wx-space-8);
}

.wx-orders-statuses__help {
  margin: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-orders-statuses__help--card {
  margin-block-end: var(--wx-space-12);
}

.wx-orders-statuses__list {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-orders-statuses__gone {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
}
</style>
