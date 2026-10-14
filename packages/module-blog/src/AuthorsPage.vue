<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  toast,
  WxAvatar,
  WxBadge,
  WxCard,
  WxEmpty,
  WxIcon,
  WxInput,
  WxListDetail,
  WxSkeleton,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate, WxListScreen } from '@webx-ui/module-admin'
import AuthorPane from './AuthorPane.vue'
import { createAuthorsApi, type AuthorRow } from './authors'
import { useBlogMessages } from './i18n'

/**
 * The authors: every profile on the left, the one being written on the right (§19.5 of the blog
 * spec) — the shape of the team, without its order and its bin: the people who write one site are
 * a short list, and a profile that goes takes nothing with it but a name on a page.
 *
 * The search and the open profile live in the address, so a link to an author — the one the panel
 * on the site and the article editor make — is a link to them in their list.
 */
withDefaults(defineProps<{ base?: string }>(), { base: '/blog' })

const context = useAdmin()
const api = createAuthorsApi(context)
const route = useRoute()
const router = useRouter()
useBlogMessages()

const t = useTranslate('webx-blog')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const rows = ref<AuthorRow[]>([])
const loading = ref(true)

const canManage = computed(() => context.can('blog.authors.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'authors')?.title ??
    t('module.authors'),
)

const q = computed(() => (typeof route.query.q === 'string' ? route.query.q : ''))

/* What is typed, ahead of the address: the address follows it after a pause. */
const typed = ref(q.value)

/** The open profile: a number, `'new'` for one not yet saved, or nothing. */
const selected = computed<number | 'new' | null>(() => {
  const raw = route.query.author

  if (raw === 'new') return canManage.value ? 'new' : null

  return typeof raw === 'string' && raw !== '' ? Number(raw) || null : null
})

/* Open is "a profile is chosen", so the address, the highlight and the drawer always agree. */
const open = computed({
  get: () => selected.value !== null,
  set: (value) => {
    if (!value) close()
  },
})

async function load(): Promise<void> {
  loading.value = true

  try {
    rows.value = await api.list(q.value)
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

watch(q, () => void load())
void load()

let pause: ReturnType<typeof setTimeout> | undefined

watch(typed, (value) => {
  clearTimeout(pause)
  pause = setTimeout(() => {
    void router.replace({ query: { ...route.query, q: value.trim() === '' ? undefined : value } })
  }, 300)
})

onBeforeUnmount(() => clearTimeout(pause))

/* `replace` and not `push`: going through the authors is not a step of history each. */
function choose(id: number | 'new'): void {
  const query: LocationQueryRaw = { ...route.query, author: String(id) }

  // The account the article editor asked a profile for belongs to that one new profile only.
  if (id !== 'new') delete query.account
  void router.replace({ query })
}

function close(): void {
  const query = { ...route.query }

  delete query.author
  delete query.account
  void router.replace({ query })
}

/** A new profile was saved: it is a record now, and the list has it. */
function created(author: AuthorRow): void {
  choose(author.id)
  void load()
}

function removed(id: number): void {
  if (selected.value === id) close()

  void load()
}
</script>

<template>
  <wx-list-screen :title="title" :card="false">
    <wx-card class="wx-authors" padding="none">
      <wx-list-detail
        v-model:open="open"
        class="wx-authors__panes"
        :list-width="360"
        :detail-min="460"
        :detail-label="t('module.authors')"
      >
        <template #list>
          <div class="wx-authors__bar">
            <wx-input
              v-model="typed"
              class="wx-authors__search"
              size="sm"
              clearable
              :placeholder="t('author.search')"
              :aria-label="t('author.search')"
            >
              <template #prefix><wx-icon name="search" size="sm" /></template>
            </wx-input>
          </div>

          <button
            v-if="canManage"
            type="button"
            class="wx-authors__new"
            :class="{ 'is-current': selected === 'new' }"
            @click="choose('new')"
          >
            <wx-icon name="plus" size="sm" />
            {{ t('author.new') }}
          </button>

          <wx-skeleton v-if="loading && rows.length === 0" class="wx-authors__loading" :rows="4" />

          <wx-empty
            v-else-if="rows.length === 0"
            :title="q === '' ? t('author.empty') : t('author.empty-search')"
            :description="q === '' ? t('author.empty-help') : undefined"
          />

          <ul v-else class="wx-authors__list" :class="{ 'is-loading': loading }">
            <li v-for="item in rows" :key="item.id">
              <button
                type="button"
                class="wx-author-row"
                :class="{ 'is-current': selected === item.id }"
                @click="choose(item.id)"
              >
                <wx-avatar
                  class="wx-author-row__photo"
                  :src="item.photo ?? undefined"
                  :name="item.name"
                  size="md"
                />

                <span class="wx-author-row__body">
                  <span class="wx-author-row__name">{{ item.name }}</span>

                  <span class="wx-author-row__meta">
                    <span v-if="item.user">{{ item.user.name }}</span>
                    <span>{{ t('author.articles', { count: item.articles_count }) }}</span>
                  </span>

                  <span class="wx-author-row__facts">
                    <wx-badge v-if="item.open" dot size="sm" type="success">
                      {{ t('author.page-open') }}
                    </wx-badge>
                    <wx-badge v-else dot size="sm">{{ t('author.page-closed') }}</wx-badge>
                  </span>
                </span>
              </button>
            </li>
          </ul>
        </template>

        <template #detail="{ inline, back }">
          <author-pane
            v-if="selected !== null"
            :id="selected === 'new' ? null : selected"
            :key="String(selected)"
            :inline="inline"
            @back="back"
            @saved="load"
            @created="created"
            @removed="removed"
          />
        </template>

        <template #empty>
          <wx-empty
            :title="t('author.choose')"
            :description="canManage ? t('author.choose-help') : undefined"
          />
        </template>
      </wx-list-detail>
    </wx-card>
  </wx-list-screen>
</template>

<style>
/*
 * What scrolls here is the page, as on every other list of the panel: `clip`, not `hidden`, which
 * would make the card a scroll container and pin everything sticky inside it (CLAUDE.md §4).
 */
.wx-authors > .wx-card__body {
  display: flex;
  flex-direction: column;
  overflow: clip;
  border-radius: inherit;
}
</style>

<style scoped>
.wx-authors__bar {
  display: flex;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12) var(--wx-space-12) var(--wx-space-8);
}

.wx-authors__search {
  flex: 1 1 180px;
  min-width: 0;
}

.wx-authors__new {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  width: calc(100% - 2 * var(--wx-space-8));
  margin: 0 var(--wx-space-8) var(--wx-space-4);
  padding: var(--wx-space-8);
  border: 1px dashed var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
  background: none;
  font: inherit;
  color: var(--wx-color-primary);
  text-align: start;
  cursor: pointer;
}

.wx-authors__new:hover,
.wx-authors__new.is-current {
  background: var(--wx-bg-subtle);
}

.wx-authors__loading {
  padding: var(--wx-space-12);
}

.wx-authors__list {
  margin: 0;
  padding: 0 var(--wx-space-8) var(--wx-space-8);
  list-style: none;
}

.wx-authors__list.is-loading {
  opacity: 0.6;
}

.wx-author-row {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-10);
  width: 100%;
  min-width: 0;
  padding: var(--wx-space-8) var(--wx-space-4);
  border: 0;
  border-radius: var(--wx-radius-sm);
  background: none;
  font: inherit;
  color: inherit;
  text-align: start;
  cursor: pointer;
}

.wx-author-row:hover,
.wx-author-row.is-current {
  background: var(--wx-bg-subtle);
}

.wx-author-row__photo {
  flex: none;
}

.wx-author-row__body {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-author-row__name {
  min-width: 0;
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-author-row.is-current .wx-author-row__name {
  color: var(--wx-color-primary);
}

.wx-author-row__meta {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-2) var(--wx-space-8);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-author-row__facts {
  display: flex;
  margin-top: var(--wx-space-2);
}
</style>
