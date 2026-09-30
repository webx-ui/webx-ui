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
import { createCatalogApi } from './api'
import { provideProductEditor } from './editor'
import { createGalleryVideo } from './galleryVideo'
import { useCatalogMessages } from './i18n'
import { lastSegment } from './paths'
import type { ProductDetail, ProductImage, ProductRow, SkuHolder } from './types'

/**
 * One product: a head, `catalog.product-form` under it, and one button (§11.1).
 *
 * One explicit save, as a category has: a product has no draft (decision 14), so what is saved is
 * on the site at once — a price that changes between two keystrokes is not something to do behind
 * the editor's back. The gallery is the exception and saves itself: its pictures are records of
 * their own, not values of this form (`GalleryField.vue`).
 *
 * The article number is unique across deleted products too (decision 5), so a refusal names the
 * product holding it — and this page turns that into a way to it, which a line under the field
 * cannot be.
 */
defineOptions({ name: 'WxCatalogProductEditor' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/catalog' })

const context = useAdmin()
const api = createCatalogApi(context)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
useCatalogMessages()

const t = useTranslate('webx-catalog')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const id = computed(() => Number(route.params.id))
const list = computed(() => `${props.base}/products`)

const product = ref<ProductRow | null>(null)
const values = ref<ScreenModel>({})
const images = ref<ProductImage[]>([])
const snapshot = ref('')

const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const holder = ref<SkuHolder | null>(null)

const canManage = computed(() => context.can('catalog.manage'))
const canDelete = computed(() => context.can('catalog.delete'))
const locked = computed(() => !canManage.value || saving.value)

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value ? 'unsaved' : 'saved'
})

const section = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'catalog')?.title ??
    t('module.title'),
)

/** The name follows the field rather than the answer: a rename shows at the top as it is typed. */
const title = computed(() => {
  const written = values.value.name as LocalizedValue | string | null | undefined

  return (
    localizedValue(written, locales.active.value, product.value?.name ?? '') || t('panel.untitled')
  )
})

/**
 * What the site does with it, under the name. Published but seen by nobody is the one state worth
 * a sentence: every other is what the badge beside the name already says.
 */
const subtitle = computed(() => {
  const row = product.value

  if (!row) return undefined

  if (row.is_published && !row.visible) return t('panel.invisible')

  return row.sku ?? undefined
})

/* The uploads of video files outlive the gallery's tab, so they are kept here (galleryVideo.ts). */
const video = createGalleryVideo({
  admin: context,
  product: computed(() => product.value?.id ?? null),
  images,
})

provideProductEditor({ product, images, locked, video, values })
provideHistorySubject({ id: computed(() => product.value?.id ?? null) })

/*
 * The address field is the panel's shared one (`wx-slug`). A product answers at `/{slug}-{id}`
 * (§4), so what is compared with the slug being typed is the registry's address without its
 * number — otherwise every product would look like one whose address is about to move.
 */
provideRecordAddress({
  values,
  prefix: ref(''),
  path: computed(() => {
    const segment = lastSegment(product.value?.url)

    return segment?.replace(new RegExp(`-${product.value?.id}$`), '') ?? null
  }),
  moving: () => t('panel.address-moving'),
})

function take(detail: ProductDetail): void {
  product.value = detail.product
  values.value = detail.values
  images.value = detail.images
  snapshot.value = JSON.stringify(detail.values)
}

async function load(): Promise<void> {
  loading.value = true

  try {
    take(await api.product(id.value))
  } catch (error) {
    toast.danger(message(error))
    void router.push(list.value)
  } finally {
    loading.value = false
  }
}

