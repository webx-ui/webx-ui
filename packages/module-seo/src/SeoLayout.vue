<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { WxButton, WxHeading, WxSegmented, WxSpace } from '@webx-ui/core'
import { useSeoMessages } from './i18n'

/**
 * The head of the section: its name, the switch between what it holds, and the one tool that
 * belongs to the whole of it.
 *
 * Rules and redirects are two screens rather than two tabs of one, because they are two tables
 * with their own paging and their own search — and a tab that quietly resets both when you come
 * back to it is worse than a second address.
 */
const props = defineProps<{ base: string; current: 'rules' | 'redirects' }>()

const emit = defineEmits<{ test: [] }>()

const context = useAdmin()
const router = useRouter()
const route = useRoute()

useSeoMessages()

const t = useTranslate('webx-seo')

/** The section's name as the server translated it; the built-in English until it arrives. */
const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'seo')?.title ??
    t('module.title'),
)

const options = computed(() => [
  { value: 'rules', label: t('page.rules') },
  { value: 'redirects', label: t('page.redirects') },
])

const where = computed({
  get: () => props.current,
  set: (next: string | number) => {
    const path = next === 'redirects' ? `${props.base}/redirects` : props.base

    if (path !== route.path) void router.push(path)
  },
})
</script>

<template>
  <div class="wx-seo-layout">
    <div class="wx-seo-layout__head">
      <wx-heading :level="2">{{ title }}</wx-heading>

      <wx-space size="sm">
        <wx-segmented v-model="where" :options="options" size="sm" />
        <wx-button variant="outline" icon="search" @click="emit('test')">
          {{ t('page.test') }}
        </wx-button>
      </wx-space>
    </div>

    <slot />
  </div>
</template>

<style scoped>
.wx-seo-layout {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  container-type: inline-size;
}

.wx-seo-layout__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
  flex-wrap: wrap;
}

/* On a phone the heading takes the first line and the controls the second, full width, rather
   than three things elbowing each other on one. */
@container (max-width: 560px) {
  .wx-seo-layout__head > * {
    flex: 1 1 100%;
  }
}
</style>
