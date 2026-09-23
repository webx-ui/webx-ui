<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
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
import { useAdmin } from '../admin'
import { useErrorText } from '../errors'
import SaveState from '../SaveState.vue'
import Screen from '../Screen.vue'
import ScreenHead from '../ScreenHead.vue'
import type { ScreenAction } from '../types'
import { createCategoriesApi } from './api'
import { provideCategoryEditor } from './editor'
import type { CategoriesOptions, CategoryRow } from './types'
import { useCategoryWords } from './words'

/**
 * One category, on a page of its own: a head, the described screen under it, and one button.
 *
 * A page and not a dialog, because a category is a record — an address, a picture, SEO and the
 * fields a project patched on — and a project that adds a tab to it needs a screen to add it to
 * (decision 7 of the services spec). The tabs are the module's screen (`blog.category-form`);
 * what this page owns is the head, the bar and the save.
 *
 * One explicit save rather than the autosave of an article: a category has no draft, so what is
 * saved is on the site at once, and a menu entry that renames itself between two keystrokes is
 * not something to do behind the editor's back. A refused field opens its own tab — the screen's
 * tabs do that themselves (`ScreenTabs.vue` in `@webx-ui/schema`).
 */
defineOptions({ name: 'WxCategoryEditorPage' })

const props = defineProps<{ options: CategoriesOptions }>()

const context = useAdmin()
const api = createCategoriesApi(context, props.options.api)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
const w = useCategoryWords(props.options.words)
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const id = computed(() => Number(route.params.id))
const list = computed(() => props.options.path)

const category = ref<CategoryRow | null>(null)
const values = ref<ScreenModel>({})
const prefix = ref<string | null>(null)
const snapshot = ref('')

const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => context.can(props.options.manage))

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value ? 'unsaved' : 'saved'
})

const section = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === props.options.module)?.title ??
    '',
)

/** The name follows the field rather than the answer: a rename shows at the top as it is typed. */
const title = computed(() => {
  const written = values.value.title as LocalizedValue | string | null | undefined

  return localizedValue(written, locales.active.value, category.value?.name ?? '') || w('untitled')
})

provideCategoryEditor({ category, values, prefix, moving: () => w('address-moving') })

function take(detail: { category: CategoryRow; values: ScreenModel; prefix: string | null }): void {
  category.value = detail.category
  values.value = detail.values
  prefix.value = detail.prefix
  snapshot.value = JSON.stringify(detail.values)
}

async function load(): Promise<void> {
  loading.value = true

  try {
    take(await api.get(id.value))
  } catch (error) {
    toast.danger(message(error))
    void router.push(list.value)
  } finally {
    loading.value = false
  }
}

async function save(): Promise<void> {
  if (!category.value || saving.value || !canManage.value) return

  saving.value = true
  errors.value = {}

  try {
    take(await api.save(category.value.id, values.value))
    toast.success(w('saved'))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(w('save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

async function remove(): Promise<void> {
  const row = category.value

  if (!row) return

  const agreed = await confirm({
    title: w('delete-title', { name: row.name }),
    message: w('delete-text'),
    confirmText: w('delete'),
    cancelText: w('cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(row.id)
    toast.success(w('deleted'))
    // Nothing left to protect: the record is in the bin, and asking about its unsaved edits on
    // the way out would be asking about a page that is gone.
    snapshot.value = current.value
    void router.push(list.value)
  } catch (error) {
    toast.danger(message(error))
  }
}

/* The browser's own guard: it cannot wait for a request, so all it can do is ask. */
function leaveGuard(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

/* Ctrl+S saves, as it does everywhere else that has a save button. */
function onKeydown(event: KeyboardEvent): void {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
    event.preventDefault()
    if (dirty.value) void save()
  }
}

watch(id, () => void load())

onMounted(() => {
  void load()
  window.addEventListener('beforeunload', leaveGuard)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', leaveGuard)
})

onBeforeRouteLeave(async () => {
  if (!dirty.value || !canManage.value) return true

  return await confirm({
    title: w('leave-title'),
    message: w('leave-text'),
    confirmText: w('leave'),
    cancelText: w('cancel'),
    tone: 'danger',
  })
})

/* What leads away from the category. On a phone the head folds them into the ···. */
const actions = computed<ScreenAction[]>(() => {
  const row = category.value
  const leads: ScreenAction[] = []

  if (!row) return leads

  if (row.url && row.is_visible) {
    leads.push({ key: 'site', label: w('open-on-site'), icon: 'link', href: row.url })
  }

  if (canManage.value) {
    leads.push({
      key: 'delete',
      label: w('delete'),
      icon: 'trash',
      danger: true,
      menu: true,
      run: () => void remove(),
    })
  }

  return leads
})
</script>

<template>
  <div class="wx-category-editor" @keydown="onKeydown">
    <template v-if="loading || !category">
      <!-- Shaped like the screen it stands in for: the head, and a card for what is coming. -->
      <wx-skeleton class="wx-category-editor__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="6" /></wx-card>
    </template>

    <template v-else>
      <screen-head
        divider
        :back="list"
        :back-label="section"
        :title="title"
        :subtitle="category.path === null ? undefined : `/${category.path}`"
        :actions="actions"
      >
        <template #trail>
          <wx-breadcrumb size="sm" :label="w('trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="list">{{ section }}</wx-breadcrumb-item>
            <wx-breadcrumb-item current>{{ title }}</wx-breadcrumb-item>
          </wx-breadcrumb>
        </template>

        <!-- Hidden is the one state a category has, and it is said where the name is. -->
        <template v-if="!category.is_visible" #title-after>
          <wx-badge dot>{{ w('hidden') }}</wx-badge>
        </template>
      </screen-head>

      <screen
        v-model="values"
        :name="props.options.screen"
        :errors="errors"
        :disabled="!canManage || saving"
      />

      <wx-action-bar v-if="canManage">
        <template #state>
          <save-state :state="state" />
        </template>

        <wx-button type="primary" :loading="saving" :disabled="!dirty" @click="save">
          {{ w('save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-category-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-category-editor__ghost {
  max-width: 420px;
}
</style>
