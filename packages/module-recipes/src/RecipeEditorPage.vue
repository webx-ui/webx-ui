<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  provideRecordAddress,
  provideRelationOwner,
  useAdmin,
  useDates,
  useErrorText,
  useTranslate,
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
 * else's is refused with a 409 and the question of which version the site gets.
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
const conflict = ref<RecipeConflict | null>(null)

const snapshot = ref('')

const canManage = computed(() => context.can('recipes.manage'))

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
  } catch (error) {
    const failure = error as {
      status?: number
      body?: RecipeConflict & { errors?: Record<string, string[]> }
    }

    if (failure.status === 409 && failure.body?.data) {
      conflict.value = failure.body
    } else if (failure.body?.errors) {
      errors.value = failure.body.errors
      toast.danger(t('recipe.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

/** Give up what was typed and take the recipe as it now is. */
async function takeTheirs(): Promise<void> {
  const agreed = await confirm({
    title: t('recipe.conflict-theirs-title'),
    message: t('recipe.conflict-theirs-text'),
    confirmText: t('recipe.conflict-theirs'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  await load(true)
}

/** Keep what was typed and write it over the other version — which is still in the history. */
async function keepMine(): Promise<void> {
  const theirs = conflict.value

  if (!theirs) return

  revision.value = theirs.data.revision
  conflict.value = null

  await save()
}

/** Throw away what is waiting — asked about, because it is the one button here that loses writing. */
async function discard(): Promise<void> {
  if (!recipe.value) return

  const agreed = await confirm({
    title: t('recipe.discard-title'),
    message: t('recipe.discard-text'),
    confirmText: t('recipe.discard'),
    cancelText: t('panel.cancel'),
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
  const row = recipe.value

  if (!row) return

  const agreed = await confirm({
    title: t('recipe.publish-title', { title: title.value || row.title }),
    message:
      row.path === null
        ? t('recipe.publish-nowhere')
        : t('recipe.publish-text', { address: `/${row.path}` }),
    confirmText: t('panel.publish'),
    cancelText: t('panel.cancel'),
  })

  if (!agreed) return

  if (dirty.value) await save()
  if (conflict.value) return

  working.value = true

  try {
    await api.publish(row.id)
    await load(true)
    toast.success(t('panel.published'))
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

      <!-- Somebody else wrote while this editor was typing. Both versions still exist, so the
           question is which one the site gets — a question, not a toast that disappears. -->
      <wx-alert
        v-if="conflict"
        type="warning"
        :title="t('recipe.conflict-title')"
        :description="conflict.message"
      >
        <template #actions>
          <wx-button size="sm" variant="outline" @click="takeTheirs">
            {{ t('recipe.conflict-theirs') }}
          </wx-button>
          <wx-button size="sm" type="primary" @click="keepMine">
            {{ t('recipe.conflict-mine') }}
          </wx-button>
        </template>
      </wx-alert>

      <wx-screen v-model="values" name="recipes.form" :errors="errors" :disabled="locked" />

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button variant="outline" :loading="saving" :disabled="!dirty" @click="save">
          {{ t('recipe.save') }}
        </wx-button>

        <wx-button
          type="primary"
          :loading="working"
          :disabled="recipe.status === 'published' && !dirty"
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
