<script setup lang="ts">
import { computed, ref } from 'vue'
import MessagePane from './MessagePane.vue'
import MessageRow from './MessageRow.vue'
import { forms, submissions, type Submission, type SubmissionStatus } from './data'

type View = 'new' | 'mine' | 'progress' | 'done' | 'trash'

const ME = 'ОМ'

const items = ref<Submission[]>(submissions.map((item) => ({ ...item })))
const view = ref<View>('new')
const source = ref('all')
const query = ref('')
const showAllForms = ref(false)
const selectedId = ref<number | null>(submissions[0].id)

/* `WxListDetail` reads this as "a record is open"; which record is ours to keep. */
const open = ref(true)

const shownForms = computed(() => (showAllForms.value ? forms : forms.slice(0, 3)))

function inView(item: Submission, name: View) {
  if (name === 'new') return item.status === 'new'
  if (name === 'mine') return item.assignee === ME
  if (name === 'progress') return item.status === 'progress'
  if (name === 'done') return item.status === 'done'
  return false
}

function countOf(name: View) {
  return items.value.filter((item) => inView(item, name)).length
}

const visible = computed(() => {
  const needle = query.value.trim().toLowerCase()

  return items.value.filter((item) => {
    if (!inView(item, view.value)) return false
    if (source.value !== 'all' && item.form !== source.value) return false
    if (!needle) return true

    return `${item.name} ${item.phone} ${item.email} ${item.preview}`.toLowerCase().includes(needle)
  })
})

const selected = computed(() => visible.value.find((item) => item.id === selectedId.value) ?? null)
const unread = computed(() => items.value.filter((item) => item.unread).length)

const formCounts = computed(() => {
  const counts: Record<string, number> = {}
  for (const item of items.value) counts[item.form] = (counts[item.form] ?? 0) + 1
  return counts
})

const emptyText = computed(() => {
  if (query.value.trim()) return 'Нічого не знайшлося'
  if (view.value === 'trash') return 'Порожньо — нічого не видаляли'
  if (view.value === 'mine') return 'На вас нічого не призначено'
  return 'Тут порожньо'
})

function select(item: Submission | null) {
  selectedId.value = item?.id ?? null
  open.value = Boolean(item)
}

function openItem(item: Submission) {
  item.unread = false
  select(item)
}

/**
 * Closing a request takes it out of the view it was read in, the way an archived mail
 * leaves the inbox — so the next one opens by itself instead of leaving an empty pane.
 */
function setStatus(item: Submission, status: SubmissionStatus) {
  item.status = status
  item.unread = false

  if (status === 'progress') {
    item.assignee = ME
    item.log = { who: ME, text: 'Олег М. взяв у роботу · щойно' }
  }
  if (status === 'done') {
    item.assignee = item.assignee ?? ME
    item.log = { who: item.assignee, text: 'Звернення закрито · щойно' }
  }

  if (!visible.value.some((other) => other.id === item.id)) select(visible.value[0] ?? null)
}

function chooseView(next: View, closeFilters?: () => void) {
  view.value = next
  closeFilters?.()
  if (!visible.value.some((item) => item.id === selectedId.value)) select(visible.value[0] ?? null)
}
</script>

