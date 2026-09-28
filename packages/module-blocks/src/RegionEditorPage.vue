<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxSaveState,
  WxScreen,
  WxScreenHead,
  type ScreenAction,
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
} from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'
import { createRegionsApi } from './api'
import { useBlocksMessages } from './i18n'
import { provideBlocksPreview, provideBlocksTop, type BlocksTop } from './preview'
import { previewAt, provideRegionEditor } from './region'
import RegionPagePicker, { type RegionPlace } from './RegionPagePicker.vue'
import type { BlockNode, RegionConflict, RegionDetail } from './types'

/**
 * The editor of one layout region: the page editor without what a region does not have — no
 * address, no SEO, no template of its own.
 *
 * Between the head and the bar is `regions.form`, a described screen, so a site adds a tab with
 * a patch as it would to a page. What the screen cannot know is which region it is — its name in
 * `allowed_in`, its `allow` and `max` from the config — and where to preview it; this page
 * provides both.
 *
 * Saving is by autosave, as on a page; publishing, taking off and discarding are asked about,
 * because each of them changes what every page of the site shows.
 */
defineOptions({ name: 'WxRegionEditorPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/regions' })

const context = useAdmin()
const api = createRegionsApi(context)
const route = useRoute()
const router = useRouter()
useBlocksMessages()

const t = useTranslate('webx-blocks')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

/** How long after the last change the draft goes to the server. */
const PAUSE = 1200

const name = computed(() => String(route.params.name ?? ''))

const region = ref<RegionDetail | null>(null)
const values = ref<ScreenModel>({ blocks: [] })
const revision = ref('')

const loading = ref(true)
const saving = ref(false)
const working = ref(false)
const errors = ref<Record<string, string[]>>({})
const conflict = ref<RegionConflict | null>(null)

const snapshot = ref('')
const reloadToken = ref(0)
const screenKey = ref(0)

/* `can()` answers a plain boolean; wrapped here so the template and the guards read one thing. */
const canManage = computed(() => context.can('blocks.regions'))

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const tree = computed<BlockNode[]>(() =>
  Array.isArray(values.value.blocks) ? (values.value.blocks as BlockNode[]) : [],
)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value ? 'unsaved' : 'saved'
})

/* Which page of the site the region is looked at on, remembered per region in this browser. */
const place = ref<RegionPlace>({ path: '/', link: null })

const storageKey = computed(() => `webx.regions.at.${name.value}`)

function readPlace(): RegionPlace {
  try {
    const stored = JSON.parse(
      localStorage.getItem(storageKey.value) ?? 'null',
    ) as RegionPlace | null

    if (stored && typeof stored.path === 'string' && stored.path.startsWith('/')) return stored
  } catch {
    // Private windows and blocked storage throw on the accessor itself: the home page it is.
  }

  return { path: '/', link: null }
}

watch(place, (next) => {
  try {
    localStorage.setItem(storageKey.value, JSON.stringify(next))
  } catch {
    // Not remembered, which is all that is lost.
  }
})

const previewUrl = computed(() =>
  region.value ? previewAt(region.value.preview_url, place.value.path) : null,
)

provideBlocksPreview({ url: previewUrl, reload: reloadToken })

provideBlocksTop(
  computed<BlocksTop | null>(() =>
    region.value
      ? {
          root: `region:${region.value.name}`,
          label: region.value.title,
          allow: region.value.allow,
          max: region.value.max,
        }
      : null,
  ),
)

provideRegionEditor({ region, canManage: canManage.value, reload: () => load(true) })

function take(detail: RegionDetail): void {
  region.value = detail
  values.value = { blocks: detail.blocks }
  revision.value = detail.revision
  snapshot.value = JSON.stringify(values.value)
  conflict.value = null
}

async function load(silent = false): Promise<void> {
  if (!silent) loading.value = true

  try {
    take(await api.get(name.value))
    reloadToken.value += 1
  } catch (error) {
    toast.danger(message(error))
    void router.push(props.base)
  } finally {
    loading.value = false
  }
}

/* One pending save at a time, and one pending pause. */
let timer: ReturnType<typeof setTimeout> | undefined

function schedule(): void {
  if (!canManage.value || conflict.value || !dirty.value) return

  clearTimeout(timer)
  timer = setTimeout(() => void save(), PAUSE)
}

function onFocusOut(): void {
  if (!canManage.value || conflict.value || !dirty.value || saving.value) return

  clearTimeout(timer)
  void save()
}

/* The save on its way, and what it carries. */
let flight: { carried: string; done: Promise<void> } | undefined

