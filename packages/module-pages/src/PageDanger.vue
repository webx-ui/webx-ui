<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { confirm, toast, WxButton, WxCard, WxText } from '@webx-ui/core'
import { createPagesApi } from './api'
import { usePageEditor } from './editor'
import { usePagesMessages } from './i18n'

/**
 * The two ways a page leaves the site.
 *
 * At the bottom of the settings tab rather than in the head, because both are the opposite of
 * what the head is for: publishing is the thing being aimed at all day, and a button that
 * takes the page down should not be next to it by the width of a finger.
 */
defineOptions({ name: 'WxPageDanger' })

const context = useAdmin()
const api = createPagesApi(context)
const router = useRouter()
const editor = usePageEditor()
usePagesMessages()

const t = useTranslate('webx-pages')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const working = ref(false)

const page = computed(() => editor?.page.value ?? null)
const canManage = computed(() => context.can('pages.manage'))

async function unpublish(): Promise<void> {
  const current = page.value

  if (!current) return

  const agreed = await confirm({
    title: t('page.unpublish-title', { title: current.title }),
    message: t('page.unpublish-text'),
    confirmText: t('page.unpublish'),
    cancelText: t('page.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  working.value = true

  try {
    await api.unpublish(current.id)
    await editor?.reload()
    toast.success(t('page.unpublished'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

async function remove(): Promise<void> {
  const current = page.value

  if (!current) return

  const agreed = await confirm({
    title: t('page.delete-title', { title: current.title }),
    // The whole branch, not the level under it (§7): a catalogue with two sections and forty
    // products is forty-two pages leaving the site at once.
    message:
      current.descendants_count > 0
        ? `${t('page.delete-text')} ${t('page.delete-branch', { count: current.descendants_count })}`
        : t('page.delete-text'),
    confirmText: t('page.delete'),
    cancelText: t('page.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  working.value = true

  try {
    await api.remove(current.id)
    toast.success(t('page.deleted'))
    void router.push(editor?.base ?? '/pages')
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}
</script>

<template>
  <wx-card v-if="page && canManage" :title="t('page.danger')">
    <div class="wx-page-danger">
      <div v-if="page.status !== 'draft'" class="wx-page-danger__row">
        <div class="wx-page-danger__what">
          <wx-text weight="medium">{{ t('page.unpublish') }}</wx-text>
          <wx-text size="sm" tone="muted">{{ t('page.unpublish-help') }}</wx-text>
        </div>
        <wx-button variant="outline" :loading="working" @click="unpublish">
          {{ t('page.unpublish') }}
        </wx-button>
      </div>

      <div class="wx-page-danger__row">
        <div class="wx-page-danger__what">
          <wx-text weight="medium">{{ t('page.delete') }}</wx-text>
          <wx-text size="sm" tone="muted">{{
            page.can.delete ? t('page.delete-help') : t('page.delete-home')
          }}</wx-text>
        </div>
        <wx-button
          type="danger"
          variant="outline"
          :disabled="!page.can.delete"
          :loading="working"
          @click="remove"
        >
          {{ t('page.delete') }}
        </wx-button>
      </div>
    </div>
  </wx-card>
</template>

<style scoped>
.wx-page-danger {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-page-danger__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
  flex-wrap: wrap;
}

.wx-page-danger__what {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  min-width: 200px;
  flex: 1;
}
</style>