async function save(): Promise<void> {
  if (!product.value || saving.value || !canManage.value) return

  saving.value = true
  errors.value = {}
  holder.value = null

  try {
    // The gallery is not sent: it saves itself, and the answer carries it as the server has it.
    take(await api.saveProduct(product.value.id, values.value))
    toast.success(t('panel.saved'))
  } catch (error) {
    const body = (
      error as {
        body?: { errors?: Record<string, string[]>; meta?: { taken_by?: SkuHolder } }
      }
    ).body

    if (body?.errors && Object.keys(body.errors).length > 0) {
      errors.value = body.errors
      holder.value = body.meta?.taken_by ?? null
      toast.danger(t('panel.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

/**
 * Where the product holding the article number is opened: its editor, or «Deleted» searched by
 * that number — a deleted product has no editor, and the only way back to it is a restore.
 */
const holderLink = computed(() => {
  const found = holder.value

  if (!found) return null

  if (found.deleted) {
    const sku = typeof values.value.sku === 'string' ? values.value.sku : ''

    return { path: `${props.base}/deleted`, query: sku ? { q: sku } : {} }
  }

  return { path: `${props.base}/products/${found.id}` }
})

async function remove(): Promise<void> {
  const row = product.value

  if (!row) return

  const agreed = await confirm({
    title: t('panel.delete-product-title', { name: row.name }),
    message: t('panel.delete-product-text'),
    confirmText: t('panel.delete'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeProduct(row.id)
    toast.success(t('panel.product-deleted'))
    // Nothing left to protect: the product is in «Deleted», and asking about its unsaved edits on
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
  // A video going up pauses when the page goes; choosing the same file again continues it.
  if (video.busy.value) {
    const leave = await confirm({
      title: t('panel.video-leave-title'),
      message: t('panel.video-leave-text'),
      confirmText: t('panel.leave'),
      cancelText: t('panel.cancel'),
      tone: 'danger',
    })

    if (!leave) return false
  }

  if (!dirty.value || !canManage.value) return true

  return await confirm({
    title: t('panel.leave-title'),
    message: t('panel.leave-text'),
    confirmText: t('panel.leave'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })
})

/*
 * What leads away from the product. "Open on the site" is there for an unpublished one too: it
 * answers with the trimmed page (§5), and that page is what a visitor with an old link sees.
 */
const actions = computed<ScreenAction[]>(() => {
  const row = product.value
  const leads: ScreenAction[] = []

  if (!row) return leads

  if (row.url) {
    leads.push({ key: 'site', label: t('panel.open-on-site'), icon: 'link', href: row.url })
  }

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
  <div class="wx-catalog-product" @keydown="onKeydown">
    <template v-if="loading || !product">
      <!-- Shaped like the screen it stands in for: the head, and a card for what is coming. -->
      <wx-skeleton class="wx-catalog-product__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="8" /></wx-card>
    </template>

    <template v-else>
      <wx-screen-head
        divider
        :back="list"
        :back-label="t('module.products')"
        :title="title"
        :subtitle="subtitle"
        :actions="actions"
      >
        <template #trail>
          <wx-breadcrumb size="sm" :label="t('panel.product-trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="list">{{ section }}</wx-breadcrumb-item>
            <wx-breadcrumb-item v-if="product.category" current>
              {{ product.category.name }}
            </wx-breadcrumb-item>
            <wx-breadcrumb-item v-else current>{{ t('product.no-category') }}</wx-breadcrumb-item>
          </wx-breadcrumb>
        </template>

        <template #title-after>
          <wx-badge
            :type="product.is_published ? (product.visible ? 'success' : 'warning') : 'default'"
            dot
          >
            {{ t(`states.${product.state}`) }}
          </wx-badge>
          <wx-badge v-if="!product.category" type="warning" round>
            {{ t('product.no-category') }}
          </wx-badge>
        </template>
      </wx-screen-head>

      <!-- The article number is somebody else's: say whose, and take the reader there. Kept above
           the tabs, because the field it is about may be on a tab nobody is looking at. -->
      <wx-alert
        v-if="holder && holderLink"
        type="warning"
        :description="errors.sku?.[0] ?? ''"
        closable
        @close="holder = null"
      >
        <template #actions>
          <wx-button size="sm" variant="outline" @click="router.push(holderLink)">
            {{ holder.deleted ? t('panel.sku-open-deleted') : t('panel.sku-open') }}
          </wx-button>
        </template>
      </wx-alert>

      <wx-screen v-model="values" name="catalog.product-form" :errors="errors" :disabled="locked" />

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
.wx-catalog-product {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-catalog-product__ghost {
  max-width: 420px;
}
</style>
