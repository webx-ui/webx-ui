<script setup lang="ts">
import { computed, ref } from 'vue'

/**
 * The three-column screen with nothing real in it: a menu narrows the list, the list
 * is rows of filler, and the detail is filler prose. Deliberately generic — the point
 * of `WxListDetail` is that mail, orders and anything else with a list and a record
 * behind it are the same screen.
 */
type View = 'all' | 'open' | 'mine' | 'archive'

interface Entry {
  id: number
  title: string
  excerpt: string
  author: string
  initials: string
  when: string
  view: Exclude<View, 'all'>
  unread: boolean
}

const titles = [
  'Lorem ipsum dolor sit amet',
  'Consectetur adipiscing elit',
  'Integer posuere erat a ante',
  'Venenatis dapibus posuere',
  'Nullam quis risus eget urna',
  'Mollis ornare vel eu leo',
  'Cum sociis natoque penatibus',
  'Nascetur ridiculus mus',
  'Vivamus sagittis lacus',
  'Aenean eu leo quam',
  'Pellentesque ornare sem',
  'Cras mattis consectetur',
]

const authors: [string, string][] = [
  ['Ганна Роут', 'ГР'],
  ['Тарас Кемп', 'ТК'],
  ['Nova Build', 'NB'],
  ['Олег Мороз', 'ОМ'],
]

const views: Exclude<View, 'all'>[] = ['open', 'mine', 'archive']

const entries: Entry[] = titles.map((title, index) => ({
  id: index + 1,
  title,
  excerpt:
    'Donec ullamcorper nulla non metus auctor fringilla. Maecenas sed diam eget risus varius ' +
    'blandit sit amet non magna.',
  author: authors[index % authors.length][0],
  initials: authors[index % authors.length][1],
  when: index < 3 ? `${9 + index}:${20 + index}` : `${24 - index} лип`,
  view: views[index % views.length],
  unread: index < 4,
}))

const view = ref<View>('all')
const query = ref('')
const selectedId = ref<number | null>(entries[0].id)
const open = ref(true)

const visible = computed(() =>
  entries.filter((entry) => {
    if (view.value !== 'all' && entry.view !== view.value) return false
    const needle = query.value.trim().toLowerCase()
    if (!needle) return true
    return `${entry.title} ${entry.author}`.toLowerCase().includes(needle)
  }),
)

const selected = computed(() => entries.find((entry) => entry.id === selectedId.value))

function countOf(name: View) {
  return name === 'all' ? entries.length : entries.filter((entry) => entry.view === name).length
}

function pick(entry: Entry) {
  selectedId.value = entry.id
  open.value = true
}
</script>

