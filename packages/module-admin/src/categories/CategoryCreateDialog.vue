<script setup lang="ts">
import { nextTick, onMounted, ref, useTemplateRef } from 'vue'
import { useModal, WxButton, WxDialog, WxFormItem, WxInput, WxSpace } from '@webx-ui/core'
import { useAdmin } from '../admin'
import { createCategoriesApi } from './api'
import type { CategoriesOptions, CategoryRow } from './types'
import { useCategoryWords } from './words'

/**
 * A new category: what it is called, and nothing else.
 *
 * The server makes the address out of the name — the same transliteration it would apply to
 * anything typed in the field — and the page that opens next is where the address, the picture
 * and the fields of the project are written, with room to say what changing the address costs.
 */
defineOptions({ name: 'WxCategoryCreateDialog' })

const props = defineProps<{ options: CategoriesOptions }>()

const { open, resolve, dismiss } = useModal<CategoryRow>()

const api = createCategoriesApi(useAdmin(), props.options.api)
const w = useCategoryWords(props.options.words)

const title = ref('')
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const field = useTemplateRef<HTMLElement>('field')

onMounted(async () => {
  await nextTick()
  field.value?.querySelector('input')?.focus()
})

/** The first line of a refusal under a field that may be named by language: `slug.en`. */
function errorOf(name: string): string | undefined {
  const key = Object.keys(errors.value).find((one) => one === name || one.startsWith(`${name}.`))

  return key === undefined ? undefined : errors.value[key]?.[0]
}

async function submit(): Promise<void> {
  if (title.value.trim() === '' || saving.value) return

  saving.value = true
  errors.value = {}

  try {
    resolve(await api.create({ title: title.value.trim() }))
  } catch (error) {
    // The address is what can be refused here, and it is refused under the name that made it:
    // the registry sees every kind of page at once, so "taken" can mean taken by an article.
    errors.value = (error as { body?: { errors?: Record<string, string[]> } }).body?.errors ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="w('new')" :width="440">
    <wx-form-item :label="w('field-title')" :error="errorOf('title') ?? errorOf('slug')" required>
      <div ref="field">
        <wx-input v-model="title" :aria-label="w('field-title')" @keyup.enter="submit" />
      </div>
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ w('cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" :disabled="title.trim() === ''" @click="submit">
          {{ w('create') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