<template>
  <wx-list-detail
    v-model:open="open"
    :list-width="380"
    :filters-width="240"
    filters-title="Зрізи"
    detail-label="Звернення"
  >
    <!-- Колонка зрізів: стани, а не форми. -->
    <template #filters="{ inline, close }">
      <wx-scrollbar class="views">
        <div v-if="inline" class="views__head">
          <wx-heading :level="2" size="lg">Вхідні</wx-heading>
          <wx-text size="xs" tone="muted">{{ items.length }}</wx-text>
        </div>

        <wx-menu
          :model-value="view"
          label="Зрізи"
          @select="(value: string | number) => chooseView(value as View, close)"
        >
          <wx-menu-item value="new" icon="mail" label="Нові">
            <template #trailing>{{ countOf('new') || '' }}</template>
          </wx-menu-item>
          <wx-menu-item value="mine" icon="user" label="Мої">
            <template #trailing>{{ countOf('mine') || '' }}</template>
          </wx-menu-item>
          <wx-menu-item value="progress" icon="clock" label="В роботі">
            <template #trailing>{{ countOf('progress') || '' }}</template>
          </wx-menu-item>
          <wx-menu-item value="done" icon="check-circle" label="Оброблені">
            <template #trailing>{{ countOf('done') || '' }}</template>
          </wx-menu-item>
          <wx-menu-item value="trash" icon="trash" label="Спам і кошик" />
        </wx-menu>

        <wx-divider spacing="sm" />

        <wx-menu v-model="source" label="Джерела">
          <wx-menu-group title="Джерела">
            <wx-menu-item value="all" label="Усі форми">
              <template #trailing>{{ items.length }}</template>
            </wx-menu-item>
            <wx-menu-item
              v-for="form in shownForms"
              :key="form.id"
              :value="form.id"
              :label="form.label"
              :icon="form.icon"
            >
              <template #trailing>{{ formCounts[form.id] || '' }}</template>
            </wx-menu-item>
            <wx-menu-item value="__more" @click="showAllForms = !showAllForms">
              <wx-text size="sm" tone="muted">
                {{ showAllForms ? 'Згорнути' : `Ще ${forms.length - 3} форми…` }}
              </wx-text>
            </wx-menu-item>
          </wx-menu-group>
        </wx-menu>

        <wx-text v-if="inline" size="xs" tone="placeholder" class="views__note">
          Підписники — окремий розділ: це список, а не листування.
        </wx-text>
      </wx-scrollbar>
    </template>

    <template #list="{ filtersInline, openFilters }">
      <div class="list__head">
        <wx-input
          v-model="query"
          size="sm"
          clearable
          placeholder="Пошук за ім'ям, телефоном, текстом"
        >
          <template #prefix><wx-icon name="search" /></template>
        </wx-input>

        <wx-button v-if="!filtersInline" variant="outline" size="sm" @click="openFilters">
          <template #icon><wx-icon name="filter" /></template>
          Зріз
        </wx-button>
      </div>

      <div class="list__summary">
        <wx-text size="xs" tone="muted">
          {{ unread }} непрочитаних · {{ visible.length }} у списку
        </wx-text>
        <wx-text size="xs" tone="muted">Спершу нові</wx-text>
      </div>

      <wx-scrollbar class="list__scroll">
        <message-row
          v-for="item in visible"
          :key="item.id"
          :item="item"
          :selected="item.id === selectedId"
          @open="openItem(item)"
        />

        <div v-if="visible.length === 0" class="list__empty">
          <wx-icon name="mail" :size="40" />
          <wx-text size="sm" tone="muted">{{ emptyText }}</wx-text>
        </div>
      </wx-scrollbar>
    </template>

    <template #detail="{ inline, back }">
      <message-pane
        v-if="selected"
        :item="selected"
        :show-back="!inline"
        @back="back"
        @progress="setStatus(selected, 'progress')"
        @done="setStatus(selected, 'done')"
      />
    </template>

    <template #empty>
      <wx-icon name="mail" :size="56" />
      <wx-text tone="muted">Оберіть звернення зі списку</wx-text>
    </template>
  </wx-list-detail>
</template>

<style scoped>
.views {
  flex: 1 1 auto;
  min-height: 0;
  padding: var(--wx-space-8);
  box-sizing: border-box;
}

.views__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--wx-space-6) var(--wx-space-10) var(--wx-space-10);
}

.views__note {
  display: block;
  padding: var(--wx-space-10);
}

.list__head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-10) var(--wx-space-12);
  border-bottom: 1px solid var(--wx-border-muted);
}

.list__head :deep(.wx-input) {
  flex: 1 1 auto;
}

.list__summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--wx-space-8) var(--wx-space-14);
}

.list__scroll {
  flex: 1 1 auto;
  min-height: 0;
}

.list__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-64) var(--wx-space-24);
  color: var(--wx-border-default);
  text-align: center;
}
</style>
