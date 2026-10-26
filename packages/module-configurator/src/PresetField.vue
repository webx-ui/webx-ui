<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { WxButton, WxInputNumber, WxSelect, WxText, type SelectOption } from '@webx-ui/core'
import { createRelationsApi, useAdmin, useTranslate } from '@webx-ui/module-admin'
import type { RenderContext, ScreenNode } from '@webx-ui/schema'
import { useConfiguratorsMessages } from './i18n'
import type { ConfiguratorCandidate, ConfiguratorChoice, ConfiguratorPreset } from './types'

/**
 * `wx-configurator-preset`: where a block's configurator starts (§12.1, decision 10) — a value for
 * any step and a number for any size, picked from the configurator chosen beside it, never typed as
 * JSON. What is left on «as the configurator starts» is not kept: the block follows the schema's
 * `initial` there.
 *
 * Nested, so it reads its neighbour: the field `configurator` of the same block (`props.from` names
 * another). The steps come with the configurator's row of the relation target, in the panel's
 * language — no permission of the section is needed to put one on a page.
 *
 * A key the configurator no longer has stays in the value, unseen: the site drops it when it prints
 * the block, and a step renamed back finds its preset again.
 */
defineOptions({ name: 'WxConfiguratorPresetField' })

const props = withDefaults(
  defineProps<{
    node?: ScreenNode
    context?: RenderContext
    disabled?: boolean
    /** The field of the same block that holds the configurator's key. */
    from?: string
  }>(),
  { node: undefined, context: undefined, disabled: false, from: 'configurator' },
)

const value = defineModel<ConfiguratorPreset | null>({ default: null })

useConfiguratorsMessages()

const admin = useAdmin()
const t = useTranslate('webx-configurator')
const api = createRelationsApi(admin, 'configurator')

const key = computed(() => {
  const chosen = props.context?.model[props.from]

  return typeof chosen === 'string' ? chosen.trim() : ''
})

const row = ref<ConfiguratorCandidate | null>(null)
const loading = ref(false)

watch(
  key,
  async (wanted) => {
    row.value = null
    if (wanted === '') return

    loading.value = true

    try {
      const rows = (await api.search(wanted)) as ConfiguratorCandidate[]
      if (key.value === wanted) row.value = rows.find((one) => one.key === wanted) ?? null
    } catch {
      // Not an answer: the field says the configurator is not on the site until the next try.
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)

const choices = computed<ConfiguratorChoice[]>(() => row.value?.choices ?? [])

const preset = computed<ConfiguratorPreset>(() =>
  value.value !== null && typeof value.value === 'object' ? value.value : {},
)

/** The option of «as the configurator starts», with what that is in words. */
function options(choice: ConfiguratorChoice & { kind: 'choice' }): SelectOption[] {
  const start = choice.options.find((one) => one.value === choice.initial) ?? choice.options[0]
  const own = start
    ? `${t('configurator.preset-default')} · ${start.label}`
    : t('configurator.preset-default')

  return [
    { value: '', label: own },
    ...choice.options.map((one) => ({ value: one.value, label: one.label })),
  ]
}

function put(name: string, next: string | number | null | undefined): void {
  const out: ConfiguratorPreset = { ...preset.value }

  if (next === null || next === undefined || next === '') delete out[name]
  else out[name] = next

  value.value = Object.keys(out).length === 0 ? null : out
}

function reset(): void {
  const known = new Set(choices.value.map((one) => one.key))
  const out = Object.fromEntries(Object.entries(preset.value).filter(([name]) => !known.has(name)))

  value.value = Object.keys(out).length === 0 ? null : out
}

const touched = computed(() => choices.value.some((one) => one.key in preset.value))
</script>

<template>
  <div class="wx-configurator-preset">
    <wx-text v-if="key === ''" size="sm" tone="muted">
      {{ t('configurator.preset-empty') }}
    </wx-text>
    <wx-text v-else-if="!loading && choices.length === 0" size="sm" tone="muted">
      {{ t('configurator.preset-missing') }}
    </wx-text>

    <template v-else>
      <label v-for="choice in choices" :key="choice.key" class="wx-configurator-preset__row">
        <span class="wx-configurator-preset__label">
          {{
            choice.kind === 'size' && choice.unit ? `${choice.label}, ${choice.unit}` : choice.label
          }}
        </span>
        <wx-select
          v-if="choice.kind === 'choice'"
          :model-value="
            typeof preset[choice.key] === 'string' ? (preset[choice.key] as string) : ''
          "
          :options="options(choice)"
          size="sm"
          :disabled="props.disabled"
          :aria-label="choice.label"
          @update:model-value="
            (next: unknown) => put(choice.key, typeof next === 'string' ? next : null)
          "
        />
        <wx-input-number
          v-else
          :model-value="
            typeof preset[choice.key] === 'number' ? (preset[choice.key] as number) : null
          "
          :min="choice.min"
          :max="choice.max"
          :step="choice.step"
          :placeholder="
            choice.initial !== null ? String(choice.initial) : t('configurator.preset-default')
          "
          size="sm"
          :disabled="props.disabled"
          :aria-label="choice.label"
          @update:model-value="(next: number | null | undefined) => put(choice.key, next)"
        />
      </label>

      <div v-if="touched && !props.disabled">
        <wx-button size="sm" variant="text" @click="reset">
          {{ t('configurator.preset-reset') }}
        </wx-button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.wx-configurator-preset {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-configurator-preset__row {
  display: grid;
  grid-template-columns: minmax(0, 12rem) minmax(0, 1fr);
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-configurator-preset__label {
  min-width: 0;
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
  overflow-wrap: anywhere;
}

@container (max-width: 28rem) {
  .wx-configurator-preset__row {
    grid-template-columns: minmax(0, 1fr);
    gap: var(--wx-space-4);
  }
}
</style>
