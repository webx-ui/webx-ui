<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import {
  WxAlert,
  WxBadge,
  WxButton,
  WxDescriptions,
  WxDescriptionsItem,
  WxDialog,
  WxEmpty,
  WxInput,
  WxSpace,
  WxText,
  useModal,
} from '@webx-ui/core'
import { createSeoApi } from './api'
import { useSeoMessages } from './i18n'
import type { SeoTestResult } from './types'

/**
 * "Why does this page have the wrong title?"
 *
 * The most common question this section gets, and the reason it should take one call to answer
 * rather than a reading of the code: which redirect catches the address, which rule matches it,
 * what every source contributed, and what the page ends up saying.
 */
const { open } = useModal<void>()

const context = useAdmin()
const api = createSeoApi(context)
useSeoMessages()

const t = useTranslate('webx-seo')

const url = ref('')
const running = ref(false)
const result = ref<SeoTestResult | null>(null)

const fields = computed(() => {
  const seo = result.value?.seo

  if (!seo) return []

  return [
    { label: t('card.title'), value: seo.title },
    { label: t('card.h1'), value: seo.h1 },
    { label: t('card.description'), value: seo.description },
    { label: t('card.keywords'), value: seo.keywords },
    { label: t('card.canonical'), value: seo.canonical },
    { label: t('card.robots'), value: seo.robots },
  ].filter((one) => one.value)
})

async function run(): Promise<void> {
  if (url.value.trim() === '') return

  running.value = true

  try {
    result.value = await api.test(url.value)
  } finally {
    running.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('page.test')" :width="720">
    <div class="wx-seo-test">
      <wx-space size="sm" class="wx-seo-test__ask">
        <wx-input
          v-model="url"
          :placeholder="t('page.test-placeholder')"
          class="wx-seo-test__input"
          @keyup.enter="run"
        />
        <wx-button type="primary" :loading="running" @click="run">
          {{ t('page.test-run') }}
        </wx-button>
      </wx-space>

      <template v-if="result">
        <!-- First, because it happens first: a redirected address never reaches the rules. -->
        <wx-alert
          v-if="result.redirect"
          type="warning"
          :description="
            t('page.test-redirected', {
              target: result.redirect.target,
              status: result.redirect.status,
            })
          "
        />

        <wx-alert v-if="!result.matched" type="info" :description="t('page.test-none')" />
        <div v-else class="wx-seo-test__matched">
          <wx-text size="sm" tone="muted">{{ t('page.test-matched') }}</wx-text>
          <wx-space size="sm" align="center">
            <wx-badge>{{ t(`page.${result.matched.match_type}`) }}</wx-badge>
            <wx-text mono size="sm">{{ result.matched.pattern }}</wx-text>
          </wx-space>
        </div>

        <div v-if="fields.length > 0" class="wx-seo-test__result">
          <wx-text size="sm" tone="muted">{{ t('page.test-result') }}</wx-text>
          <wx-descriptions :columns="1" bordered size="sm">
            <wx-descriptions-item v-for="one in fields" :key="one.label" :label="one.label">
              {{ one.value }}
            </wx-descriptions-item>
          </wx-descriptions>
        </div>

        <div v-if="result.chain.length > 0" class="wx-seo-test__chain">
          <wx-text size="sm" tone="muted">{{ t('page.test-chain') }}</wx-text>
          <wx-space size="xs" wrap>
            <wx-badge v-for="step in result.chain" :key="step.source">
              {{ step.source }} · {{ step.priority }}
            </wx-badge>
          </wx-space>
        </div>
      </template>

      <wx-empty v-else-if="!running" icon="search" :description="t('page.test-empty')" />
    </div>
  </wx-dialog>
</template>

<style scoped>
.wx-seo-test {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
}

.wx-seo-test__ask {
  width: 100%;
}

.wx-seo-test__input {
  flex: 1 1 auto;
}

.wx-seo-test__matched,
.wx-seo-test__result,
.wx-seo-test__chain {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
}
</style>
