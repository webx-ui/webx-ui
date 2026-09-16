<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { toast, useLocales, WxAlert, WxFormItem, WxText } from '@webx-ui/core'
import PagePicker from './PagePicker.vue'
import { createPagesApi } from './api'
import { usePageEditor } from './editor'
import { usePagesMessages } from './i18n'

/**
 * Where the page sits: the whole address it answers at, and the page it is inside.
 *
 * The address is shown built rather than left as a slug in a box. `shoes` says nothing about
 * whether the page is under the catalogue or at the top of the site, and the tree is exactly
 * what an editor is deciding when they look at this — so the field edits the last segment and
 * this prints the sentence it is part of, following the field as it is typed.
 *
 * Moving is not a draft. The tree is applied at once, because a page that sits in two places
 * until it is published is a tree no screen can draw — so the picker asks the server there and
 * then, and the warning beside it says what that costs.
 */
defineOptions({ name: 'WxPagePlace' })

const context = useAdmin()
const api = createPagesApi(context)
const editor = usePageEditor()
const locales = useLocales()
usePagesMessages()

const t = useTranslate('webx-pages')

const moving = ref(false)
const parent = ref<number | null>(null)

const page = computed(() => editor?.page.value ?? null)

/**
 * The address of the page above in the language being edited, or `null` where it has none.
 *
 * The home page's is `''` — the front page of the site — so the two are not the same answer
 * and cannot both be an empty string.
 */
const prefix = computed<string | null>(() => {
  if (editor?.ancestors.value.length === 0) return null

  return editor?.prefixes.value[locales.active.value] ?? null
})

/** The last segment as it stands in the field, not as the registry last heard it. */
const slug = computed(() => {
  const written = editor?.values.value.slug

  if (typeof written === 'string') return written

  // The language being edited and no other. Falling back on a language that does have words
  // would print an address the site does not answer at (§8).
  return (written as Record<string, string> | undefined)?.[locales.active.value] ?? ''
})

const address = computed(() => {
  if (page.value?.is_home) return '/'
  if (prefix.value === null || slug.value === '') return null

  return `/${[prefix.value, slug.value].filter(Boolean).join('/')}`
})

watch(
  page,
  (current) => {
    parent.value = current?.parent_id ?? null
  },
  { immediate: true },
)

async function move(target: number | null): Promise<void> {
  const current = page.value

  if (!current || target === null || target === current.parent_id || moving.value) return

  moving.value = true

  try {
    const result = await api.move(current.id, target, 'inside')

    toast.success(
      result.addresses_changed > 1
        ? t('page.moved', { count: result.addresses_changed })
        : t('page.moved-one'),
    )
    await editor?.reload()
  } catch (error) {
    toast.danger((error as { body?: { message?: string } }).body?.message ?? String(error))
    parent.value = current.parent_id
  } finally {
    moving.value = false
  }
}
</script>

<template>
  <div v-if="page" class="wx-page-place">
    <wx-form-item :label="t('page.address')">
      <wx-text v-if="address" mono class="wx-page-place__address">{{ address }}</wx-text>
      <wx-text v-else size="sm" tone="muted">{{ t('page.no-address') }}</wx-text>
    </wx-form-item>

    <template v-if="page.can.move">
      <wx-form-item :label="t('page.field-parent')" :help="t('page.parent-help')">
        <page-picker
          v-model="parent"
          :exclude="[page.id]"
          :selected="editor?.ancestors.value ?? []"
          @update:model-value="move"
        />
      </wx-form-item>

      <!-- Said before the move rather than after it: the old addresses keep working as
           redirects, which is the part that decides whether this is safe to do on a live
           site, and nobody reads it in a toast that has already gone. -->
      <wx-alert type="info" variant="soft" :description="t('page.parent-warning')" />
    </template>
  </div>
</template>

<style scoped>
.wx-page-place {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-page-place__address {
  word-break: break-all;
}
</style>
