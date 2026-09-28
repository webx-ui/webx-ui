<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'
import {
  confirm,
  createModal,
  toast,
  WxCard,
  WxEmpty,
  WxIcon,
  WxIndicator,
  WxListDetail,
  WxSkeleton,
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
import { createBannersApi } from './api'
import { useBannersMessages } from './i18n'
import PlaceDialog from './PlaceDialog.vue'
import PlacePane from './PlacePane.vue'
import type { PlaceRow } from './types'

/**
 * The section: the places of the site on the left, the banners of one of them on the right
 * (§5.4) — the shape of the menus, for the same reason: somebody who opens this is arranging one
 * place, not choosing between them, so the first one opens by itself where there is room for it.
 * On a phone it does not: there the same line would raise a panel over a list nobody touched.
 *
 * A declared place is on the list before anything was ever saved into it (decision 1) — its row
 * in the table is made by its first banner — so places are named by key throughout, not by id.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/banners' })

const admin = useAdmin()
const api = createBannersApi(admin)
const route = useRoute()
const router = useRouter()
useBannersMessages()

const t = useTranslate('webx-banners')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const places = ref<PlaceRow[]>([])
const loading = ref(true)
const open = ref(false)
const detailInline = ref(true)

const canManage = computed(() => admin.can('banners.manage'))

const edit = createModal<PlaceRow, { place?: PlaceRow | null }>(PlaceDialog)

const title = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'banners')?.title ??
    t('module.banners'),
)

/** Which place is open, kept in the address — so "back" from a banner lands on its place. */
const current = computed<string | null>(() => {
  const asked = route.query.place

  return typeof asked === 'string' && asked !== '' ? asked : null
})

const chosen = computed(() => places.value.find((place) => place.key === current.value) ?? null)

async function load(): Promise<void> {
  loading.value = true

  try {
    places.value = await api.places()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

void load()

/*
 * The first place, where there is a column to draw it in — only once the width is measured
 * (`detail-inline` says so), so a phone does not get a drawer over a list it never touched.
 */
watch([places, chosen, detailInline], () => {
  if (!detailInline.value || places.value.length === 0 || chosen.value !== null) return

  void router.replace({ query: { ...route.query, place: places.value[0]!.key } })
})

watch(chosen, (place) => {
  if (place !== null && detailInline.value) open.value = true
})

/* Coming back from a banner on a phone lands on its place, and that is the drawer, open. */
if (current.value !== null) open.value = true

function choose(place: PlaceRow): void {
  const query: LocationQueryRaw = { ...route.query, place: place.key }

  // The bin of one place is not the bin of the next.
  delete query.view
  void router.replace({ query })
  open.value = true
}

async function add(): Promise<void> {
  const made = await edit({ place: null })

  if (!made) return

  toast.success(t('places.created'))
  await load()
  choose(made)
}

async function rename(place: PlaceRow): Promise<void> {
  const saved = await edit({ place })

  if (!saved) return

  toast.success(t('places.renamed'))
  await load()
}

async function remove(place: PlaceRow): Promise<void> {
  const agreed = await confirm({
    title: t('places.delete-title', { place: place.title }),
    message: t('places.delete-text'),
    confirmText: t('places.delete'),
    cancelText: t('places.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removePlace(place.key)
    toast.success(t('places.deleted'))

    if (current.value === place.key) {
      const rest = { ...route.query }

      delete rest.place
      delete rest.view
      void router.replace({ query: rest })
      open.value = false
    }
  } catch (error) {
    // The one refusal worth its own words: something is still in it — perhaps only in the bin,
    // which the count beside the name does not show (§5.6).
    toast.danger(message(error))
  } finally {
    await load()
  }
}

function actionsFor(place: PlaceRow): RowAction[] {
  if (!canManage.value || place.declared) return []

  return [
    { key: 'rename', icon: 'edit', label: t('places.rename'), run: () => void rename(place) },
    {
      key: 'delete',
      icon: 'trash',
      // Said in the line itself, since a switched-off line cannot carry a tooltip: an empty
      // place is the only kind that goes (decision 1).
      label: place.count > 0 ? t('places.delete-empty-first') : t('places.delete'),
      danger: true,
      disabled: place.count > 0,
      run: () => void remove(place),
    },
  ]
}

const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [{ key: 'new-place', label: t('places.new'), icon: 'plus', run: () => void add() }]
    : [],
)
</script>

<template>
  <wx-list-screen :title="title" :actions="actions" :card="false">
    <wx-card class="wx-banners" padding="none">
      <wx-list-detail
        v-model:open="open"
        class="wx-banners__panes"
        :list-width="300"
        :detail-min="440"
        :detail-label="t('places.places')"
        @detail-inline="detailInline = $event"
      >
        <template #list>
          <wx-skeleton
            v-if="loading && places.length === 0"
            class="wx-banners__loading"
            :rows="3"
          />

          <wx-empty
            v-else-if="places.length === 0"
            :title="t('places.empty')"
            :description="t('places.empty-help')"
          />

          <ul v-else class="wx-banners__list">
            <li v-for="place in places" :key="place.key" class="wx-banners__row">
              <button
                type="button"
                class="wx-banners__open"
                :class="{ 'is-current': place.key === current }"
                @click="choose(place)"
              >
                <span class="wx-banners__name">
                  <wx-text truncate weight="medium">{{ place.title }}</wx-text>
                  <wx-icon
                    v-if="place.declared"
                    class="wx-banners__lock"
                    name="lock"
                    size="sm"
                    :aria-label="t('places.declared')"
                  />
                  <wx-indicator
                    class="wx-banners__count"
                    type="neutral"
                    :value="place.count"
                    :label="t('places.count')"
                  />
                </span>

                <wx-text size="sm" tone="muted" mono truncate>banners('{{ place.key }}')</wx-text>
              </button>

              <wx-row-menu
                v-if="actionsFor(place).length > 0"
                :actions="actionsFor(place)"
                :label="place.title"
              />
            </li>
          </ul>
        </template>

        <template #detail="{ inline, back }">
          <place-pane
            v-if="chosen"
            :key="chosen.key"
            :place="chosen"
            :base="props.base"
            :inline="inline"
            @back="back"
            @changed="load"
          />
        </template>

        <template #empty>
          <wx-empty :title="t('places.choose')" :description="t('places.choose-help')" />
        </template>
      </wx-list-detail>
    </wx-card>
  </wx-list-screen>
</template>

<style>
/*
 * What scrolls here is the page, the way it does on every other list of the panel. `clip`, not
 * `hidden`: `hidden` would make this a scroll container, and everything sticky inside would pin
 * itself to a box that never moves (CLAUDE.md §4).
 */
.wx-banners > .wx-card__body {
  display: flex;
  flex-direction: column;
  overflow: clip;
  border-radius: inherit;
}
</style>

<style scoped>
.wx-banners__loading {
  padding: var(--wx-space-16);
}

.wx-banners__list {
  margin: 0;
  padding: var(--wx-space-8);
  list-style: none;
}

.wx-banners__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  margin: 0;
  padding-inline: var(--wx-space-8);
  border-radius: var(--wx-radius-sm);
}

.wx-banners__row:has(.is-current) {
  background: var(--wx-bg-subtle);
}

.wx-banners__open {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-2);
  flex: 1 1 auto;
  min-width: 0;
  padding-block: var(--wx-space-8);
  padding-inline: 0;
  border: 0;
  background: none;
  font: inherit;
  color: inherit;
  text-align: start;
  cursor: pointer;
}

.wx-banners__open.is-current {
  color: var(--wx-color-primary);
}

.wx-banners__name {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
  max-width: 100%;
}

.wx-banners__lock {
  flex: none;
  color: var(--wx-text-muted);
}

.wx-banners__count {
  flex: 0 0 auto;
}
</style>
