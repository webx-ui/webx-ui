<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  confirm,
  toast,
  WxAlert,
  WxBadge,
  WxEmpty,
  WxIcon,
  WxSegmented,
  WxSkeleton,
  WxSortableList,
  WxTooltip,
  type SegmentedValue,
} from '@webx-ui/core'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxRowMenu,
  WxScreenHead,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
import { createBannersApi } from './api'
import { useBannersMessages } from './i18n'
import type { BannerRow, PlaceRow } from './types'

/**
 * The banners of one place: the right-hand half of the section, and the whole screen on a phone.
 *
 * The order here is the order on the site, and a place is small, so the list is the whole place
 * and a drag writes it whole (`reorder`). The bin is the same list turned over: nothing to drag,
 * and a banner in it opens nothing — it can only come back.
 *
 * The way back is drawn here and not by the pane around it: the drawer a narrow screen opens this
 * in has no close of its own on purpose (CLAUDE.md §4).
 */
defineOptions({ name: 'WxBannerPlacePane' })

const props = withDefaults(
  defineProps<{
    place: PlaceRow
    base: string
    /** False in the drawer a narrow screen opens this in. */
    inline?: boolean
  }>(),
  { inline: true },
)

const emit = defineEmits<{
  back: []
  /** Something moved in or out of the place: the count beside its name is stale. */
  changed: []
}>()

const admin = useAdmin()
const api = createBannersApi(admin)
const route = useRoute()
const router = useRouter()
useBannersMessages()

const t = useTranslate('webx-banners')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const rows = ref<BannerRow[]>([])
const loading = ref(true)

const canManage = computed(() => admin.can('banners.manage'))

/* The bin is in the address with the place, so coming back from a banner lands where one was. */
const view = computed<SegmentedValue>({
  get: () => (route.query.view === 'trashed' ? 'trashed' : 'live'),
  set: (value) => {
    void router.replace({
      query: { ...route.query, view: value === 'trashed' ? 'trashed' : undefined },
    })
  },
})

const inBin = computed(() => view.value === 'trashed')

const views = computed(() => [
  { value: 'live', label: t('banner.all') },
  { value: 'trashed', label: t('banner.trashed'), icon: 'trash' as const },
])

const sortable = computed(() => canManage.value && !inBin.value && rows.value.length > 1)

const layout = computed(() => {
  const known = ['single', 'random', 'slider']

  return known.includes(props.place.layout) ? t(`places.layout-${props.place.layout}`) : ''
})

