<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  provideRecordAddress,
  provideRelationOwner,
  useAdmin,
  useDates,
  useEditing,
  useErrorText,
  useTranslate,
  WxEditingAlerts,
  WxSaveState,
  WxScreen,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  localizedValue,
  toast,
  useLocales,
  WxActionBar,
  WxBadge,
  WxBreadcrumb,
  WxBreadcrumbItem,
  WxButton,
  WxCard,
  WxSkeleton,
  type LocalizedValue,
} from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'
import { createRecipesApi } from './api'
import { provideRecipeEditor } from './editor'
import { useRecipesMessages } from './i18n'
import type { RecipeConflict, RecipeDetail, RecipeRow } from './types'

/**
 * The editor of one recipe: a head that stays put, the described screen under it, and the bar
 * along the bottom where it is saved and published (§5.9).
 *
 * Everything between the head and the bar is `recipes.form` — the recipe, its settings, the SEO
 * card `module-seo` patches on and the history — so a project adds its own field (an author's
 * note) with a patch rather than a fork of this file. Saving is by autosave into the draft, and
 * the draft carries the categories, the services and the similar recipes too: the site changes,
 * all of it at once, only on "Publish". Edits are guarded by a revision: a save over somebody
 * else's is refused with a 409, merged with theirs, and asked about only where both changed.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/recipes' })

const context = useAdmin()
const api = createRecipesApi(context)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
const dates = useDates()
useRecipesMessages()

const t = useTranslate('webx-recipes')
const panel = useTranslate('webx-admin')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

/** How long after the last keystroke the draft goes to the server. */
const PAUSE = 1200

const id = computed(() => Number(route.params.id))
const list = computed(() => props.base)

const recipe = ref<RecipeRow | null>(null)
const values = ref<ScreenModel>({})
const revision = ref('')
const prefix = ref<string | null>(null)
const previewUrl = ref<string | null>(null)

const loading = ref(true)
const saving = ref(false)
const working = ref(false)
const errors = ref<Record<string, string[]>>({})

const snapshot = ref('')

const canManage = computed(() => context.can('recipes.manage'))

/*
 * Other people on the same recipe: a refused save is merged with theirs field by field and
 * saved again, only a field both changed is asked about, and a save made meanwhile — by a
 * colleague or an agent — is offered before this editor writes over it.
 */
const editing = useEditing<ScreenModel>({
  entity: 'recipes',
  id: () => recipe.value?.id,
  values,
  revision,
  read: async () => {
    const detail = await api.get(id.value)

    return { values: detail.values, revision: detail.revision }
  },
  adopt: (theirs) => {
    snapshot.value = JSON.stringify(theirs.values)
  },
  // Published or restored by somebody else: the badge and the address follow, and the form
  // stays as it is.
  refresh: () => refresh(),
  restore: async () => {
    if (recipe.value) await api.restore(recipe.value.id)
  },
  canWrite: () => canManage.value,
  busy: () => saving.value || flight !== undefined,
  screen: 'recipes.form',
})

const conflict = editing.conflict

/** Closed for writing: no permission, or a publication in flight. */
const locked = computed(() => !canManage.value || working.value)

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value ? 'unsaved' : 'saved'
})

/** The name follows the field rather than the answer: a rename shows at the top as it is typed. */
const title = computed(() => {
  const written = values.value.title as LocalizedValue | string | null | undefined

  return localizedValue(written, locales.active.value, recipe.value?.title ?? '')
})

/** Since when it is on the site, under the name — the one fact the badge beside it cannot carry. */
const publication = computed(() => {
  const row = recipe.value

  if (!row || (row.status !== 'published' && row.status !== 'modified')) return ''

  return t('recipe.live-since', { date: dates.short(row.published_at) })
})

/* "Similar recipes" pick from recipes, and never offer the one they are on. */
provideRelationOwner({ type: 'recipe', id: computed(() => recipe.value?.id ?? null) })

/* The address field is the panel's shared one (`wx-slug`), and this is what it prints. */
provideRecordAddress({
  values,
  prefix,
  path: computed(() => recipe.value?.path),
  moving: () => t('recipe.address-moving'),
})

provideRecipeEditor({ recipe, canManage: canManage.value, reload: () => load(true) })

function take(detail: RecipeDetail): void {
  recipe.value = detail.recipe
  values.value = detail.values
  revision.value = detail.revision
  prefix.value = detail.prefix
  previewUrl.value = detail.preview_url
  snapshot.value = JSON.stringify(detail.values)
  editing.opened(detail.values)
  conflict.value = null
}

/**
 * Read the recipe again — quietly unless this is the first time: the skeleton replaces the whole
 * screen, and a publication answered with it would throw the editor back onto the first tab.
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

/**
 * What surrounds the form, read again: the row with its status, the address, the preview. Not
 * the values — the form is somebody's work in progress, and a change of its content is the
 * notice's to offer.
 */