<template>
  <wx-list-detail v-model:open="open" :filters-width="240" :list-width="380" :detail-min="420">
    <template #filters="{ close }">
      <div class="records__filters">
        <wx-menu v-model="view" label="Подання" @select="close">
          <wx-menu-item value="all" icon="list" label="Усі записи">
            <template #trailing>{{ countOf('all') }}</template>
          </wx-menu-item>
          <wx-menu-item value="open" icon="mail" label="Відкриті">
            <template #trailing>{{ countOf('open') }}</template>
          </wx-menu-item>
          <wx-menu-item value="mine" icon="user" label="Мої">
            <template #trailing>{{ countOf('mine') }}</template>
          </wx-menu-item>
          <wx-menu-item value="archive" icon="folder" label="Архів">
            <template #trailing>{{ countOf('archive') }}</template>
          </wx-menu-item>
        </wx-menu>

        <wx-divider spacing="sm" />

        <wx-menu label="Мітки">
          <wx-menu-group title="Мітки">
            <wx-menu-item value="tag-lorem" icon="tag" label="Lorem" />
            <wx-menu-item value="tag-ipsum" icon="tag" label="Ipsum" />
            <wx-menu-item value="tag-dolor" icon="tag" label="Dolor" />
          </wx-menu-group>
        </wx-menu>
      </div>
    </template>

    <template #list="{ filtersInline, openFilters }">
      <div class="records__bar">
        <wx-input v-model="query" size="sm" clearable placeholder="Пошук">
          <template #prefix><wx-icon name="search" /></template>
        </wx-input>
        <wx-button v-if="!filtersInline" size="sm" variant="outline" @click="openFilters">
          <template #icon><wx-icon name="filter" /></template>
          Подання
        </wx-button>
      </div>

      <wx-scrollbar class="records__rows">
        <wx-entity-card
          v-for="entry in visible"
          :key="entry.id"
          variant="plain"
          size="sm"
          shape="circle"
          :selected="entry.id === selectedId"
          @click="pick(entry)"
        >
          <template #media>
            <span class="records__avatar">{{ entry.initials }}</span>
          </template>
          <template #title>
            <wx-text :weight="entry.unread ? 'semibold' : 'regular'" truncate>
              {{ entry.title }}
            </wx-text>
          </template>
          <template #meta>
            <wx-text size="xs" tone="muted" truncate>
              {{ entry.author }} · {{ entry.excerpt }}
            </wx-text>
          </template>
          <template #actions>
            <wx-text size="xs" tone="muted">{{ entry.when }}</wx-text>
          </template>
        </wx-entity-card>

        <div v-if="visible.length === 0" class="records__none">
          <wx-icon name="search" :size="28" />
          <wx-text size="sm" tone="muted">Нічого не знайшлося</wx-text>
        </div>
      </wx-scrollbar>
    </template>

    <template #detail="{ inline, back }">
      <div v-if="selected" class="records__detail">
        <div class="records__detail-bar">
          <wx-button v-if="!inline" size="sm" variant="text" @click="back">
            <template #icon><wx-icon name="arrow-left" /></template>
            Назад
          </wx-button>
          <wx-text weight="semibold" truncate>{{ selected.title }}</wx-text>
          <wx-actions class="records__detail-actions" size="sm">
            <wx-action type="edit" />
            <wx-action icon="folder" title="В архів" />
            <wx-action type="remove" />
          </wx-actions>
        </div>

        <wx-scrollbar class="records__detail-body">
          <div class="records__detail-inner">
            <div class="records__from">
              <span class="records__avatar records__avatar--lg">{{ selected.initials }}</span>
              <div>
                <wx-text weight="semibold">{{ selected.author }}</wx-text>
                <wx-text size="xs" tone="muted" as="div">
                  {{ selected.when }} · запис #{{ selected.id }}
                </wx-text>
              </div>
            </div>

            <wx-prose>
              <p>
                Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer posuere erat a ante
                venenatis dapibus posuere velit aliquet. Nullam quis risus eget urna mollis ornare
                vel eu leo.
              </p>
              <p>
                Sed posuere consectetur est at lobortis. Cum sociis natoque penatibus et magnis dis
                parturient montes, nascetur ridiculus mus. Donec ullamcorper nulla non metus auctor
                fringilla. Vivamus sagittis lacus vel augue laoreet rutrum faucibus dolor auctor.
              </p>
              <blockquote>
                Maecenas sed diam eget risus varius blandit sit amet non magna.
              </blockquote>
              <p>
                Curabitur blandit tempus porttitor. Etiam porta sem malesuada magna mollis euismod.
                Donec id elit non mi porta gravida at eget metus. Nulla vitae elit libero, a
                pharetra augue.
              </p>
            </wx-prose>
          </div>
        </wx-scrollbar>
      </div>
    </template>

    <template #empty>
      <wx-icon name="file" :size="36" />
      <wx-text size="sm" tone="muted">Виберіть запис зі списку</wx-text>
    </template>
  </wx-list-detail>
</template>

<style scoped>
.records__filters {
  padding: var(--wx-space-8) 0;
  overflow-y: auto;
}

.records__bar {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-10) var(--wx-space-12);
  border-bottom: 1px solid var(--wx-border-default);
}

.records__bar :deep(.wx-input) {
  flex: 1 1 auto;
  min-width: 0;
}

.records__rows {
  flex: 1 1 auto;
  min-height: 0;
}

.records__avatar {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  background: var(--wx-color-primary-soft);
  color: var(--wx-color-primary);
  border-radius: var(--wx-radius-full);
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-semibold);
}

.records__avatar--lg {
  width: 40px;
  height: 40px;
  font-size: var(--wx-font-size-sm);
}

.records__none {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-40) var(--wx-space-16);
  color: var(--wx-text-muted);
}

.records__detail {
  display: flex;
  flex-direction: column;
  min-height: 0;
  height: 100%;
}

.records__detail-bar {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-10) var(--wx-space-12);
  border-bottom: 1px solid var(--wx-border-default);
}

.records__detail-actions {
  margin-inline-start: auto;
}

.records__detail-body {
  flex: 1 1 auto;
  min-height: 0;
}

.records__detail-inner {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  padding: var(--wx-space-18) var(--wx-space-24);
}

.records__from {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
}
</style>
