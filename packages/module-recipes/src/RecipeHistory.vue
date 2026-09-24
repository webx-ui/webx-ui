<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxDate } from '@webx-ui/module-admin'
import { confirm, toast, WxBadge, WxButton, WxEmpty, WxSkeleton, WxText } from '@webx-ui/core'
import { createRecipesApi } from './api'
import { useRecipeEditor } from './editor'
import { useRecipesMessages } from './i18n'
import type { RecipeVersion } from './types'

/**
 * What was published, when, by whom and from where — publications only; the autosaves are
 * insurance, not history.
 *
 * Restoring makes the old version the draft. Publishing it is the same separate step it always
 * is, so nothing here changes the site by itself.
 */
defineOptions({ name: 'WxRecipeHistory' })

const context = useAdmin()
const api = createRecipesApi(context)
const editor = useRecipeEditor()
useRecipesMessages()

const t = useTranslate('webx-recipes')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const versions = ref<RecipeVersion[] | null>(null)
const working = ref(false)

const recipe = computed(() => editor?.recipe.value ?? null)
const canManage = computed(() => editor?.canManage ?? false)

/** The newest publication is the site — while the recipe is on it at all. */
const live = computed(() =>
  recipe.value?.status === 'published' || recipe.value?.status === 'modified'
    ? (versions.value?.[0]?.number ?? null)
    : null,
)

async function load(): Promise<void> {
  const current = recipe.value

  if (!current) return

  try {
    versions.value = await api.versions(current.id)
  } catch (error) {
    versions.value = []
    toast.danger(message(error))
  }
}

async function restore(version: RecipeVersion): Promise<void> {
  const current = recipe.value

  if (!current) return

  const agreed = await confirm({
    title: t('recipe.restore-title', { number: version.number }),
    message: t('recipe.restore-text'),
    confirmText: t('recipe.restore-version'),
    cancelText: t('panel.cancel'),
  })

  if (!agreed) return

  working.value = true

  try {
    await api.restoreVersion(current.id, version.number)
    // Through the editor rather than with the answer: the values it holds are what the form is
    // bound to, and a version restored underneath it has to reach the fields.
    await editor?.reload()
    toast.success(t('recipe.restored-version', { number: version.number }))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

// The recipe is read again after every publication and every restore, so its stamp is the
// signal that there is something new to list.
watch(
  () => [recipe.value?.id, recipe.value?.published_at, recipe.value?.updated_at],
  () => void load(),
  { immediate: true },
)
</script>

<template>
  <div class="wx-recipe-history">
    <wx-skeleton v-if="versions === null" class="wx-recipe-history__ghost" :rows="3" />
    <wx-empty v-else-if="versions.length === 0" :description="t('recipe.history-empty')" />
    <div
      v-for="version in versions"
      v-else
      :key="version.number"
      class="wx-recipe-history__row"
      :class="{ 'is-live': version.number === live }"
    >
      <code class="wx-recipe-history__number">{{
        t('recipe.version', { number: version.number })
      }}</code>
      <div class="wx-recipe-history__who">
        <wx-date v-if="version.created_at" :value="version.created_at" size="md" tone="default" />
        <wx-text size="sm" tone="muted">
          {{ version.author ?? t(`recipe.source-${version.source}`) }}
          <template v-if="version.comment"> · {{ version.comment }}</template>
        </wx-text>
      </div>
      <wx-badge v-if="version.number === live" type="success" dot>{{
        t('recipe.version-live')
      }}</wx-badge>
      <wx-button
        v-if="canManage && version.number !== live"
        size="sm"
        variant="outline"
        :loading="working"
        @click="restore(version)"
      >
        {{ t('recipe.restore-version') }}
      </wx-button>
    </div>
  </div>
</template>

<style scoped>
.wx-recipe-history {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  overflow: hidden;
}

.wx-recipe-history__ghost {
  padding: var(--wx-space-10) var(--wx-space-12);
}

.wx-recipe-history__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  padding: var(--wx-space-10) var(--wx-space-12);
  border-block-end: 1px solid var(--wx-border-default);
  flex-wrap: wrap;
}

.wx-recipe-history__row:last-child {
  border-block-end: 0;
}

.wx-recipe-history__number {
  width: 40px;
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-recipe-history__who {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 160px;
}
</style>
