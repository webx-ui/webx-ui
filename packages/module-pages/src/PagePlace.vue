<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { confirm, toast, WxAlert, WxFormItem } from '@webx-ui/core'
import PagePicker from './PagePicker.vue'
import { createPagesApi } from './api'
import { usePageEditor } from './editor'
import { usePagesMessages } from './i18n'

/**
 * Where the page sits: the page it is inside.
 *
 * The address itself is the slug field above (`wx-slug`), which prints the address of the page
 * above in front of what is typed — so the place is said once, where it is edited.
 *
 * Moving is not a draft. The tree is applied at once, because a page that sits in two places
 * until it is published is a tree no screen can draw — so the picker asks the server there and
 * then, and the warning beside it says what that costs.
 */
defineOptions({ name: 'WxPagePlace' })

const context = useAdmin()
const api = createPagesApi(context)
const editor = usePageEditor()
usePagesMessages()

const t = useTranslate('webx-pages')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const moving = ref(false)
const parent = ref<number | null>(null)

const page = computed(() => editor?.page.value ?? null)

watch(
  page,
  (current) => {
    parent.value = current?.parent_id ?? null
  },
  { immediate: true },
)

/**
 * The same question a drop in the list asks, and for the same reason (§14.3): a move rewrites
 * the address of the page and of everything under it, and leaves a redirect on each of the old
 * ones. Picking a parent from a list is deliberate enough that one page moving alone goes
 * through without a dialog; a branch does not.
 */
async function move(target: number | null): Promise<void> {
  const current = page.value

  if (!current || target === null || target === current.parent_id || moving.value) return

  const moves = current.descendants_count + 1

  if (moves > 1) {
    const agreed = await confirm({
      title: t('page.move-title', { title: current.title }),
      message: t('page.move-branch', { count: moves }),
      confirmText: t('page.move-confirm'),
      cancelText: t('page.cancel'),
    })

    if (!agreed) {
      parent.value = current.parent_id

      return
    }
  }

  moving.value = true

  try {
    const result = await api.move(current.id, target, 'inside')

    toast.success(
      result.addresses_changed > 1
        ? t('page.moved', { count: result.addresses_changed })
        : t('page.moved-one'),
    )
    await editor?.reload()
  } catch (error) {
    toast.danger(message(error))
    parent.value = current.parent_id
  } finally {
    moving.value = false
  }
}
</script>

<template>
  <!-- The home page sits nowhere and moves nowhere: nothing to draw. -->
  <div v-if="page?.can.move" class="wx-page-place">
    <wx-form-item :label="t('page.field-parent')" :help="t('page.parent-help')">
      <page-picker
        v-model="parent"
        :exclude="[page.id]"
        :selected="editor?.ancestors.value ?? []"
        @update:model-value="move"
      />
    </wx-form-item>

    <!-- Said before the move rather than after it: the old addresses keep working as
         redirects, which is the part that decides whether this is safe to do on a live
         site, and nobody reads it in a toast that has already gone. -->
    <wx-alert type="info" variant="soft" :description="t('page.parent-warning')" />
  </div>
</template>

<style scoped>
.wx-page-place {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}
</style>