/** A save asked for while another is out waits for it rather than being dropped (as pages). */
async function save(): Promise<void> {
  while (flight) {
    const { carried, done } = flight

    await done

    if (!dirty.value || conflict.value || current.value === carried) return
  }

  const carried = current.value
  const done = write()

  flight = { carried, done }

  try {
    await done
  } finally {
    if (flight?.done === done) flight = undefined
  }
}

async function write(): Promise<void> {
  if (!region.value || !canManage.value) return

  clearTimeout(timer)

  // What is being sent, so that anything changed while the request is out stays dirty.
  const sending = current.value
  const blocks = tree.value

  saving.value = true
  errors.value = {}

  try {
    const detail = await api.save(region.value.name, { blocks, revision: revision.value })

    region.value = detail
    revision.value = detail.revision
    conflict.value = null

    if (current.value === sending) snapshot.value = sending

    // The preview draws the draft, and the draft is what was just written.
    reloadToken.value += 1
  } catch (error) {
    const failure = error as {
      status?: number
      body?: Partial<RegionConflict> & { errors?: Record<string, string[]> }
    }

    if (failure.status === 409 && failure.body?.revision) {
      conflict.value = {
        message: failure.body.message ?? t('region.conflict-title'),
        revision: failure.body.revision,
      }
    } else if (failure.body?.errors) {
      errors.value = failure.body.errors
      toast.danger(t('region.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

async function takeTheirs(): Promise<void> {
  const agreed = await confirm({
    title: t('region.conflict-theirs-title'),
    message: t('region.conflict-theirs-text'),
    confirmText: t('region.conflict-theirs'),
    cancelText: t('region.cancel'),
    tone: 'danger',
  })

  if (agreed) await load(true)
}

/** Keep what was written, over the other version: that one is in the autosave ring anyway. */
async function keepMine(): Promise<void> {
  const theirs = conflict.value

  if (!theirs) return

  revision.value = theirs.revision
  conflict.value = null

  await save()
}

/**
 * Run one of the three actions that change the site, after the question and a save.
 *
 * Saving first, because each of them works on the draft the server holds: publishing a region
 * with the last second of edits still in the pause would publish without them.
 */
async function act(
  words: { title: string; text: string; confirm: string; done: string; danger?: boolean },
  call: (name: string) => Promise<RegionDetail>,
): Promise<void> {
  if (!region.value) return

  const agreed = await confirm({
    title: words.title,
    message: words.text,
    confirmText: words.confirm,
    cancelText: t('region.cancel'),
    tone: words.danger ? 'danger' : undefined,
  })

  if (!agreed) return

  if (dirty.value) await save()
  if (conflict.value || dirty.value) return

  working.value = true

  try {
    take(await call(region.value.name))
    reloadToken.value += 1
    toast.success(words.done)
  } catch (error) {
    // A block that cannot be drawn refuses the publication by name; that sentence is the
    // server's to say, since only it rendered the region.
    const refused = (error as { body?: { errors?: Record<string, string[]> } }).body?.errors
      ?.blocks?.[0]

    toast.danger(refused ?? message(error))
  } finally {
    working.value = false
  }
}

function publish(): Promise<void> {
  const title = region.value?.title ?? ''

  return act(
    {
      title: t('region.publish-title', { title }),
      text: t('region.publish-text'),
      confirm: t('region.publish'),
      done: t('region.published'),
    },
    (region) => api.publish(region),
  )
}

function unpublish(): Promise<void> {
  const title = region.value?.title ?? ''

  return act(
    {
      title: t('region.unpublish-title', { title }),
      text: t('region.unpublish-text'),
      confirm: t('region.unpublish'),
      done: t('region.unpublished'),
      danger: true,
    },
    (region) => api.unpublish(region),
  )
}

function discard(): Promise<void> {
  const title = region.value?.title ?? ''

  return act(
    {
      title: t('region.discard-title', { title }),
      text: t('region.discard-text'),
      confirm: t('region.discard'),
      done: t('region.discarded'),
      danger: true,
    },
    (region) => api.discardDraft(region),
  )
}

/** The code's view made into a block type, with one of it put in the draft (§7.3). */
async function adopt(): Promise<void> {
  if (!region.value) return

  if (dirty.value) await save()

  working.value = true

  try {
    const adopted = await api.adopt(region.value.name)

    take(adopted.region)
    reloadToken.value += 1
    // The constructor reads the catalogue once, when it mounts, and the type that was just made
    // is not in it: drawn again, the new block gets its title and its form rather than a slug.
    screenKey.value += 1
    toast.success(t('region.adopted', { slug: adopted.block.slug }))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

type Status = 'live' | 'modified' | 'fallback'

const status = computed<Status>(() => {
  if (!region.value?.published) return 'fallback'

  return region.value.has_draft || dirty.value ? 'modified' : 'live'
})

const badge = computed(() =>
  status.value === 'live' ? 'success' : status.value === 'modified' ? 'warning' : 'default',
)

const actions = computed<ScreenAction[]>(() =>
  previewUrl.value
    ? [
        {
          key: 'preview',
          label: t('region.preview'),
          icon: 'external-link',
          href: previewUrl.value,
        },
      ]
    : [],
)

function leaveGuard(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

watch(current, schedule)

watch(
  name,
  () => {
    place.value = readPlace()
    void load()
  },
  { immediate: true },
)

onMounted(() => {
  window.addEventListener('beforeunload', leaveGuard)
})

onBeforeUnmount(() => {
  clearTimeout(timer)
  window.removeEventListener('beforeunload', leaveGuard)
})

/* Leaving flushes the pause rather than asking, and asks only when the save did not go through. */
onBeforeRouteLeave(async () => {
  if (!dirty.value || !canManage.value) return true

  await save()

  if (!dirty.value) return true

  return await confirm({
    title: t('region.leave-title'),
    message: t('region.leave-text'),
    confirmText: t('region.leave'),
    cancelText: t('region.cancel'),
    tone: 'danger',
  })
})
</script>

<template>
  <div class="wx-region-editor" @focusout="onFocusOut">
    <template v-if="loading || !region">
      <wx-skeleton class="wx-region-editor__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="8" /></wx-card>
    </template>

    <template v-else>
      <wx-screen-head
        divider
        :back="base"
        :back-label="t('region.title')"
        :title="region.title"
        :subtitle="region.description ?? undefined"
        :actions="actions"
      >
        <template #title-after>
          <wx-badge :type="badge" dot>{{ t(`region.status-${status}`) }}</wx-badge>
        </template>

        <template #extra>
          <region-page-picker v-model="place" />
        </template>
      </wx-screen-head>

      <wx-alert
        v-if="conflict"
        type="warning"
        :title="t('region.conflict-title')"
        :description="conflict.message"
      >
        <template #actions>
          <wx-button size="sm" variant="outline" @click="takeTheirs">
            {{ t('region.conflict-theirs') }}
          </wx-button>
          <wx-button size="sm" type="primary" @click="keepMine">
            {{ t('region.conflict-mine') }}
          </wx-button>
        </template>
      </wx-alert>

      <!--
        While the tree is empty the site prints the view from the code, and the preview shows
        exactly that — so the page says which view it is and how to replace it, rather than
        leaving a header that is plainly there beside a constructor that says "no blocks".
      -->
      <wx-alert
        v-if="tree.length === 0"
        class="wx-region-editor__empty"
        type="info"
        variant="soft"
        :title="t('region.empty-title')"
        :description="
          region.fallback
            ? t('region.empty-text', { view: region.fallback })
            : t('region.empty-text-none')
        "
      >
        <template v-if="region.can_adopt && canManage" #actions>
          <wx-button size="sm" variant="outline" :loading="working" @click="adopt">
            {{ t('region.adopt') }}
          </wx-button>
        </template>
      </wx-alert>

      <div class="wx-region-editor__screen">
        <wx-screen
          :key="screenKey"
          v-model="values"
          name="regions.form"
          :errors="errors"
          :disabled="!canManage || working"
        />
      </div>

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button v-if="region.published" variant="text" :disabled="working" @click="unpublish">
          {{ t('region.unpublish') }}
        </wx-button>

        <wx-button
          v-if="region.has_draft && region.published"
          variant="text"
          :disabled="working"
          @click="discard"
        >
          {{ t('region.discard') }}
        </wx-button>

        <wx-button variant="outline" :loading="saving" :disabled="!dirty" @click="save">
          {{ t('region.save') }}
        </wx-button>

        <wx-button
          type="primary"
          :loading="working"
          :disabled="region.published && !region.has_draft && !dirty"
          @click="publish"
        >
          {{ t('region.publish') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-region-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  height: 100%;
  min-height: 0;
  container-type: inline-size;
}

.wx-region-editor__ghost {
  max-width: 420px;
}

.wx-region-editor__screen {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

/*
 * The height travels through the screen's own boxes, as on the page editor: each is a flex
 * column that may shrink — and only the tab on screen, since `display: flex` on a kept-alive
 * hidden tab would override its `[hidden]` and split the height between all of them (§4).
 */
.wx-region-editor__screen :deep(.wx-screen-host),
.wx-region-editor__screen :deep(.wx-screen),
.wx-region-editor__screen :deep(.wx-tabs),
.wx-region-editor__screen :deep(.wx-tabs__layout),
.wx-region-editor__screen :deep(.wx-tabs__panels),
.wx-region-editor__screen :deep(.wx-tab:not([hidden])) {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}
</style>
