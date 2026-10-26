<script setup lang="ts">
import { nextTick, onMounted, ref, useTemplateRef } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { useModal, WxButton, WxDialog, WxInput, WxSpace, type LocalizedValue } from '@webx-ui/core'

/**
 * What is on a picture, per language — the file's own alt.
 *
 * Every block and page that uses the picture prints it where the place itself wrote no alt; an
 * image field's own alt still wins there. Left empty, the picture is decorative, which is a
 * choice as well — so an empty answer is a save, not a refusal.
 */
const props = withDefaults(
  defineProps<{
    name: string
    value?: LocalizedValue | null
  }>(),
  { value: null },
)

const { open, resolve, dismiss } = useModal<LocalizedValue>()

const t = useTranslate('webx-media')

const alt = ref<LocalizedValue>({ ...(props.value ?? {}) })
const input = useTemplateRef<HTMLElement>('input')

onMounted(async () => {
  await nextTick()
  input.value?.querySelector('input')?.focus()
})
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('manager.alt-title', { name })" :width="460">
    <div ref="input" class="wx-media-alt">
      <wx-input
        v-model="alt"
        localized
        :placeholder="t('field.alt-hint')"
        :aria-label="t('manager.alt')"
        @keyup.enter="resolve(alt)"
      />
      <p class="wx-media-alt__hint">{{ t('manager.alt-hint') }}</p>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('manager.cancel') }}</wx-button>
        <wx-button type="primary" @click="resolve(alt)">{{ t('manager.save') }}</wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style>
.wx-media-alt {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-media-alt__hint {
  margin: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}
</style>
