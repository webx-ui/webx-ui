<script setup lang="ts">
import { ref, watch } from 'vue'
import { WxAlert, WxSkeleton } from '@webx-ui/core'
import { WxScreenRenderer, type ScreenModel, type ScreenNode } from '@webx-ui/schema'
import { useAdmin } from './admin'
import { useTranslate } from './i18n'

/**
 * A screen of the panel, by name.
 *
 * Fetches the tree the server hands out — patched, permission-filtered, translated — lays the
 * project's own patch over it, and draws it with the renderer against the panel's registry,
 * dictionary and permissions. It does not load values and does not save: that is the page's
 * business, the way it is for any form.
 */
const props = withDefaults(
  defineProps<{
    /** `<module>.<screen>`, e.g. `settings.index`. */
    name: string
    /** Server validation errors by field name, shown under the fields. */
    errors?: Record<string, string[]>
    disabled?: boolean
    labelPosition?: 'top' | 'left'
    labelWidth?: string
    size?: 'sm' | 'md' | 'lg'
  }>(),
  {
    errors: undefined,
    disabled: false,
    labelPosition: undefined,
    labelWidth: undefined,
    size: undefined,
  },
)

const emit = defineEmits<{
  /** The tree arrived; a page that wants to know what it draws reads it here. */
  loaded: [root: ScreenNode[]]
}>()

const model = defineModel<ScreenModel>({ default: () => ({}) })

const admin = useAdmin()
const t = useTranslate('webx-admin')

const root = ref<ScreenNode[] | null>(null)
const failed = ref<string | null>(null)

async function load(): Promise<void> {
  root.value = null
  failed.value = null

  try {
    const tree = await admin.loadScreen(props.name)
    root.value = tree
    emit('loaded', tree)
  } catch (error) {
    failed.value = error instanceof Error ? error.message : String(error)
  }
}

// A new language means a new tree: the labels inside it were translated on the server.
watch([() => props.name, () => admin.i18n.state.locale], () => void load(), { immediate: true })

defineExpose({ reload: load })
</script>

<template>
  <div class="wx-screen-host">
    <wx-alert v-if="failed" type="danger" :title="t('shell.error-title')" :description="failed" />
    <wx-skeleton v-else-if="root === null" :rows="4" />
    <wx-screen-renderer
      v-else
      v-model="model"
      :root="root"
      :patch="admin.screenPatch(name)"
      :types="admin.types"
      :errors="errors"
      :translate="admin.i18n.t"
      :can="admin.can"
      :disabled="disabled"
      :label-position="labelPosition"
      :label-width="labelWidth"
      :size="size"
    />
  </div>
</template>
