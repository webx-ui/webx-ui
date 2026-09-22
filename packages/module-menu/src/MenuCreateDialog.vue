<script setup lang="ts">
import { nextTick, onMounted, ref, useTemplateRef } from 'vue'
import { useModal, WxButton, WxDialog, WxFormItem, WxInput, WxSpace } from '@webx-ui/core'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { createMenusApi } from './api'
import { useMenuMessages } from './i18n'
import type { MenuRow } from './types'

/**
 * A menu of somebody's own: a key and a name (§9).
 *
 * Two fields and no more. A menu declared in the configuration is not made here and cannot be —
 * it is a template asking for a name — so everything this dialog writes is a menu somebody will
 * read out of a block or a template they are about to write.
 *
 * The key does not follow the name the way a page's address follows its title: this one is typed
 * into `menu('…')` by hand, and a key that quietly became `main-menu-2` would be a template
 * asking for a menu that is not there.
 */
defineOptions({ name: 'WxMenuCreateDialog' })

const props = withDefaults(defineProps<{ menu?: MenuRow | null }>(), { menu: null })

const { open, resolve, dismiss } = useModal<MenuRow>()

const admin = useAdmin()
const api = createMenusApi(admin)
useMenuMessages()

const t = useTranslate('webx-menu')

const editing = props.menu !== null

const key = ref(props.menu?.key ?? '')
const title = ref(props.menu?.title ?? '')
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const field = useTemplateRef<HTMLElement>('field')

onMounted(async () => {
  await nextTick()
  field.value?.querySelector('input')?.focus()
})

async function submit(): Promise<void> {
  if (title.value.trim() === '' || saving.value) return

  saving.value = true
  errors.value = {}

  try {
    const input = { key: key.value.trim(), title: title.value.trim() }

    resolve(
      editing && props.menu !== null
        ? await api.update(props.menu.key, props.menu.can.rename ? input : { title: input.title })
        : await api.create(input),
    )
  } catch (error) {
    // The key is the one thing that can be refused, and it is refused under its own field:
    // taken already, or spelled in a way no template could write.
    errors.value = (error as { body?: { errors?: Record<string, string[]> } }).body?.errors ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="editing ? t('menu.rename-title') : t('menu.new-menu-title')"
    :width="440"
  >
    <wx-form-item :label="t('menu.field-name')" :error="errors.title?.[0]" required>
      <div ref="field">
        <wx-input v-model="title" :aria-label="t('menu.field-name')" @keyup.enter="submit" />
      </div>
    </wx-form-item>

    <!--
      Locked for a menu a template asks for: that spelling is the reference, and renaming it
      here would break a view nobody is looking at.
    -->
    <wx-form-item
      :label="t('menu.field-key')"
      :error="errors.key?.[0]"
      :help="t('menu.key-help')"
      required
    >
      <wx-input
        v-model="key"
        :disabled="editing && !props.menu?.can.rename"
        :aria-label="t('menu.field-key')"
        @keyup.enter="submit"
      />
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('menu.cancel') }}</wx-button>
        <wx-button
          type="primary"
          :loading="saving"
          :disabled="title.trim() === '' || key.trim() === ''"
          @click="submit"
        >
          {{ editing ? t('menu.save') : t('menu.create') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
