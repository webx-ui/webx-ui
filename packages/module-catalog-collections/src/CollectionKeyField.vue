<script setup lang="ts">
import { computed } from 'vue'
import { WxCodeField, useTranslate } from '@webx-ui/module-admin'
import { useCollectionEditor } from './editor'
import { NAMESPACE, useCatalogCollectionsMessages } from './i18n'

/**
 * `wx-catalog-collection-key`: a key may be written while the collection is new and is read-only
 * from then on: templates and texts already name it (§2.2). Empty — the server makes it from the
 * title.
 *
 * Typed, it is a code as it is typed rather than a refusal on save (#125): «Летние шины» turns into
 * `letnie-shiny` under the fingers (`WxCodeField`), and the model brings whatever reaches it the
 * same way. A type of its own rather than `wx-code` because a screen node cannot say «read-only
 * once saved»: only the editor knows whether the collection exists.
 */
defineOptions({ name: 'WxCatalogCollectionKey' })

const props = withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false })

const value = defineModel<string | null>({ default: null })

const editor = useCollectionEditor()
useCatalogCollectionsMessages()

const t = useTranslate(NAMESPACE)

const fixed = computed(() => (editor?.collection.value ?? null) !== null)
</script>

<template>
  <wx-code-field
    :model-value="value ?? ''"
    :disabled="props.disabled"
    :readonly="fixed"
    :maxlength="64"
    :placeholder="fixed ? undefined : t('collection.key-placeholder')"
    @update:model-value="
      (code: unknown) => (value = typeof code === 'string' && code !== '' ? code : null)
    "
  />
</template>
