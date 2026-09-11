<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useElementWidth, useResponsiveShell } from '@webx-ui/core'
import MessagePane from './MessagePane.vue'
import MessageRow from './MessageRow.vue'
import { forms, submissions, type Submission, type SubmissionStatus } from './data'

type View = 'new' | 'mine' | 'progress' | 'done' | 'trash'

const ME = 'ОМ'

const shellEl = ref<HTMLElement | null>(null)

/* The admin chrome: full sidebar, icon rail, or a burger — from the shell's width. */
const { layout, collapsed, showAside, drawerOpen, toggle, close } = useResponsiveShell(shellEl, {
  persist: 'playground-inbox',
})

/*
 * The inbox measures what is left after the admin chrome, not the window: the sidebar
 * takes 220px of it, and a reading pane squeezed into 330px is worse than no pane.
 * Below 1080px of its own the column of views folds into a filter; below 780px the
 * message stops being a column and becomes a screen.
 */
const inboxEl = ref<HTMLElement | null>(null)
const inboxWidth = useElementWidth(inboxEl)

const showViews = computed(() => inboxWidth.value === 0 || inboxWidth.value >= 1080)
const paneBesideList = computed(() => inboxWidth.value === 0 || inboxWidth.value >= 780)

const items = ref<Submission[]>(submissions.map((item) => ({ ...item })))
const view = ref<View>('new')
const source = ref('all')
const query = ref('')
const showAllForms = ref(false)
const selectedId = ref<number | null>(submissions[0].id)
const paneOpen = ref(false)

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

function open(item: Submission) {
  selectedId.value = item.id
  item.unread = false
  if (!paneBesideList.value) paneOpen.value = true
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

  if (!visible.value.some((other) => other.id === item.id)) {
    selectedId.value = visible.value[0]?.id ?? null
    if (!paneBesideList.value) paneOpen.value = false
  }
}

/* The pane stops being a screen of its own as soon as there is room for it. */
watch(paneBesideList, (beside) => {
  if (beside) paneOpen.value = false
})

function chooseView(next: View) {
  view.value = next
  close()
  if (!visible.value.some((item) => item.id === selectedId.value)) {
    selectedId.value = visible.value[0]?.id ?? null
  }
}
</script>

