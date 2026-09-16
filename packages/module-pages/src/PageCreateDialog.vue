<script setup lang="ts">
import { nextTick, onMounted, ref, useTemplateRef, watch } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { useModal, WxButton, WxDialog, WxFormItem, WxInput, WxSpace } from '@webx-ui/core'
import PagePicker from './PagePicker.vue'
import { createPagesApi } from './api'
import { usePagesMessages } from './i18n'
import { slugify } from './slug'
import type { PageRow } from './types'

/**
 * A new page: what it is called, where it lives, and what it is called in the address.
 *
 * The address follows the title while nobody has touched it, and stops the moment somebody
 * does — a field that keeps rewriting what was typed into it is a field people fight.
 */
const props = withDefaults(defineProps<{ parent?: PageRow | null }>(), { parent: null })

const { open, resolve, dismiss } = useModal<PageRow>()

const context = useAdmin()
const api = createPagesApi(context)
usePagesMessages()

const t = useTranslate('webx-pages')

const title = ref('')
const slug = ref('')
const parentId = ref<number | null>(props.parent?.id ?? null)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const field = useTemplateRef<HTMLElement>('field')

const edited = ref(false)

watch(title, (value) => {
  if (!edited.value) slug.value = slugify(value)
})

onMounted(async () => {
  await nextTick()
  field.value?.querySelector('input')?.focus()
})

async function submit(): Promise<void> {
  if (title.value.trim() === '' || saving.value) return

  saving.value = true
  errors.value = {}

  try {
    resolve(
      await api.create({
        title: title.value.trim(),
        slug: slug.value.trim(),
        parent_id: parentId.value,
      }),
    )
  } catch (error) {
    // The address is the one thing that can be refused here, and it is refused under its own
    // field: the registry sees every kind of entity, so "taken" can mean taken by a product.
    errors.value = (error as { body?: { errors?: Record<string, string[]> } }).body?.errors ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('page.new-title')" :width="460">
    <wx-form-item :label="t('page.field-title')" :error="errors.title?.[0]" required>
      <div ref="field">
        <wx-input v-model="title" :aria-label="t('page.field-title')" @keyup.enter="submit" />
      </div>
    </wx-form-item>

    <wx-form-item
      :label="t('page.field-slug')"
      :error="errors.slug?.[0]"
      :help="t('page.slug-help')"
    >
      <wx-input
        v-model="slug"
        :aria-label="t('page.field-slug')"
        @input="edited = true"
        @keyup.enter="submit"
      />
    </wx-form-item>

    <wx-form-item :label="t('page.field-parent')" :error="errors.parent_id?.[0]">
      <page-picker v-model="parentId" :selected="props.parent ? [props.parent] : []" />
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('page.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" :disabled="title.trim() === ''" @click="submit">
          {{ t('page.create') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
