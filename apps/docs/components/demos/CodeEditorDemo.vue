<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { WxCodeEditor, type CodeEditorDiagnostic, type CodeEditorLanguage } from '@webx-ui/core'

const samples: Record<CodeEditorLanguage, string> = {
  json: `{
  "type": "wx-tabs",
  "id": "settings",
  "children": [
    {
      "type": "wx-tab",
      "id": "general",
      "props": { "label": "trans::settings::general" },
      "children": [
        { "type": "wx-input", "id": "project-name", "props": { "label": "Project name" } },
        { "type": "wx-media", "id": "logo", "props": { "label": "Logo", "accept": ["image/*"] } }
      ]
    }
  ],
  "visible": { "when": "is_active", "is": true }
}`,
  javascript: `export function debounce(fn, wait = 200) {
  let timer = null
  return (...args) => {
    clearTimeout(timer)
    timer = setTimeout(() => fn(...args), wait)
  }
}`,
  typescript: `interface ScreenNode {
  type: string
  id?: string
  props?: Record<string, unknown>
  children?: ScreenNode[]
}

export const isLeaf = (node: ScreenNode): boolean => !node.children?.length`,
  html: `<article class="card">
  <h2 class="card__title">Release notes</h2>
  <p>Shipped on <time datetime="2026-09-14">14 September</time>.</p>
</article>`,
  css: `.card {
  display: grid;
  gap: var(--wx-space-8);
  padding: var(--wx-space-16);
  border-radius: var(--wx-radius-md);
}

.card__title:hover {
  color: var(--wx-color-primary);
}`,
  markdown: `# Release notes

The **table** now switches to _cards_ below 480px.

- fixed the seam in Firefox
- \`WxCodeEditor\` for JSON patches

See [the roadmap](/guide/roadmap).`,
  yaml: `name: Lint, typecheck, test, build
on: [push, pull_request]
jobs:
  ci:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: pnpm install --frozen-lockfile`,
  php: `<?php

namespace WebxUi\\Settings;

final class Screen
{
    public function __construct(private readonly array $tree) {}

    public function id(): string
    {
        return $this->tree['id'] ?? 'settings';
    }
}`,
  plain: 'Anything goes here: no highlighting, no linting, just a monospace surface.',
}

const languages = (Object.keys(samples) as CodeEditorLanguage[]).map((value) => ({
  label: value,
  value,
}))

const language = ref<CodeEditorLanguage>('json')
const code = ref(samples.json)
const wrap = ref(false)
const diagnostics = ref<CodeEditorDiagnostic[]>([])
const editor = ref<InstanceType<typeof WxCodeEditor> | null>(null)

watch(language, (next) => {
  code.value = samples[next]
  diagnostics.value = []
})

const status = computed(() => {
  if (language.value !== 'json') return undefined
  return diagnostics.value.length ? 'error' : 'success'
})

const message = computed(() => {
  if (language.value !== 'json') return ''
  const first = diagnostics.value[0]
  return first ? first.message : 'Valid JSON'
})
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div class="wx-demo__row">
      <wx-select
        v-model="language"
        :options="languages"
        size="sm"
        aria-label="Language"
        style="width: 160px"
      />
      <wx-switch v-model="wrap" size="sm">Wrap lines</wx-switch>
      <wx-button v-if="language === 'json'" size="sm" @click="editor?.format()">Format</wx-button>
      <span
        v-if="message"
        :style="{
          marginLeft: 'auto',
          fontSize: '13px',
          color: diagnostics.length ? 'var(--wx-color-danger)' : 'var(--wx-text-muted)',
        }"
      >
        {{ message }}
      </span>
    </div>

    <wx-code-editor
      ref="editor"
      v-model="code"
      :language="language"
      :line-wrapping="wrap"
      :status="status"
      placeholder="Paste something…"
      min-height="240px"
      max-height="420px"
      aria-label="Code"
      @lint="diagnostics = $event"
    />
  </div>
</template>
