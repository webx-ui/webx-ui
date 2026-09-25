<script setup lang="ts">
import { ref, watch } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxCodeEditor, WxText } from '@webx-ui/core'
import { useBlocksMessages } from './i18n'

/**
 * `wx-data` in a component's sample form: a structure the caller passes from code, which nobody
 * edits in the panel except to try the component on something else. So it is JSON, as it
 * travels, in the code editor the rest of this screen is written in (§3.3).
 *
 * The text is kept as typed and the value changes only on JSON that parses: half a bracket is
 * what the text looks like on every keystroke on the way to the next valid one, and a sample
 * that became `null` in the middle of typing would redraw the stage empty each time.
 */
const props = withDefaults(
  defineProps<{
    modelValue?: unknown
    disabled?: boolean
    /** The shape's name (`recipes.card`): the help under the template describes it. */
    shape?: string
  }>(),
  { modelValue: undefined, disabled: false, shape: undefined },
)

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()

useBlocksMessages()
const t = useTranslate('webx-blocks')

const text = ref(format(props.modelValue))
const error = ref<string | null>(null)

function format(value: unknown): string {
  return JSON.stringify(value ?? {}, null, 2)
}

function onText(next: string): void {
  text.value = next

  try {
    const parsed: unknown = JSON.parse(next)

    error.value = null
    emit('update:modelValue', parsed)
  } catch (failure) {
    error.value = t('components.data-invalid', { error: (failure as Error).message })
  }
}

/* A value from outside (a restored version, a customised sample) replaces the text; the echo of
   what was just typed does not, or the caret would jump to the end on every keystroke. */
watch(
  () => props.modelValue,
  (value) => {
    let same = false

    try {
      same = JSON.stringify(JSON.parse(text.value)) === JSON.stringify(value ?? {})
    } catch {
      same = false
    }

    if (!same) {
      text.value = format(value)
      error.value = null
    }
  },
  { deep: true },
)
</script>

<template>
  <div class="wx-block-data">
    <wx-code-editor
      :model-value="text"
      language="json"
      lint
      :readonly="disabled"
      min-height="120px"
      max-height="320px"
      @update:model-value="onText"
    />
    <wx-text v-if="error" size="sm" tone="danger">{{ error }}</wx-text>
  </div>
</template>

<style scoped>
.wx-block-data {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
}
</style>
