<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { toast, WxAlert, WxButton, WxCard, WxCodeEditor, WxSpace, WxText } from '@webx-ui/core'
import { createConfiguratorsApi } from './api'
import { useBuilder } from './builder'
import { useConfiguratorEditor } from './editor'
import type { ConfiguratorSchema } from './engine/types'
import type { SchemaProblem } from './types'

/**
 * The whole document as JSON, for whoever finds it quicker than the constructor (§13.2). «Apply»
 * asks the server's check first — the one a publication is held to — and puts the document into
 * the draft when it is a JSON object, problems or not, as a draft is saved; the problems are
 * listed with their places, and a click takes the constructor there.
 */
defineOptions({ name: 'WxConfiguratorJson' })

const emit = defineEmits<{ reveal: [path: string] }>()

const builder = useBuilder()
const editor = useConfiguratorEditor()
const api = createConfiguratorsApi(useAdmin())
const t = useTranslate('webx-configurator')
const message = useErrorText()

const pretty = (schema: ConfiguratorSchema) => JSON.stringify(schema, null, 2)

const text = ref(pretty(builder.schema.value))
const applying = ref(false)
const problems = ref<SchemaProblem[]>([])
const syntax = ref<string | null>(null)

/** What is typed differs from the draft: until it is applied, the draft is not overwritten. */
const typed = computed(() => text.value !== pretty(builder.schema.value))

/* The draft changed elsewhere — the constructor, a merge: the text follows unless somebody is
   halfway through typing over it. */
watch(
  () => pretty(builder.schema.value),
  (next, before) => {
    if (text.value === before) text.value = next
  },
)

async function apply(): Promise<void> {
  syntax.value = null
  problems.value = []

  let parsed: unknown

  try {
    parsed = JSON.parse(text.value)
  } catch (error) {
    syntax.value = t('configurator.json-not-json', { error: (error as Error).message })

    return
  }

  if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
    syntax.value = t('configurator.json-not-object')

    return
  }

  applying.value = true

  try {
    const checked = await api.validate(parsed, editor.detail.value?.configurator.id)

    problems.value = checked.errors
    editor.values.value.schema = parsed as ConfiguratorSchema
    text.value = pretty(editor.values.value.schema)

    if (checked.valid) toast.success(t('configurator.json-applied'))
    else toast.warning(t('configurator.json-applied-problems', { count: checked.errors.length }))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    applying.value = false
  }
}

function reset(): void {
  text.value = pretty(builder.schema.value)
  syntax.value = null
}
</script>

<template>
  <wx-card class="wx-configurator-json">
    <div class="wx-configurator-json__body">
      <wx-text size="sm" tone="muted">{{ t('configurator.json-help') }}</wx-text>

      <wx-code-editor
        v-model="text"
        language="json"
        lint
        line-wrapping
        :readonly="builder.disabled.value"
        min-height="360px"
        max-height="70vh"
        :aria-label="t('configurator.tab-json')"
      />

      <wx-alert v-if="syntax" type="danger">{{ syntax }}</wx-alert>

      <wx-alert
        v-if="problems.length > 0"
        type="warning"
        :title="t('configurator.problems', { count: problems.length })"
      >
        <ul class="wx-configurator-json__problems">
          <li v-for="(problem, at) in problems" :key="at">
            <button type="button" @click="emit('reveal', problem.path)">
              <code>{{ problem.path || '—' }}</code>
              <span>{{ problem.message }}</span>
            </button>
          </li>
        </ul>
      </wx-alert>

      <wx-space v-if="!builder.disabled.value" size="sm">
        <wx-button type="primary" :loading="applying" :disabled="!typed" @click="apply">
          {{ t('configurator.json-apply') }}
        </wx-button>
        <wx-button variant="outline" :disabled="!typed" @click="reset">
          {{ t('configurator.json-reset') }}
        </wx-button>
      </wx-space>
    </div>
  </wx-card>
</template>

<style scoped>
.wx-configurator-json__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-configurator-json__problems {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-configurator-json__problems button {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  font-size: var(--wx-font-size-sm);
  text-align: start;
  cursor: pointer;
}

.wx-configurator-json__problems code {
  font-family: var(--wx-font-family-mono);
  color: var(--wx-text-muted);
  overflow-wrap: anywhere;
}
</style>
