<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxIcon } from '@webx-ui/core'
import type { Lint } from './types'

/**
 * The strip under an editor: what was noticed, and what was not — a green line for every
 * check that passed, so that silence reads as "checked" rather than "not looked at".
 */
const props = defineProps<{
  /** Which editor this strip sits under. */
  file: 'template' | 'styles' | 'script' | 'schema'
  lints: Lint[]
  slug: string
  /** A refusal from publishing, shown first and in red. */
  error?: { message: string; line: number | null; where?: string | null } | null
}>()

const t = useTranslate('webx-blocks')

const mine = computed(() => props.lints.filter((lint) => lint.file === props.file))

const passed = computed(() => {
  const codes = new Set(mine.value.map((lint) => lint.code))
  const lines: string[] = []

  if (props.file === 'template') {
    if (!codes.has('no-marker')) lines.push(t('checks.ok-marker'))
    if (!codes.has('variables-missing')) lines.push(t('checks.ok-variables'))
  }

  if (props.file === 'styles') {
    if (!codes.has('stray-selectors')) lines.push(t('checks.ok-prefix', { slug: props.slug }))
    if (!codes.has('bare-selectors')) lines.push(t('checks.ok-bare'))
    if (!codes.has('media-query')) lines.push(t('checks.ok-container'))
  }

  return lines
})
</script>

<template>
  <div class="wx-block-checks">
    <div v-if="error" class="wx-block-checks__line is-error">
      <wx-icon name="close-circle" />
      <span>
        {{ error.message }}
        <template v-if="error.line !== null">
          · {{ t('page.line', { line: error.line }) }}</template
        >
        <template v-if="error.where"> · {{ error.where }}</template>
      </span>
    </div>
    <div v-for="lint in mine" :key="lint.code" class="wx-block-checks__line is-warning">
      <wx-icon name="warning" />
      <span>
        {{ lint.message }}
        <template v-if="lint.line !== null"> · {{ t('page.line', { line: lint.line }) }}</template>
      </span>
    </div>
    <div v-for="line in passed" :key="line" class="wx-block-checks__line is-ok">
      <wx-icon name="check" />
      <span>{{ line }}</span>
    </div>
  </div>
</template>

<style scoped>
.wx-block-checks {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  padding: var(--wx-space-8) var(--wx-space-12);
  font-size: var(--wx-font-size-sm);
  border-block-start: 1px solid var(--wx-border-muted);
}

.wx-block-checks__line {
  display: flex;
  gap: var(--wx-space-8);
  align-items: flex-start;
}

.wx-block-checks__line .wx-icon {
  flex: none;
  margin-block-start: 2px;
}

.is-ok {
  color: var(--wx-text-muted);
}

.is-ok .wx-icon {
  color: var(--wx-color-success);
}

.is-warning {
  color: var(--wx-color-warning);
}

.is-error {
  color: var(--wx-color-danger);
}
</style>
