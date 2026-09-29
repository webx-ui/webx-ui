<script setup lang="ts">
import { computed, nextTick, onMounted, ref, useTemplateRef } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import {
  useLocales,
  useModal,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSpace,
  WxTreeSelect,
  type TreeSelectValue,
} from '@webx-ui/core'
import { createCatalogApi } from './api'
import { firstError } from './errors'
import { useCatalogMessages } from './i18n'
import { useCategoryTree } from './store'
import type { CategoryDetail } from './types'

/**
 * A new category: its name and where it hangs. Created unpublished — a shelf nobody filled is
 * not one to show visitors — at the end of its parent's children; the tree is where it is dragged
 * into place. The address is made of the name, and a taken one is refused under the name.
 */
const props = withDefaults(defineProps<{ parent?: number | null }>(), { parent: null })

const { open, resolve, dismiss } = useModal<CategoryDetail>()

const context = useAdmin()
const api = createCatalogApi(context)
const tree = useCategoryTree(context)
const locales = useLocales()
useCatalogMessages()

const t = useTranslate('webx-catalog')

const name = ref('')
const parent = ref<TreeSelectValue>(props.parent)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const field = useTemplateRef<HTMLElement>('field')

const nodes = computed(() => tree.nodes.value ?? [])

onMounted(async () => {
  void tree.load().catch(() => undefined)
  await nextTick()
  field.value?.querySelector('input')?.focus()
})

async function submit(): Promise<void> {
  if (name.value.trim() === '' || saving.value) return

  saving.value = true
  errors.value = {}

  try {
    const under = typeof parent.value === 'number' ? parent.value : null

    resolve(
      await api.createCategory(
        { name: { [locales.active.value]: name.value.trim() }, is_published: false },
        under,
      ),
    )
  } catch (error) {
    errors.value = (error as { body?: { errors?: Record<string, string[]> } }).body?.errors ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.new-category')" :width="480">
    <wx-form-item
      :label="t('category.name')"
      :error="firstError(errors, 'name') ?? firstError(errors, 'slug')"
      required
    >
      <div ref="field">
        <wx-input v-model="name" :aria-label="t('category.name')" @keyup.enter="submit" />
      </div>
    </wx-form-item>

    <wx-form-item :label="t('panel.field-parent')" :error="firstError(errors, 'parent_id')">
      <wx-tree-select
        v-model="parent"
        :nodes="nodes"
        node-key="id"
        label-key="name"
        children-key="children"
        show-path
        separator=" / "
        filterable
        clearable
        default-expand-all
        teleport
        :placeholder="t('panel.parent-root')"
      />
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" :disabled="name.trim() === ''" @click="submit">
          {{ t('panel.create') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
