<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxRadio,
  WxRadioGroup,
  WxSelect,
  WxSpace,
} from '@webx-ui/core'
import { createBlocksApi } from './api'
import { useBlocksMessages } from './i18n'
import { groupLabel } from './schema'
import type { BlockKind, BlockType } from './types'

/**
 * A new type needs a few things to exist — what kind it is, a name, an identifier and, for a
 * block, a group — and gets an empty draft to open. Everything else is the editor's job.
 *
 * The kind is asked first and in words, one line under each: "component" means nothing to a
 * person who has not read the guide, and the line is the guide in one sentence.
 */
const { resolve, dismiss, open } = useModal<BlockType>()

const context = useAdmin()
const api = createBlocksApi(context)
useBlocksMessages()

const t = useTranslate('webx-blocks')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const groups = computed<string[]>(() => {
  const meta = context.state.manifest?.modules.find((module) => module.id === 'blocks')?.meta

  return (meta?.groups as string[] | undefined) ?? ['content']
})

const form = ref({
  kind: 'block' as BlockKind,
  title: '',
  slug: '',
  group: groups.value[0] ?? 'content',
})
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)
const slugTouched = ref(false)

const groupOptions = computed(() =>
  groups.value.map((id) => ({ value: id, label: groupLabel(id, t) })),
)

/** A slug from the name, until the person types one of their own. Latin only: the identifier is code. */
function slugify(text: string): string {
  return text
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/^[^a-z]+/, '')
    .slice(0, 64)
}

watch(
  () => form.value.title,
  (title) => {
    if (!slugTouched.value) form.value.slug = slugify(title)
  },
)

function errorOf(field: string): string | undefined {
  return errors.value[field]?.[0]
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    // A component is never offered in "Add a block", so a group would be a choice about nothing.
    const { group, ...rest } = form.value

    resolve(await api.create(form.value.kind === 'component' ? rest : { ...rest, group }))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]>; message?: string } }).body

    if (body?.errors) {
      errors.value = body.errors
    } else {
      toast.danger(message(error, t('page.failed')))
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('page.new')" :width="520">
    <div class="wx-block-create">
      <wx-form-item :label="t('components.kind')" :error="errorOf('kind')">
        <wx-radio-group v-model="form.kind" class="wx-block-create__kinds">
          <wx-radio value="block">
            <span class="wx-block-create__kind">{{ t('components.kind-block') }}</span>
            <span class="wx-block-create__kind-help">{{ t('components.kind-block-help') }}</span>
          </wx-radio>
          <wx-radio value="component">
            <span class="wx-block-create__kind">{{ t('components.kind-component') }}</span>
            <span class="wx-block-create__kind-help">{{
              t('components.kind-component-help')
            }}</span>
          </wx-radio>
        </wx-radio-group>
      </wx-form-item>

      <wx-form-item :label="t('page.title')" :error="errorOf('title')">
        <wx-input v-model="form.title" autofocus />
      </wx-form-item>

      <wx-form-item
        :label="t('page.identifier')"
        :help="t('page.identifier-help')"
        :error="errorOf('slug')"
      >
        <wx-input v-model="form.slug" placeholder="hero" @input="slugTouched = true" />
      </wx-form-item>

      <wx-form-item
        v-if="form.kind === 'block'"
        :label="t('page.group')"
        :help="t('page.group-help')"
        :error="errorOf('group')"
      >
        <wx-select v-model="form.group" :options="groupOptions" />
      </wx-form-item>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('page.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" @click="save">{{ t('page.create') }}</wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-block-create {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-block-create__kinds {
  gap: var(--wx-space-10);
}

/* The circle beside the name, not beside the middle of name and sentence. */
.wx-block-create__kinds :deep(.wx-radio) {
  align-items: flex-start;
}

/* The name on its line and the sentence under it, both inside the radio's label. */
.wx-block-create__kind {
  display: block;
  font-weight: var(--wx-font-weight-medium);
}

.wx-block-create__kind-help {
  display: block;
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}
</style>
