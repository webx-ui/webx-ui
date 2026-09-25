<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  useAdmin,
  useDates,
  useErrorText,
  useTranslate,
  WxSaveState,
  WxScreen,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
import { provideBlocksPreview } from '@webx-ui/module-blocks'
import {
  confirm,
  localizedValue,
  toast,
  useLocales,
  WxActionBar,
  WxAlert,
  WxBadge,
  WxBreadcrumb,
  WxBreadcrumbItem,
  WxButton,
  WxCard,
  WxSkeleton,
  type LocalizedValue,
} from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'
import { createBlogApi } from './api'
import { provideArticleEditor } from './editor'
import { useBlogMessages } from './i18n'
import type { ArticleConflict, ArticleDetail, ArticleOption, ArticleRow } from './types'

/**
 * The editor of one article: a head that stays put, and the described screen under it.
 *
 * What this page owns is the head — what the article is called, what state it is in, and the
 * way out to the site — and the bar along the bottom, which is where it is saved and published.
 * Everything between them is `blog.article-form`, so a module or a project adds a tab to the
 * editor with a patch (§12) rather than with a fork of this file.
 *
 * Saving is by autosave: a pause after the last keystroke, and the moment a field is left. The
 * explicit button is still there, because a button that says "saved" is the only way a person
 * can be sure — but nothing is ever lost by not pressing it.
 *
 * The one thing here that a page editor does not have is the day. An article carries a date
 * that may be in the future, so "publish" means "publish under the date in the settings tab",
 * and which day that is stands under the name before the button is pressed (§7).
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/blog' })

const context = useAdmin()
const api = createBlogApi(context)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
const dates = useDates()
useBlogMessages()

const t = useTranslate('webx-blog')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

/** How long after the last keystroke the draft goes to the server. */
const PAUSE = 1200

const id = computed(() => Number(route.params.id))
const list = computed(() => `${props.base}/articles`)

const article = ref<ArticleRow | null>(null)
const values = ref<ScreenModel>({})
const revision = ref('')
const prefix = ref('')
const previewUrl = ref<string | null>(null)
const options = ref<ArticleDetail['options']>({ rubrics: [], authors: [] })
const related = ref<ArticleOption[]>([])

const loading = ref(true)
const saving = ref(false)
const working = ref(false)
const errors = ref<Record<string, string[]>>({})
const conflict = ref<ArticleConflict | null>(null)

const snapshot = ref('')
const reloadToken = ref(0)

const canManage = computed(() => context.can('blog.articles.manage'))

/** Closed for writing: no permission, or a publication in flight. */
const locked = computed(() => !canManage.value || working.value)

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

/** Saved · saving · not saved yet — the one line of the bar that moves while typing. */
const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value ? 'unsaved' : 'saved'
})

/**
 * The name in the head follows the field rather than the answer: an editor who renamed the
 * article in the settings tab should see the new name at the top before the save lands.
 */
const title = computed(() => {
  const written = values.value.title as LocalizedValue | string | null | undefined

  return localizedValue(written, locales.active.value, article.value?.title ?? '')
})

/** The day in the settings tab, as the picker leaves it: `2026-09-24 08:00:00`, or nothing. */
const chosen = computed<string | null>(() => {
  const written = values.value.published_at

  return typeof written === 'string' && written !== '' ? written : null
})

const future = computed(() => {
  if (chosen.value === null) return false

  // The picker's value is local time with a space in it, which Safari refuses to parse.
  const at = new Date(chosen.value.replace(' ', 'T'))

  return !Number.isNaN(at.valueOf()) && at.valueOf() > Date.now()
})

/**
 * When the article goes out, under its name.
 *
 * Only the day, and only when there is one. What state it is in the badges beside the name say
 * already — live, scheduled, with edits waiting — and saying it twice in two shapes is how a
 * screen teaches an editor to read neither. The day is the part a badge cannot carry, and the
 * part somebody about to press "publish" actually weighs.
 */
const publication = computed(() => {
  const row = article.value

  if (!row) return ''

  const when = dates.short(row.published_at)

  const line = {
    scheduled: t('article.live-scheduled', { date: when }),
    published: t('article.live-since', { date: when }),
    modified: t('article.live-since', { date: when }),
  }[row.status as 'scheduled' | 'published' | 'modified']

  return line ?? ''
})

/* The constructor shows the draft as a page of the site. The link is the editor's to hand
   over: only this screen knows which entity it is editing. */
provideBlocksPreview({ url: computed(() => previewUrl.value), reload: reloadToken })

provideArticleEditor({
  article,
  values,
  options,
  related,
  prefix,
  base: props.base,
  canManage: canManage.value,
  disabled: locked,
  reload: () => load(true),
  save: () => save(),
})

function take(detail: ArticleDetail): void {
  article.value = detail.article
  values.value = detail.values
  revision.value = detail.revision
  prefix.value = detail.prefix
  previewUrl.value = detail.preview_url
  options.value = detail.options
  related.value = detail.related
  snapshot.value = JSON.stringify(detail.values)
  conflict.value = null
}

