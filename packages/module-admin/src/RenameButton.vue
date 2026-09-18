<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { WxAction, WxButton, WxInput, WxPopover } from '@webx-ui/core'
import type { ActionSize } from '@webx-ui/core'
import { useTranslate } from './i18n'

/**
 * Renaming, made an act rather than a side effect.
 *
 * A heading that is quietly an `<input>` reads as a heading: nothing says it can be typed in,
 * and the reader finds out by accident — or never. Worse, it is bound straight to the record,
 * so a stray keystroke on what looks like a title is a rename nobody asked for.
 *
 * So the name is text, with a pencil beside it. The pencil opens a small form: one field, one
 * button, and nothing changes until that button is pressed. Escape and a click outside leave
 * the name as it was.
 */
const props = withDefaults(
  defineProps<{
    /** What is being renamed. Only written back when the form is submitted. */
    name?: string
    /** Heading of the form, and the pencil's tooltip. Defaults to the panel's own word. */
    label?: string
    placeholder?: string
    disabled?: boolean
    size?: ActionSize
  }>(),
  { name: '', label: undefined, placeholder: undefined, disabled: false, size: 'sm' },
)

const emit = defineEmits<{ rename: [name: string] }>()

const t = useTranslate('webx-admin')

const open = ref(false)
const draft = ref(props.name)
const field = ref<InstanceType<typeof WxInput> | null>(null)

const title = computed(() => props.label ?? t('editor.rename'))

/*
 * Seeded when the form opens rather than whenever the name changes: a rename arriving from
 * somewhere else while somebody is typing here would otherwise overwrite what they typed.
 */
watch(open, async (isOpen) => {
  if (!isOpen) return

  draft.value = props.name

  // The field does not exist until the panel is on screen, so this waits for it. Selected
  // and not merely focused: renaming is usually replacing, not appending.
  await nextTick()
  field.value?.focus()
  field.value?.select()
})

function submit(): void {
  const next = draft.value.trim()

  // An empty name is not a rename, it is a record with nothing to call it by.
  if (next === '' || next === props.name) {
    open.value = false

    return
  }

  emit('rename', next)
  open.value = false
}
</script>

<template>
  <wx-popover v-model:open="open" :title="title" :width="320" side="bottom" align="start" teleport>
    <template #trigger>
      <wx-action type="edit" :title="title" :size="size" :disabled="disabled" />
    </template>

    <!-- A form, so that Enter does what the button does. -->
    <form class="wx-rename" @submit.prevent="submit">
      <wx-input
        ref="field"
        v-model="draft"
        :aria-label="title"
        :placeholder="placeholder"
        @keydown.esc="open = false"
      />

      <div class="wx-rename__footer">
        <wx-button type="primary" size="sm" native-type="submit">{{ t('editor.save') }}</wx-button>
      </div>
    </form>
  </wx-popover>
</template>

<style scoped>
.wx-rename {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-rename__footer {
  display: flex;
  justify-content: flex-end;
}
</style>
