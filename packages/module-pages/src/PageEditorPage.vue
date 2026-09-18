<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { useAdmin, useErrorText, useTranslate, WxBackButton, WxScreen } from '@webx-ui/module-admin'
import { provideBlocksPreview } from '@webx-ui/module-blocks'
import {
  confirm,
  localizedValue,
  toast,
  useElementWidth,
  useLocales,
  WxAction,
  WxActionBar,
  WxAlert,
  WxBadge,
  WxBreadcrumb,
  WxBreadcrumbItem,
  WxButton,
  WxDropdown,
  WxDropdownItem,
  WxSkeleton,
  WxText,
  type LocalizedValue,
} from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'
import { createPagesApi } from './api'
import { providePageEditor } from './editor'
import { usePagesMessages } from './i18n'
import type { PageConflict, PageDetail, PageRow } from './types'

/**
 * The editor of one page: a head that stays put, and the described screen under it.
 *
 * What the page itself owns is the head — where this page sits, what state it is in, and the
 * way out to the site — and the bar along the bottom, which is where the page is saved and
 * published. Everything between them is `pages.form`, so a module or a project adds a tab to
 * the editor with a patch (§12) rather than with a fork of this file.
 *
 * Saving is by autosave: a pause after the last keystroke, and the moment a field is left. The
 * explicit button is still there, because a button that says "saved" is the only way a person
 * can be sure — but nothing is ever lost by not pressing it.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/pages' })

const context = useAdmin()
const api = createPagesApi(context)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
usePagesMessages()

const t = useTranslate('webx-pages')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

/** How long after the last keystroke the draft goes to the server. */
const PAUSE = 1200

const id = computed(() => Number(route.params.id))

const page = ref<PageRow | null>(null)
const ancestors = ref<PageRow[]>([])
const values = ref<ScreenModel>({})
const revision = ref('')
const previewUrl = ref<string | null>(null)
const prefixes = ref<Record<string, string>>({})

const loading = ref(true)
const saving = ref(false)
const working = ref(false)
const errors = ref<Record<string, string[]>>({})
const conflict = ref<PageConflict | null>(null)

const snapshot = ref('')
const reloadToken = ref(0)

const canManage = computed(() => context.can('pages.manage'))

/*
 * Where the head stops being a row and becomes three. Decided by the width of the panel rather
 * than of the window, the same way the section's list decides between a table and cards.
 */
const NARROW = 720

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)
const narrow = computed(() => width.value > 0 && width.value < NARROW)

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

/** Saved · saving · not saved yet — the one line of the head that moves while typing. */
const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value ? 'unsaved' : 'saved'
})

/**
 * The name in the head follows the field rather than the answer: an editor who renamed the
 * page in the settings tab should see the new name at the top before the save lands.
 */
const title = computed(() => {
  const written = values.value.title as LocalizedValue | string | null | undefined

  return localizedValue(written, locales.active.value, page.value?.title ?? '')
})

const trail = computed(() =>
  ancestors.value.map((node) => ({
    id: node.id,
    label: node.is_home ? t('pages.home') : node.title,
  })),
)

/* The constructor shows the draft as a page of the site. The link is the editor's to hand
   over: only this screen knows which entity it is editing. */
provideBlocksPreview({ url: computed(() => previewUrl.value), reload: reloadToken })

providePageEditor({
  page,
  ancestors,
  values,
  prefixes,
  base: props.base,
  canManage: canManage.value,
  reload: () => load(true),
  save: () => save(),
})

function take(detail: PageDetail): void {
  page.value = detail.page
  ancestors.value = detail.ancestors
  values.value = detail.values
  revision.value = detail.revision
  previewUrl.value = detail.preview_url
  prefixes.value = detail.address_prefix
  snapshot.value = JSON.stringify(detail.values)
  conflict.value = null
}

/**
 * Read the page again.
 *
 * Quietly unless this is the first time: the skeleton replaces the whole screen, tabs and all,
 * so a publication answered with it would throw an editor who published from the history back
 * onto the first tab — and flash the page on the way.
 */