/**
 * Read the article again.
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
    void router.push(list.value)
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

/* The save on its way, and what it carries. */
let flight: { carried: string; done: Promise<void> } | undefined

/**
 * A save asked for while another is on its way waits for it instead of being dropped: leaving and
 * publishing read `dirty` right after, and a save that was skipped reads as one that failed.
 */
async function save(): Promise<void> {
  while (flight) {
    const { carried, done } = flight

    await done

    // That request wrote what it carried; go again only for what was typed while it was out.
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
  if (!article.value || !canManage.value) return

  clearTimeout(timer)

  // What is being sent, so that whatever is typed while the request is in flight stays dirty
  // and gets its own save rather than being marked as written.
  const sending = current.value
  const sent = values.value

  saving.value = true
  errors.value = {}

  try {
    const detail = await api.save(article.value.id, { values: sent, revision: revision.value })

    article.value = detail.article
    revision.value = detail.revision
    prefix.value = detail.prefix
    previewUrl.value = detail.preview_url
    options.value = detail.options
    related.value = detail.related
    conflict.value = null

    if (current.value === sending) snapshot.value = sending

    // The preview is rendered from the draft, and the draft is what was just written.
    reloadToken.value += 1
  } catch (error) {
    const failure = error as {
      status?: number
      body?: ArticleConflict & { errors?: Record<string, string[]> }
    }

    if (failure.status === 409 && failure.body?.data) {
      conflict.value = failure.body
    } else if (failure.body?.errors) {
      errors.value = failure.body.errors
      toast.danger(t('article.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

/** Give up what was typed and take the article as it now is. */
async function takeTheirs(): Promise<void> {
  const agreed = await confirm({
    title: t('article.conflict-theirs-title'),
    message: t('article.conflict-theirs-text'),
    confirmText: t('article.conflict-theirs'),
    cancelText: t('panel.cancel'),
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
 * Throw away what is waiting and keep what the site is showing (§10).
 *
 * Offered only while there is a difference to throw away. Asked about, because it is the one
 * button on this screen that destroys writing — and answered honestly: the last few minutes are
 * still in the autosave ring, but nothing in the panel lists them, so the question does not
 * promise a way back.
 */
async function discard(): Promise<void> {
  if (!article.value) return

  const agreed = await confirm({
    title: t('article.discard-title'),
    message: t('article.discard-text'),
    confirmText: t('article.discard'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  working.value = true

  try {
    take(await api.discard(article.value.id))
    toast.success(t('article.discarded'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

/**
 * Publishing is asked about, because it is the one action here that visitors see: everything
 * else on this screen writes a draft nobody outside the panel can read.
 *
 * The question names the day as well as the address, and that is the whole point of asking. A
 * date in the future means the article does not appear — it waits — and an editor who thought
 * they were publishing now would go and look for it on the site (§7).
 */
async function publish(): Promise<void> {
  const row = article.value

  if (!row) return

  const address = row.path === null ? null : `/${row.path}`
  const when = chosen.value === null ? t('article.publish-now') : dates.short(chosen.value)

  const agreed = await confirm({
    title: t('article.publish-title', { title: row.title }),
    message: future.value
      ? t('article.publish-later', { date: when })
      : address === null
        ? t('article.publish-nowhere')
        : t('article.publish-text', { address }),
    confirmText: future.value ? t('article.schedule') : t('panel.publish'),
    cancelText: t('panel.cancel'),
  })

  if (!agreed) return

  if (dirty.value) await save()
  if (conflict.value) return

  working.value = true

  try {
    await api.publish(row.id, chosen.value)
    await load(true)
    toast.success(future.value ? t('article.scheduled') : t('panel.published'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

function badge(): 'default' | 'success' | 'warning' | 'primary' {
  const status = article.value?.status

  if (status === 'published') return 'success'
  if (status === 'modified') return 'warning'

  return status === 'scheduled' ? 'primary' : 'default'
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
    title: t('article.leave-title'),
    message: t('article.leave-text'),
    confirmText: t('article.leave'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })
})
/* What leads away from the article. On a phone the head folds them into the ···. */
const actions = computed<ScreenAction[]>(() => {
  const leads: ScreenAction[] = []

  if (previewUrl.value) {
    leads.push({ key: 'preview', label: t('article.preview'), icon: 'eye', href: previewUrl.value })
  }

  if (article.value?.url && article.value.status !== 'draft') {
    leads.push({
      key: 'site',
      label: t('panel.open-on-site'),
      icon: 'link',
      href: article.value.url,
    })
  }

  /*
   * Throwing away what was written since the last publication: in the `···` at every width and
   * never a button, which is what `menu` is for. It used to stand in the action bar next to
   * "publish", one slip away from it — and it is offered at all only while there is a
   * difference between what is written and what is on the site.
   */
  if (article.value?.status === 'modified') {
    leads.push({
      key: 'discard',
      label: t('article.discard'),
      icon: 'refresh',
      danger: true,
      menu: true,
      run: () => void discard(),
    })
  }

  return leads
})
</script>

<template>
  <!-- No `data-wx-fill`: this screen is a window tall only while the constructor is the tab on
       screen, and that is a question only CSS can ask. `WxMain` still gives it a floor of one
       window, because it carries an action bar. -->
  <div class="wx-article-editor" @focusout="onFocusOut">
    <template v-if="loading || !article">
      <!-- Shaped like the screen it stands in for: the head on the ground, and what it is
           waiting for on the card that will hold it. Two bare strips on an empty background
           read as a page that failed rather than one that is a moment away. -->
      <wx-skeleton class="wx-article-editor__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="8" /></wx-card>
    </template>

    <template v-else>
      <!--
        The panel's head: the way out, the trail, the name and the state of the article. What is
        left in it is what leads away from the article — the draft on the site and the article on
        the site. Saving and publishing are down in the bar, where the eye is, and only there: a
        second copy of a button already on screen is not a reminder (§3.3).
      -->
      <wx-screen-head
        divider
        :back="list"
        :back-label="t('module.articles')"
        :title="title || t('article.untitled')"
        :subtitle="publication"
        :actions="actions"
      >
        <template #trail>
          <wx-breadcrumb size="sm" :label="t('article.trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="list">
              {{ t('module.articles') }}
            </wx-breadcrumb-item>
            <wx-breadcrumb-item current>{{ title || t('article.untitled') }}</wx-breadcrumb-item>
          </wx-breadcrumb>
        </template>

        <template #title-after>
          <wx-badge :type="badge()" dot>{{ t(`panel.status-${article.status}`) }}</wx-badge>
          <!-- What is waiting, said beside the state rather than instead of it, exactly as the
               list says it: the article is on the site, and there is something that is not.
               The bar used to carry this in words; the head is where the state of the record
               already lives. -->
          <wx-badge v-if="article.status === 'modified'" type="primary" round>
            {{ t('panel.edits') }}
          </wx-badge>
        </template>
      </wx-screen-head>

      <!-- Somebody else wrote while this editor was typing. Both versions still exist, so the
           question is which one the site gets — and it is a question, not a toast that
           disappears while the answer is being thought about. -->
      <wx-alert
        v-if="conflict"
        type="warning"
        :title="t('article.conflict-title')"
        :description="conflict.message"
      >
        <template #actions>
          <wx-button size="sm" variant="outline" @click="takeTheirs">
            {{ t('article.conflict-theirs') }}
          </wx-button>
          <wx-button size="sm" type="primary" @click="keepMine">
            {{ t('article.conflict-mine') }}
          </wx-button>
        </template>
      </wx-alert>

      <div class="wx-article-editor__screen">
        <wx-screen v-model="values" name="blog.article-form" :errors="errors" :disabled="locked" />
      </div>

      <!--
        The last row of the screen, not a layer over it: the tab above shrinks by the height of
        the bar and is never covered by it.
      -->
      <!--
        Two buttons and a mark, the same bar the page editor has.

        What used to stand here besides them: a sentence saying since when the article is on the
        site and that there are unpublished edits, and a third button to throw those edits away.
        Half the sentence is what the badge beside the name already says, the other half is a
        fact about the article rather than about the last keystroke and now stands under its
        name; and throwing work away is not a button to keep beside "publish" — it is in the
        `···`, where this panel keeps everything that cannot be undone.
      -->
      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button variant="outline" :loading="saving" :disabled="!dirty" @click="save">
          {{ t('article.save') }}
        </wx-button>

        <wx-button
          type="primary"
          :loading="working"
          :disabled="article.status === 'published' && !dirty"
          @click="publish"
        >
          {{ future ? t('article.schedule') : t('panel.publish') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-article-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  /* The constructor is the whole screen below the head, so the page is as tall as what it is
     drawn in and never taller: the tree, the form and the preview scroll inside themselves,
     and the head stays where it was put. */
  height: 100%;
  min-height: 0;
  container-type: inline-size;
}

.wx-article-editor__ghost {
  max-width: 420px;
}

.wx-article-editor__screen {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

/*
 * The tabs are the screen's, not this page's, so the height has to travel through them: every
 * box between the page and the constructor is a flex column that may shrink, or the constructor
 * sizes itself to its content and the whole page scrolls with the head on top of it.
 *
 * `:not([hidden])` is load-bearing. The tabs are kept alive, so the three that are not on
 * screen are still in the document, and a `display: flex` that reached them would make each one
 * a quarter of the height the visible one should have had.
 */
.wx-article-editor__screen :deep(.wx-screen-host),
.wx-article-editor__screen :deep(.wx-screen),
.wx-article-editor__screen :deep(.wx-tabs),
.wx-article-editor__screen :deep(.wx-tabs__layout),
.wx-article-editor__screen :deep(.wx-tabs__panels),
.wx-article-editor__screen :deep(.wx-tab:not([hidden])) {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

/*
 * No tab is a box of a fixed height any more, and none has a scrollbar of its own: the
 * constructor's preview is as tall as the page it shows and the browser scrolls it. A tab
 * that grows with its contents is also the only kind that does not clip them — a scroll box
 * cut the shadows off the cards inside it square at all four edges.
 */
</style>
