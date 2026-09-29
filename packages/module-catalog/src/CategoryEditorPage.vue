<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  provideHistorySubject,
  provideRecordAddress,
  useAdmin,
  useBodyKeys,
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
  WxBadge,
  WxBreadcrumb,
  WxBreadcrumbItem,
  WxButton,
  WxCard,
  WxSkeleton,
  type LocalizedValue,
} from '@webx-ui/core'
import type { ScreenModel } from '@webx-ui/schema'
import { createCatalogApi } from './api'
import { provideCatalogCategoryEditor } from './editor'
import { useCatalogMessages } from './i18n'
import { lastSegment } from './paths'
import { ancestry, useCategoryTree } from './store'
import type { CategoryDetail, CategoryRow } from './types'

/**
 * One category of the catalogue: a head, `catalog.category-form` under it, and one button.
 *
 * The shared category editor of the panel (`WxCategoryEditorPage`) is for flat lists with a
 * prefix; this one sits in a tree, at the root of the site (§4), and its trail is the branch it
 * hangs from. The «Filters» tab reads which category this is from here, to say whose setting it
 * inherits (§6.2).
 */
defineOptions({ name: 'WxCatalogCategoryEditor' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

const context = useAdmin()
const api = createCatalogApi(context)
const tree = useCategoryTree(context)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
useCatalogMessages()

const t = useTranslate('webx-catalog')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const id = computed(() => Number(route.params.id))
const list = computed(() => `${props.base}/categories`)

const category = ref<CategoryRow | null>(null)
const values = ref<ScreenModel>({})
const snapshot = ref('')

const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => context.can('catalog.manage'))
const canDelete = computed(() => context.can('catalog.delete'))
const locked = computed(() => !canManage.value || saving.value)

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value ? 'unsaved' : 'saved'
})

/** The name follows the field rather than the answer: a rename shows at the top as it is typed. */
const title = computed(() => {
  const written = values.value.name as LocalizedValue | string | null | undefined

  return (
    localizedValue(written, locales.active.value, category.value?.name ?? '') || t('panel.untitled')
  )
})

/** Where it answers, as a reader would type it: `/laptops/`. */
const address = computed(() => {
  const segment = lastSegment(category.value?.url)

  return segment === null ? undefined : `/${segment}/`
})

/** The categories above this one, from the top down. */
const parents = computed(() =>
  category.value
    ? ancestry(tree.nodes.value ?? [], category.value.id)
        .slice(1)
        .reverse()
    : [],
)

provideCatalogCategoryEditor({ category, locked })
provideHistorySubject({ id: computed(() => category.value?.id ?? null) })

/* The address of a category is flat at the root of the site at any depth (decision 23). */
provideRecordAddress({
  values,
  prefix: ref(''),
  path: computed(() => lastSegment(category.value?.url)),
  moving: () => t('panel.address-moving'),
})

function take(detail: CategoryDetail): void {
  category.value = detail.category
  values.value = detail.values
  snapshot.value = JSON.stringify(detail.values)
}

async function load(): Promise<void> {
  loading.value = true

  try {
    const [detail] = await Promise.all([api.category(id.value), tree.load().catch(() => [])])

    take(detail)
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
    take(await api.saveCategory(category.value.id, values.value))
    // A name, a publication or an address: every picker of the catalogue shows one of them.
    tree.drop()
    void tree.load().catch(() => undefined)
    toast.success(t('panel.saved'))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors && Object.keys(body.errors).length > 0) {
      errors.value = body.errors
      toast.danger(t('panel.save-failed'))
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
    title: t('panel.delete-category-title', { name: row.name }),
    message: t('panel.delete-category-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeCategory(row.id)
    tree.drop()
    toast.success(t('panel.category-deleted'))
    snapshot.value = current.value
    void router.push(list.value)
  } catch (error) {
    // "Not empty: products in it: 12" — the server's sentence is the one worth reading here.
    toast.danger(message(error))
  }
}

/* The browser's own guard: it cannot wait for a request, so all it can do is ask. */
function leaveGuard(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

function onKeydown(event: KeyboardEvent): void {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
    event.preventDefault()
    if (dirty.value) void save()
  }
}

useBodyKeys(onKeydown)

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
    title: t('panel.leave-title'),
    message: t('panel.leave-text'),
    confirmText: t('panel.leave'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })
})

const actions = computed<ScreenAction[]>(() => {
  const row = category.value
  const leads: ScreenAction[] = []

  if (!row) return leads

  // A hidden category answers 404; a link to that would be a link to an error page.
  if (row.url && row.visible) {
    leads.push({ key: 'site', label: t('panel.open-on-site'), icon: 'link', href: row.url })
  }

  leads.push({
    key: 'products',
    label: t('panel.show-products'),
    icon: 'list',
    menu: true,
    run: () =>
      void router.push({
        path: `${props.base}/products`,
        query: { 'f.category': String(row.id) },
      }),
  })

  if (canDelete.value) {
    leads.push({
      key: 'delete',
      label: t('panel.delete'),
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
  <div class="wx-catalog-category-editor" @keydown="onKeydown">
    <template v-if="loading || !category">
      <wx-skeleton class="wx-catalog-category-editor__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="6" /></wx-card>
    </template>

    <template v-else>
      <wx-screen-head
        divider
        :back="list"
        :back-label="t('module.categories')"
        :title="title"
        :subtitle="address"
        :actions="actions"
      >
        <template #trail>
          <wx-breadcrumb size="sm" :label="t('panel.category-trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="list">
              {{ t('module.categories') }}
            </wx-breadcrumb-item>
            <wx-breadcrumb-item
              v-for="parent in parents"
              :key="parent.id"
              :as="'router-link'"
              :to="`${list}/${parent.id}`"
            >
              {{ parent.name }}
            </wx-breadcrumb-item>
            <wx-breadcrumb-item current>{{ title }}</wx-breadcrumb-item>
          </wx-breadcrumb>
        </template>

        <template #title-after>
          <wx-badge v-if="!category.is_published" dot>{{ t('states.unpublished') }}</wx-badge>
          <wx-badge v-else-if="!category.visible" type="warning" dot>
            {{ t('panel.category-hidden') }}
          </wx-badge>
        </template>
      </wx-screen-head>

      <wx-screen
        v-model="values"
        name="catalog.category-form"
        :errors="errors"
        :disabled="locked"
      />

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button type="primary" :loading="saving" :disabled="!dirty" @click="save">
          {{ t('panel.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-category-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-catalog-category-editor__ghost {
  max-width: 420px;
}
</style>