async function load(): Promise<void> {
  loading.value = true

  try {
    rows.value = await api.list(props.place.key, inBin.value)
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

watch(inBin, () => void load())
void load()

function edit(row: BannerRow): string {
  return `${props.base}/${row.id}`
}

function add(): void {
  void router.push({ path: `${props.base}/new`, query: { place: props.place.key } })
}

/**
 * The order on screen, written whole. The list is already in its new order (`WxSortableList`
 * reorders the model before it says so), so a failure has to put it back rather than leave the
 * screen disagreeing with the database.
 */
async function reorder(): Promise<void> {
  try {
    await api.reorder(
      props.place.key,
      rows.value.map((row) => row.id),
    )
  } catch (error) {
    toast.danger(message(error, t('banner.reorder-failed')))
    await load()
  }
}

/*
 * On and off from the list, through the form's own door: there is no switch of its own in the
 * API, and a second way to write a banner would be a second set of rules to keep in step. What
 * the form would refuse — a look the config dropped since — is refused here the same way.
 */
async function toggle(row: BannerRow): Promise<void> {
  try {
    const detail = await api.get(row.id)

    await api.save(row.id, { ...detail.values, enabled: !row.enabled })
    toast.success(row.enabled ? t('banner.switched-off') : t('banner.switched-on'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await load()
  }
}

async function remove(row: BannerRow): Promise<void> {
  const agreed = await confirm({
    title: t('banner.delete-title', { name: row.title }),
    message: t('banner.delete-text'),
    confirmText: t('banner.delete'),
    cancelText: t('banner.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(t('banner.deleted'))
    emit('changed')
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await load()
  }
}

async function restore(row: BannerRow): Promise<void> {
  try {
    await api.restore(row.id)
    toast.success(t('banner.restored'))
    emit('changed')
  } catch (error) {
    toast.danger(message(error))
  } finally {
    await load()
  }
}

function actionsFor(row: BannerRow): RowAction[] {
  if (inBin.value) {
    return canManage.value
      ? [
          {
            key: 'restore',
            icon: 'refresh',
            label: t('banner.restore'),
            run: () => void restore(row),
          },
        ]
      : []
  }

  const open: RowAction = {
    key: 'open',
    icon: 'edit',
    label: t('banner.open'),
    run: () => void router.push(edit(row)),
  }

  if (!canManage.value) return [open]

  return [
    open,
    {
      key: 'toggle',
      icon: row.enabled ? 'eye-off' : 'eye',
      label: row.enabled ? t('banner.disable') : t('banner.enable'),
      run: () => void toggle(row),
    },
    {
      key: 'delete',
      icon: 'trash',
      label: t('banner.delete'),
      danger: true,
      run: () => void remove(row),
    },
  ]
}

/* The one thing this pane exists for, in its head; on a narrow pane it stays a button. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value && !inBin.value
    ? [{ key: 'new', label: t('banner.new'), icon: 'plus', primary: true, run: add }]
    : [],
)
</script>

<template>
  <div class="wx-banner-place">
    <wx-screen-head
      :level="3"
      :back="!props.inline"
      :back-label="t('places.places')"
      :title="place.title"
      :actions="actions"
      :collapse-below="420"
      @back="emit('back')"
    >
      <template v-if="place.declared" #title-after>
        <wx-tooltip :content="t('places.declared-help')">
          <span class="wx-banner-place__lock" tabindex="0" :aria-label="t('places.declared')">
            <wx-icon name="lock" size="sm" />
          </span>
        </wx-tooltip>
      </template>
    </wx-screen-head>

    <!--
      How a template asks for this place, where somebody writing one will look for it: the key
      is the whole contract between the panel and the site (decision 7).
    -->
    <p class="wx-banner-place__usage">
      <span>{{ t('places.usage') }}</span>
      <code>banners('{{ place.key }}')</code>
      <span v-if="layout" class="wx-banner-place__layout">· {{ layout }}</span>
    </p>

    <wx-segmented
      v-model="view"
      class="wx-banner-place__views"
      size="sm"
      :options="views"
      :aria-label="t('banner.all')"
    />

    <wx-skeleton v-if="loading && rows.length === 0" :rows="4" />

    <wx-empty
      v-else-if="rows.length === 0"
      :title="inBin ? t('banner.empty-bin') : t('banner.empty')"
      :description="inBin ? undefined : t('banner.empty-help')"
    />

    <template v-else>
      <!-- A grip and not the whole row: the row opens a banner, and a row that both opens and
           drags is one where one of the two happens by accident. -->
      <wx-sortable-list
        v-model="rows"
        class="wx-banner-place__list"
        :class="{ 'is-loading': loading }"
        plain
        item-key="id"
        :handle="sortable ? 'grip' : 'row'"
        :item-label="(item: BannerRow) => item.title"
        :disabled="!sortable"
        @move="reorder"
      >
        <template #default="{ item }">
          <component
            :is="inBin ? 'div' : 'router-link'"
            :to="inBin ? undefined : edit(item)"
            class="wx-banner-row"
            :class="{ 'is-off': !item.enabled && !inBin }"
          >
            <span class="wx-banner-row__thumb">
              <img v-if="item.thumb" :src="item.thumb" alt="" loading="lazy" />
              <wx-icon v-else name="image" size="sm" :aria-label="t('banner.no-picture')" />
            </span>

            <span class="wx-banner-row__body">
              <span class="wx-banner-row__title">{{ item.title }}</span>

              <span v-if="item.video || (!item.enabled && !inBin)" class="wx-banner-row__facts">
                <wx-badge v-if="item.video" size="sm" type="info">{{ t('banner.video') }}</wx-badge>
                <wx-badge v-if="!item.enabled && !inBin" dot size="sm">{{
                  t('banner.disabled')
                }}</wx-badge>
              </span>
            </span>
          </component>
        </template>

        <template #actions="{ item }">
          <wx-row-menu :actions="actionsFor(item)" :label="item.title" />
        </template>
      </wx-sortable-list>

      <!-- What a drag does here, said once under the list. -->
      <wx-alert
        v-if="sortable"
        class="wx-banner-place__note"
        type="info"
        :description="t('banner.order')"
      />
    </template>
  </div>
</template>

<style scoped>
.wx-banner-place {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
  padding: var(--wx-gap, var(--wx-space-16));
}

.wx-banner-place__lock {
  display: inline-flex;
  color: var(--wx-text-muted);
}

.wx-banner-place__usage {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: var(--wx-space-6);
  margin: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-banner-place__usage code {
  padding: 0 var(--wx-space-4);
  border-radius: var(--wx-radius-xs);
  background: var(--wx-bg-subtle);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-mono);
  /* Copied whole, as somebody writing a template would: a click selects all of it. */
  user-select: all;
}

.wx-banner-place__views {
  align-self: flex-start;
}

.wx-banner-place__list.is-loading {
  opacity: 0.6;
}

.wx-banner-place__list :deep(.wx-sortable-list__row) {
  gap: var(--wx-space-4);
  padding-inline: var(--wx-space-4);
  border-radius: var(--wx-radius-sm);
}

.wx-banner-place__list :deep(.wx-sortable-list__row:hover) {
  background: var(--wx-bg-subtle);
}

.wx-banner-row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  /* The row is the target: a short title should not leave half the line dead. */
  flex: 1 1 auto;
  width: 100%;
  min-width: 0;
  padding-block: var(--wx-space-8);
  color: inherit;
  text-decoration: none;
}

/* Off is not gone: still here, still in its place, just quieter than what is on the site. */
.wx-banner-row.is-off .wx-banner-row__thumb,
.wx-banner-row.is-off .wx-banner-row__title {
  opacity: 0.55;
}

.wx-banner-row__thumb {
  display: flex;
  flex: none;
  align-items: center;
  justify-content: center;
  width: 80px;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  border-radius: var(--wx-radius-xs);
  background: var(--wx-bg-fill);
  color: var(--wx-text-muted);
}

.wx-banner-row__thumb img {
  width: 100%;
  height: 100%;
  /* A thumbnail wider than its frame would otherwise hold the row open (CLAUDE.md §4). */
  min-width: 0;
  object-fit: cover;
}

.wx-banner-row__body {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-banner-row__title {
  min-width: 0;
  overflow: hidden;
  font-weight: var(--wx-font-weight-medium);
  text-overflow: ellipsis;
  white-space: nowrap;
}

a.wx-banner-row:hover .wx-banner-row__title {
  color: var(--wx-color-primary);
}

.wx-banner-row__facts {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-4);
}

.wx-banner-place__note {
  margin-top: var(--wx-space-4);
}
</style>
