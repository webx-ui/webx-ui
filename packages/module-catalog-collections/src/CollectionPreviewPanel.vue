<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useLocales, WxAlert, WxBadge, WxSkeleton, WxText } from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createCollectionsApi, previewQuestion } from './api'
import { useCollectionEditor } from './editor'
import { NAMESPACE, useCatalogCollectionsMessages } from './i18n'
import type { CollectionPreview, DroppedProduct } from './types'

/**
 * `wx-catalog-collection-preview`: what the values on the form would show (§6.3), asked a moment
 * after the last change, on nothing saved. Only what decides the products asks again. Beside the
 * list of a hand-picked collection, what will not reach the site and why: otherwise a manager who
 * added five products and sees three has nothing to go on.
 */
defineOptions({ name: 'WxCatalogCollectionPreview' })

const admin = useAdmin()
const api = createCollectionsApi(admin)
const editor = useCollectionEditor()
const locales = useLocales()
useCatalogCollectionsMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const preview = ref<CollectionPreview | null>(null)
const loading = ref(false)
const failed = ref('')

const question = computed(() =>
  editor ? previewQuestion(editor.values.value, locales.active.value) : null,
)
const asked = computed(() => JSON.stringify(question.value))

let timer: ReturnType<typeof setTimeout> | undefined
let ticket = 0

async function ask(): Promise<void> {
  if (!question.value) return

  const mine = ++ticket

  loading.value = true

  try {
    const answer = await api.preview(question.value)

    if (mine !== ticket) return

    preview.value = answer
    failed.value = ''
  } catch (error) {
    if (mine === ticket) failed.value = message(error)
  } finally {
    if (mine === ticket) loading.value = false
  }
}

watch(
  asked,
  () => {
    clearTimeout(timer)
    timer = setTimeout(() => void ask(), 350)
  },
  { immediate: true },
)

onBeforeUnmount(() => clearTimeout(timer))

const money = computed(
  () =>
    new Intl.NumberFormat(admin.i18n.state.locale, {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }),
)

function reason(row: DroppedProduct): string {
  if (row.reason !== 'status') return t(`panel.reason-${row.reason}`)

  return row.status
    ? t('panel.reason-status', { status: row.status })
    : t('panel.reason-status-unnamed')
}

const counted = computed(() => {
  const answer = preview.value

  if (!answer) return ''

  return t(answer.more ? 'panel.preview-more' : 'panel.preview-count', { count: answer.count })
})
</script>

<template>
  <div class="wx-catalog-collection-preview" :aria-busy="loading">
    <wx-alert v-if="failed" type="danger">{{ failed }}</wx-alert>

    <wx-skeleton v-if="!preview && !failed" :rows="4" />

    <template v-if="preview">
      <wx-alert v-if="preview.count === 0" type="warning">{{ t('panel.preview-empty') }}</wx-alert>
      <wx-text v-else size="sm" tone="muted">{{ counted }}</wx-text>

      <ul v-if="preview.products.length > 0" class="wx-catalog-collection-preview__list">
        <li
          v-for="product in preview.products"
          :key="product.id"
          class="wx-catalog-collection-preview__row"
        >
          <img
            v-if="product.image"
            class="wx-catalog-collection-preview__image"
            :src="product.image"
            alt=""
            loading="lazy"
          />
          <span v-else class="wx-catalog-collection-preview__image" />
          <span class="wx-catalog-collection-preview__name">{{ product.name }}</span>
          <span v-if="product.price != null" class="wx-catalog-collection-preview__price">
            {{ money.format(product.price) }}
            <s v-if="product.old_price != null" class="wx-catalog-collection-preview__old">
              {{ money.format(product.old_price) }}
            </s>
          </span>
        </li>
      </ul>

      <template v-if="preview.dropped.length > 0">
        <wx-text size="sm" weight="medium">{{ t('panel.preview-dropped') }}</wx-text>
        <ul class="wx-catalog-collection-preview__list">
          <li
            v-for="row in preview.dropped"
            :key="row.id"
            class="wx-catalog-collection-preview__dropped"
          >
            <span class="wx-catalog-collection-preview__name">{{ row.name }}</span>
            <wx-badge size="sm" type="warning" round>{{ reason(row) }}</wx-badge>
          </li>
        </ul>
      </template>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-collection-preview {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-collection-preview__list {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-catalog-collection-preview__row {
  display: grid;
  grid-template-columns: 40px minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-catalog-collection-preview__dropped {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-4) var(--wx-space-8);
}

.wx-catalog-collection-preview__image {
  display: block;
  width: 40px;
  height: 40px;
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-subtle);
  object-fit: cover;
}

.wx-catalog-collection-preview__name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-catalog-collection-preview__price {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  font-variant-numeric: tabular-nums;
}

.wx-catalog-collection-preview__old {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}
</style>