<template>
  <div ref="shellEl" class="shell">
    <wx-container full-height>
      <wx-header>
        <wx-action
          :icon="layout === 'drawer' ? 'menu' : 'sidebar'"
          :title="layout === 'drawer' ? 'Меню' : 'Згорнути меню'"
          @click="toggle"
        />
        <strong class="shell__brand">Demo admin</strong>

        <template #end>
          <wx-indicator :value="unread" :max="99">
            <wx-action icon="bell" title="Сповіщення" />
          </wx-indicator>

          <wx-dropdown align="end">
            <template #trigger>
              <wx-button variant="text" size="sm">
                <template #icon><wx-icon name="user" /></template>
                Олег М.
              </wx-button>
            </template>
            <wx-dropdown-item icon="user">Профіль</wx-dropdown-item>
            <wx-dropdown-item icon="settings">Налаштування</wx-dropdown-item>
            <hr />
            <wx-dropdown-item icon="logout" tone="danger">Вийти</wx-dropdown-item>
          </wx-dropdown>
        </template>
      </wx-header>

      <wx-container direction="horizontal">
        <wx-aside v-if="showAside" :collapsed="collapsed" :width="220" scroll>
          <wx-menu model-value="inbox" size="sm" label="Розділи" :collapsed="collapsed">
            <wx-menu-item value="dashboard" icon="home" label="Головна" />
            <wx-menu-item value="inbox" icon="mail" label="Вхідні">
              <template #trailing>
                <wx-badge type="primary" size="sm" round>{{ unread }}</wx-badge>
              </template>
            </wx-menu-item>
            <wx-menu-item value="orders" icon="cart" label="Замовлення" />
            <wx-menu-item value="catalog" icon="grid" label="Каталог" />
            <wx-menu-item value="content" icon="file" label="Контент" />
            <wx-menu-item value="settings" icon="settings" label="Система" />
          </wx-menu>
        </wx-aside>

        <!-- Усе, що лишилось адмінського хрому, і є інбокс: він міряє саме себе. -->
        <div ref="inboxEl" class="inbox">
          <!-- Колонка зрізів: стани, а не форми. -->
          <wx-aside v-if="showViews" class="views" :width="230" scroll>
            <div class="views__head">
              <wx-heading :level="2" size="lg">Вхідні</wx-heading>
              <wx-text size="xs" tone="muted">{{ items.length }}</wx-text>
            </div>

            <wx-menu
              :model-value="view"
              size="sm"
              label="Зрізи"
              @select="(value: string | number) => chooseView(value as View)"
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

            <wx-menu v-model="source" size="sm" label="Джерела">
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

            <wx-text size="xs" tone="placeholder" class="views__note">
              Підписники — окремий розділ: це список, а не листування.
            </wx-text>
          </wx-aside>

          <!-- Список -->
          <div class="list" :class="{ 'list--alone': !paneBesideList }">
            <div class="list__head">
              <wx-input
                v-model="query"
                size="sm"
                clearable
                placeholder="Пошук за ім'ям, телефоном, текстом"
              >
                <template #prefix><wx-icon name="search" /></template>
              </wx-input>

              <wx-dropdown v-if="!showViews" align="start">
                <template #trigger>
                  <wx-button variant="outline" size="sm">
                    <template #icon><wx-icon name="filter" /></template>
                    Зріз
                  </wx-button>
                </template>
                <wx-dropdown-item icon="mail" @click="chooseView('new')">
                  Нові · {{ countOf('new') }}
                </wx-dropdown-item>
                <wx-dropdown-item icon="user" @click="chooseView('mine')">
                  Мої · {{ countOf('mine') }}
                </wx-dropdown-item>
                <wx-dropdown-item icon="clock" @click="chooseView('progress')">
                  В роботі · {{ countOf('progress') }}
                </wx-dropdown-item>
                <wx-dropdown-item icon="check-circle" @click="chooseView('done')">
                  Оброблені · {{ countOf('done') }}
                </wx-dropdown-item>
              </wx-dropdown>
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
                @open="open(item)"
              />

              <div v-if="visible.length === 0" class="list__empty">
                <wx-icon name="mail" :size="40" />
                <wx-text size="sm" tone="muted">{{ emptyText }}</wx-text>
              </div>
            </wx-scrollbar>
          </div>

          <!-- Відкрите звернення -->
          <wx-main v-if="paneBesideList" padding="none" class="pane-column">
            <message-pane
              v-if="selected"
              :item="selected"
              @progress="setStatus(selected, 'progress')"
              @done="setStatus(selected, 'done')"
            />
            <div v-else class="pane-empty">
              <wx-icon name="mail" :size="56" />
              <wx-text tone="muted">Оберіть звернення зі списку</wx-text>
            </div>
          </wx-main>
        </div>
      </wx-container>
    </wx-container>

    <!-- Меню адмінки на вузькому екрані -->
    <wx-drawer v-model:open="drawerOpen" title="Меню" side="left" :size="260" closable>
      <wx-menu model-value="inbox" size="sm" label="Розділи" @select="close">
        <wx-menu-item value="dashboard" icon="home" label="Головна" />
        <wx-menu-item value="inbox" icon="mail" label="Вхідні" />
        <wx-menu-item value="orders" icon="cart" label="Замовлення" />
        <wx-menu-item value="catalog" icon="grid" label="Каталог" />
        <wx-menu-item value="content" icon="file" label="Контент" />
        <wx-menu-item value="settings" icon="settings" label="Система" />
      </wx-menu>

      <wx-divider label="Зрізи" spacing="sm" />

      <wx-menu
        :model-value="view"
        size="sm"
        @select="(value: string | number) => chooseView(value as View)"
      >
        <wx-menu-item value="new" icon="mail" label="Нові">
          <template #trailing>{{ countOf('new') || '' }}</template>
        </wx-menu-item>
        <wx-menu-item value="mine" icon="user" label="Мої" />
        <wx-menu-item value="progress" icon="clock" label="В роботі" />
        <wx-menu-item value="done" icon="check-circle" label="Оброблені" />
      </wx-menu>
    </wx-drawer>

    <!-- Звернення на вузькому екрані: окремий екран, а не колонка -->
    <!-- The way back is the arrow in the pane's own header, so the panel needs no ×. -->
    <wx-drawer
      v-model:open="paneOpen"
      side="right"
      size="100%"
      :closable="false"
      aria-label="Звернення"
      class="pane-drawer"
    >
      <message-pane
        v-if="selected"
        :item="selected"
        show-back
        @back="paneOpen = false"
        @progress="setStatus(selected, 'progress')"
        @done="setStatus(selected, 'done')"
      />
    </wx-drawer>
  </div>
</template>

<style scoped>
.shell {
  height: 100vh;
  overflow: hidden;
}

.shell__brand {
  font-size: var(--wx-font-size-md);
  font-weight: var(--wx-font-weight-semibold);
}

.inbox {
  display: flex;
  flex: 1 1 auto;
  min-width: 0;
  min-height: 0;
}

.views {
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

.list {
  display: flex;
  flex-direction: column;
  width: 380px;
  flex: 0 0 380px;
  min-height: 0;
  background: var(--wx-bg-surface);
  border-right: 1px solid var(--wx-border-default);
  box-sizing: border-box;
}

.list--alone {
  width: auto;
  flex: 1 1 auto;
  /* A row whose badge and phone refuse to wrap must not widen the column. */
  min-width: 0;
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

.pane-column {
  min-width: 0;
}

.pane-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--wx-space-10);
  height: 100%;
  color: var(--wx-border-default);
}

.pane-drawer :deep(.wx-drawer__body) {
  padding: 0;
}
</style>