async function refresh(): Promise<void> {
  try {
    const detail = await api.get(id.value)

    recipe.value = detail.recipe
    prefix.value = detail.prefix
    previewUrl.value = detail.preview_url
  } catch {
    // The heartbeat says what happened; a row that could not be read again stays as it was.
  }
}

/* One pending save at a time, and one pending pause. */
let timer: ReturnType<typeof setTimeout> | undefined

function schedule(): void {
  if (!canManage.value || conflict.value || !dirty.value || editing.stopped.value) return

  clearTimeout(timer)
  timer = setTimeout(() => void save(), PAUSE)
}

/** A field was left: write now rather than at the end of a pause nobody is waiting through. */
function onFocusOut(): void {
  if (!canManage.value || conflict.value || !dirty.value || saving.value || editing.stopped.value)
    return

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
  if (!recipe.value || !canManage.value) return

  clearTimeout(timer)

  // What is being sent, so that whatever is typed while the request is in flight stays dirty and
  // gets its own save rather than being marked as written.
  const sending = current.value
  const sent = values.value

  saving.value = true
  errors.value = {}

  try {
    const detail = await api.save(recipe.value.id, { values: sent, revision: revision.value })

    recipe.value = detail.recipe
    revision.value = detail.revision
    prefix.value = detail.prefix
    previewUrl.value = detail.preview_url
    conflict.value = null

    if (current.value === sending) snapshot.value = sending

    // What the server holds now is what was sent: the base of the next merge.
    editing.opened(sent)
  } catch (error) {
    const failure = error as {
      status?: number
      body?: RecipeConflict & { errors?: Record<string, string[]> }
    }

    if (failure.status === 409 && failure.body?.data) {
      const theirs = failure.body.data

      // Nothing overlapping: both edits are in the form now, and they go to the server again.
      if (
        editing.refused({
          values: theirs.values,
          revision: theirs.revision,
          changed: failure.body.changed,
        })
      ) {
        recipe.value = theirs.recipe
        void save()
      }
    } else if (failure.body?.errors) {
      errors.value = failure.body.errors
      toast.danger(t('recipe.save-failed'))
    } else if (!(await editing.failed(error))) {
      // In the bin is said by the notice above the form, with the way back; anything else here.
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

/** Give up what was typed and take the recipe as it now is; the component asked first. */
async function takeTheirs(): Promise<void> {
  await load(true)
}

/** Throw away what is waiting — asked about, because it is the one button here that loses writing. */
async function discard(): Promise<void> {
  if (!recipe.value) return

  const agreed = await confirm({
    title: panel('editor.discard-title'),
    message: panel('editor.discard-text'),
    confirmText: t('recipe.discard'),
    // Not «Cancel»: beside «Discard changes» the two read as the same word.
    cancelText: panel('editor.keep-changes'),
    tone: 'danger',
  })

  if (!agreed) return

  working.value = true

  try {
    take(await api.discard(recipe.value.id))
    toast.success(t('recipe.discarded'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

/** Publishing is asked about, because it is the one action here that visitors see. */
async function publish(): Promise<void> {
  // Saved before asking: the question names the address the draft will publish at, and only a
  // saved draft has one — an address typed but not saved was asked about under the old one.
  if (dirty.value) await save()
  if (conflict.value || dirty.value) return

  if (!recipe.value) return

  // The draft on the server may hold somebody else's edit this editor never pulled in: said
  // before it goes on the site, with whose it is and where, rather than published unseen.
  const held = await editing.beforePublish()

  if (!held) return

  const row = recipe.value

  // Publishing moves a renamed slug, so the question names where the page will be, not where
  // it is — and says the old address will lead there, since that is what happens to it.
  const next = row.next_path ?? row.path
  const old =
    row.next_path != null && row.path !== null
      ? ` ${panel('editor.publish-moves', { old: `/${row.path}` })}`
      : ''

  // One question is enough: whoever just agreed to publish somebody else's changes has said yes.
  const agreed =
    held.asked ||
    (await confirm({
      title: t('recipe.publish-title', { title: title.value || row.title }),
      message:
        next === null
          ? t('recipe.publish-nowhere')
          : t('recipe.publish-text', { address: `/${next}` }) + old,
      confirmText: t('panel.publish'),
      cancelText: t('panel.cancel'),
    }))

  if (!agreed) return

  working.value = true

  try {
    await api.publish(row.id, held.revision)
    await load(true)
    toast.success(t('panel.published'))
  } catch (error) {
    if (!(await editing.failed(error))) toast.danger(message(error))
  } finally {
    working.value = false
  }
}

/**
 * Taking it off the site is asked about, as publishing is: visitors are who notice. Nothing
 * written is lost, and «Publish» brings it back at the same address.
 */
async function unpublish(): Promise<void> {
  const row = recipe.value

  if (!row) return

  const agreed = await confirm({
    title: panel('editor.unpublish-title'),
    message: panel('editor.unpublish-text'),
    confirmText: panel('editor.unpublish'),
    cancelText: panel('editor.keep-published'),
  })

  if (!agreed) return

  working.value = true

  try {
    await api.unpublish(row.id)
    await load(true)
    toast.success(panel('editor.unpublished'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

function badge(): 'default' | 'success' {
  const status = recipe.value?.status

  return status === 'published' || status === 'modified' ? 'success' : 'default'
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
 * Leaving flushes the pause rather than asking about it; the question is asked only when the save
 * did not go through — a conflict, a refused field, a server that is not there.
 */
onBeforeRouteLeave(async () => {
  if (!dirty.value || !canManage.value) return true

  await save()

  if (!dirty.value) return true

  return await confirm({
    title: t('recipe.leave-title'),
    message: t('recipe.leave-text'),
    confirmText: t('recipe.leave'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })
})

/* What leads away from the recipe. On a phone the head folds them into the ···. */
const actions = computed<ScreenAction[]>(() => {
  const leads: ScreenAction[] = []

  if (previewUrl.value) {
    leads.push({ key: 'preview', label: t('recipe.preview'), icon: 'eye', href: previewUrl.value })
  }

  if (recipe.value?.url && recipe.value.status !== 'draft') {
    leads.push({
      key: 'site',
      label: t('panel.open-on-site'),
      icon: 'link',
      href: recipe.value.url,
    })
  }

  // On the site now: the way off it is a button of its own beside «Open on the site», not a
  // line in the ···. Hiding loses nothing and is undone by «Publish».
  if (
    canManage.value &&
    (recipe.value?.status === 'published' || recipe.value?.status === 'modified')
  ) {
    leads.push({
      key: 'unpublish',
      label: panel('editor.unpublish'),
      icon: 'eye-off',
      run: () => void unpublish(),
    })
  }

  // Throwing away writing lives in the ···, never beside "publish", and only while there is a
  // difference between what is written and what is on the site.
  if (recipe.value?.status === 'modified') {
    leads.push({
      key: 'discard',
      label: t('recipe.discard'),
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
  <div class="wx-recipe-editor" @focusout="onFocusOut">
    <template v-if="loading || !recipe">
      <wx-skeleton class="wx-recipe-editor__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="8" /></wx-card>
    </template>

    <template v-else>
      <wx-screen-head
        divider
        :back="list"
        :back-label="t('module.recipes')"
        :title="title || t('recipe.untitled')"
        :subtitle="publication"
        :actions="actions"
      >
        <template #trail>
          <wx-breadcrumb size="sm" :label="t('recipe.trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="list">
              {{ t('module.recipes') }}
            </wx-breadcrumb-item>
            <wx-breadcrumb-item current>{{ title || t('recipe.untitled') }}</wx-breadcrumb-item>
          </wx-breadcrumb>
        </template>

        <template #title-after>
          <wx-badge :type="badge()" dot>{{ t(`panel.status-${recipe.status}`) }}</wx-badge>
          <wx-badge v-if="recipe.status === 'modified'" type="primary" round>
            {{ t('panel.edits') }}
          </wx-badge>
        </template>
      </wx-screen-head>

      <!-- Somebody else wrote while this editor was open. What does not overlap is merged
           without a word; what does is listed place by place. -->
      <wx-editing-alerts :editing="editing" @save="save" @theirs="takeTheirs" />

      <wx-screen v-model="values" name="recipes.form" :errors="errors" :disabled="locked" />

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state v-if="!editing.stopped.value" :state="state" />
        </template>

        <wx-button
          variant="outline"
          :loading="saving"
          :disabled="!dirty || editing.stopped.value"
          :title="editing.blocked.value"
          @click="save"
        >
          {{ t('recipe.save') }}
        </wx-button>

        <wx-button
          type="primary"
          :loading="working"
          :disabled="editing.stopped.value || (recipe.status === 'published' && !dirty)"
          :title="editing.blocked.value"
          @click="publish"
        >
          {{ t('panel.publish') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
/*
 * No constructor here, so nothing needs the height of the window: the page scrolls, and the
 * panel gives a screen with an action bar a floor tall enough for the bar to sit at the bottom
 * (`WxMain`, CLAUDE.md §4 on `data-wx-fill`).
 */
.wx-recipe-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-height: 0;
  container-type: inline-size;
}

.wx-recipe-editor__ghost {
  max-width: 420px;
}
</style>