async function load(silent = false): Promise<void> {
  if (!silent) loading.value = true

  try {
    take(await api.get(id.value))
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

/** A field was left: write now rather than at the end of a pause nobody is waiting through. */
function onFocusOut(): void {
  if (!canManage.value || conflict.value || !dirty.value || saving.value) return

  clearTimeout(timer)
  void save()
}

async function save(): Promise<void> {
  if (!page.value || saving.value || !canManage.value) return

  clearTimeout(timer)

  // What is being sent, so that whatever is typed while the request is in flight stays dirty
  // and gets its own save rather than being marked as written.
  const sending = current.value
  const sent = values.value

  saving.value = true
  errors.value = {}

  try {
    const detail = await api.save(page.value.id, { values: sent, revision: revision.value })

    page.value = detail.page
    ancestors.value = detail.ancestors
    revision.value = detail.revision
    previewUrl.value = detail.preview_url
    prefixes.value = detail.address_prefix
    conflict.value = null

    if (current.value === sending) snapshot.value = sending

    // The preview is rendered from the draft, and the draft is what was just written.
    reloadToken.value += 1
  } catch (error) {
    const failure = error as {
      status?: number
      body?: PageConflict & { errors?: Record<string, string[]> }
    }

    if (failure.status === 409 && failure.body?.data) {
      conflict.value = failure.body
    } else if (failure.body?.errors) {
      errors.value = failure.body.errors
      toast.danger(t('page.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

/** Give up what was typed and take the page as it now is (§6). */
async function takeTheirs(): Promise<void> {
  const agreed = await confirm({
    title: t('page.conflict-theirs-title'),
    message: t('page.conflict-theirs-text'),
    confirmText: t('page.conflict-theirs'),
    cancelText: t('page.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  await load(true)
}

/**
 * Keep what was typed and write it over the other version.
 *
 * Nothing is lost by it: the version that is about to be overwritten is in the history if it
 * was published, and in the autosave ring if it was not.
 */
async function keepMine(): Promise<void> {
  const theirs = conflict.value

  if (!theirs) return

  revision.value = theirs.data.revision
  conflict.value = null

  await save()
}

/**
 * Publishing is asked about, because taking a page off the site is (§14.2).
 *
 * It is the one action here that visitors see: everything else on this screen writes a draft
 * nobody outside the panel can read. The question names the address rather than counting
 * anything — one page goes on the site, and what matters is where.
 */
async function publish(): Promise<void> {
  if (!page.value) return

  const address = page.value.path === null ? null : `/${page.value.path}`

  const agreed = await confirm({
    title: t('page.publish-title', { title: page.value.title }),
    message:
      address === null ? t('page.publish-text-nowhere') : t('page.publish-text', { address }),
    confirmText: t('page.publish'),
    cancelText: t('page.cancel'),
  })

  if (!agreed) return

  if (dirty.value) await save()
  if (conflict.value) return

  working.value = true

  try {
    await api.publish(page.value.id)
    await load(true)
    toast.success(t('page.published'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

function badge(): 'default' | 'success' | 'warning' {
  if (page.value?.status === 'published') return 'success'

  return page.value?.status === 'modified' ? 'warning' : 'default'
}

/* The browser's own guard: it cannot wait for a request, so all it can do is ask. */
function leaveGuard(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

watch(current, schedule)
watch(id, () => void load())

onMounted(() => {
  void load()
  window.addEventListener('beforeunload', leaveGuard)
})

onBeforeUnmount(() => {
  clearTimeout(timer)
  window.removeEventListener('beforeunload', leaveGuard)
})

/**
 * Leaving flushes the pause rather than asking about it: everything else on this screen saves
 * by itself, and a dialog that appears only because somebody left within a second of typing
 * would be the one place that does not. The question is asked only when the save did not go
 * through — a conflict, a refused field, a server that is not there.
 */
onBeforeRouteLeave(async () => {
  if (!dirty.value || !canManage.value) return true

  await save()

  if (!dirty.value) return true

  return await confirm({
    title: t('page.leave-title'),
    message: t('page.leave-text'),
    confirmText: t('page.leave'),
    cancelText: t('page.cancel'),
    tone: 'danger',
  })
})
</script>

<template>
  <!-- `data-wx-fill`: this screen is as tall as the column it is drawn in and scrolls its own
       panes, rather than growing and taking the page with it (§10). -->
  <div ref="root" class="wx-page-editor" data-wx-fill @focusout="onFocusOut">
    <template v-if="loading || !page">
      <wx-skeleton class="wx-page-editor__ghost" title :rows="1" />
      <wx-skeleton :rows="8" />
    </template>

    <template v-else>
      <div class="wx-page-editor__head">
        <!-- The trail says where the reader is; this is the way out of it, and on a phone it
             is the only one that is a control rather than four words in the smallest type. -->
        <wx-back-button class="wx-page-editor__back" :to="base" :label="t('module.title')" />

        <div class="wx-page-editor__id">
          <wx-breadcrumb size="sm" :label="t('page.trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="base">
              {{ t('module.title') }}
            </wx-breadcrumb-item>
            <wx-breadcrumb-item
              v-for="node in trail"
              :key="node.id"
              :as="'router-link'"
              :to="`${base}/${node.id}`"
            >
              {{ node.label }}
            </wx-breadcrumb-item>
            <wx-breadcrumb-item current>
              {{ page.is_home ? t('pages.home') : title }}
            </wx-breadcrumb-item>
          </wx-breadcrumb>

          <div class="wx-page-editor__name">
            <span class="wx-page-editor__title">{{
              page.is_home ? t('pages.home') : title || t('page.untitled')
            }}</span>
            <wx-badge :type="badge()" dot>{{ t(`page.status-${page.status}`) }}</wx-badge>
          </div>
        </div>

        <!--
          What is left in the head is what leads away from the page: the draft on the site and
          the page on the site. Saving and publishing are down in the bar, where the eye is —
          and only there, because this screen is exactly as tall as the window and the head
          never leaves it. A second copy of a button already on screen is not a reminder.
        -->
        <div class="wx-page-editor__actions">
          <!-- Narrow: the two links fold into a menu. A head three rows tall is a fifth of a
               phone screen given to buttons above the thing being edited. -->
          <wx-dropdown v-if="narrow" align="end">
            <template #trigger>
              <wx-action type="more" :title="t('page.more')" />
            </template>
            <wx-dropdown-item
              v-if="previewUrl"
              icon="eye"
              :href="previewUrl"
              target="_blank"
              rel="noopener"
            >
              {{ t('page.preview') }}
            </wx-dropdown-item>
            <wx-dropdown-item
              v-if="page.url && page.status !== 'draft'"
              icon="link"
              :href="page.url"
              target="_blank"
              rel="noopener"
            >
              {{ t('page.open-on-site') }}
            </wx-dropdown-item>
          </wx-dropdown>

          <template v-else>
            <wx-button
              v-if="previewUrl"
              variant="text"
              icon="eye"
              :href="previewUrl"
              target="_blank"
              rel="noopener"
            >
              {{ t('page.preview') }}
            </wx-button>
            <wx-button
              v-if="page.url && page.status !== 'draft'"
              variant="text"
              icon="link"
              :href="page.url"
              target="_blank"
              rel="noopener"
            >
              {{ t('page.open-on-site') }}
            </wx-button>
          </template>
        </div>
      </div>

      <!-- Somebody else wrote while this editor was typing. Both versions still exist, so the
           question is which one the site gets — and it is a question, not a toast that
           disappears while the answer is being thought about. -->
      <wx-alert
        v-if="conflict"
        type="warning"
        :title="t('page.conflict-title')"
        :description="conflict.message"
      >
        <template #actions>
          <wx-button size="sm" variant="outline" @click="takeTheirs">
            {{ t('page.conflict-theirs') }}
          </wx-button>
          <wx-button size="sm" type="primary" @click="keepMine">
            {{ t('page.conflict-mine') }}
          </wx-button>
        </template>
      </wx-alert>

      <div class="wx-page-editor__screen">
        <wx-screen
          v-model="values"
          name="pages.form"
          :errors="errors"
          :disabled="!canManage || working"
        />
      </div>

      <!--
        The last row of the screen, not a layer over it: the preview above shrinks by the height
        of the bar and is never covered by it. Nothing scrolls on this screen, so the bar never
        has to stick to anything — it is already where sticking would put it.
      -->
      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-text size="sm" tone="muted">{{ t(`page.state-${state}`) }}</wx-text>
        </template>

        <wx-button variant="outline" :loading="saving" :disabled="!dirty" @click="save">
          {{ t('page.save') }}
        </wx-button>

        <!-- Only the forward action is here; taking a page off the site and deleting it live
             in the settings tab, where nothing is one slip away from the save button. -->
        <wx-button
          type="primary"
          :loading="working"
          :disabled="page.status === 'published' && !dirty"
          @click="publish"
        >
          {{ t('page.publish') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-page-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  /* The constructor is the whole screen below the head, so the page is as tall as what it
     is drawn in and never taller: the tree, the form and the preview scroll inside
     themselves, and the head stays where it was put (§10). */
  height: 100%;
  min-height: 0;
  container-type: inline-size;
}

.wx-page-editor__ghost {
  max-width: 420px;
}

/*
 * The head stays put by standing still: the screen is as tall as its column, so nothing under
 * it scrolls the page — each tab scrolls inside itself. A sticky bar would be a bar that never
 * has anything to stick to.
 */
.wx-page-editor__head {
  flex: none;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--wx-space-12);
  flex-wrap: wrap;
  padding-block-end: var(--wx-space-12);
  border-block-end: 1px solid var(--wx-border-default);
}

/* Level with the trail, which is the first line of the head, not the middle of both lines. */
.wx-page-editor__back {
  flex: none;
}

.wx-page-editor__id {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  flex: 1 1 260px;
  min-width: 0;
}

.wx-page-editor__name {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex-wrap: wrap;
  min-width: 0;
}

.wx-page-editor__title {
  font-size: var(--wx-font-size-xl);
  font-weight: var(--wx-font-weight-bold);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-page-editor__actions {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex-wrap: wrap;
}

.wx-page-editor__screen {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

/*
 * The tabs are the screen's, not this page's, so the height has to travel through them: every
 * box between the page and the constructor is a flex column that may shrink, or the
 * constructor sizes itself to its content and the whole page scrolls with the head on top of
 * it — which is the one thing §10 is about.
 */
.wx-page-editor__screen :deep(.wx-screen-host),
.wx-page-editor__screen :deep(.wx-screen),
.wx-page-editor__screen :deep(.wx-tabs),
.wx-page-editor__screen :deep(.wx-tabs__layout),
.wx-page-editor__screen :deep(.wx-tabs__panels),
.wx-page-editor__screen :deep(.wx-tab:not([hidden])) {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

/*
 * The tab that is not shown keeps its `display: none`. Said out loud because the rule above
 * would take it away: the tabs are kept alive, so the three that are hidden are still in the
 * document, and a `display: flex` that reaches them makes each one a quarter of the height the
 * one on screen should have had. Measured — a constructor 181px tall in a 740px column.
 */

/* A tab is ordinary content and scrolls the way a form does; the constructor fills instead. */
.wx-page-editor__screen :deep(.wx-tab:not([hidden])) {
  overflow: auto;
}

/*
 * The renderer wraps every field in a form item, so the height has to travel through that as
 * well — and through that one only. `:has()` picks the wrapper of a field that fills and leaves
 * the ordinary fields of the settings tab the height of their own contents.
 */
.wx-page-editor__screen :deep(.wx-form-item:has(.wx-blocks-host.is-fill)),
.wx-page-editor__screen :deep(.wx-form-item:has(.wx-blocks-host.is-fill) .wx-form-item__control) {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

@container (max-width: 640px) {
  .wx-page-editor__actions {
    width: 100%;
  }
}
</style>
