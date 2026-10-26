<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxFormItem, WxInput, WxInputNumber, type LocalizedValue } from '@webx-ui/core'
import ConditionsEditor from './ConditionsEditor.vue'
import { conditionErrors, errorsAt, useBuilder } from './builder'
import { localized, renameKey, setText, stepsAbove, takenKeys } from './document'
import type { Condition, FieldDoc } from './engine/types'

/**
 * One size field of a sizes step (§5.4): its key — the name a formula reads it by — words, unit,
 * range, stepper step and starting number, and when it is asked at all («depth» only for the
 * corner enclosures). A key renamed takes the formulas, `defaults` and `limits` that name it along.
 */
defineOptions({ name: 'WxConfiguratorSizeField' })

const props = defineProps<{ step: number; index: number }>()

const builder = useBuilder()
const t = useTranslate('webx-configurator')

const field = computed(() => builder.schema.value.steps[props.step]!.fields![props.index]!)
const path = computed(() => `steps.${props.step}.fields.${props.index}`)
const locale = builder.locale
const disabled = builder.disabled

const errors = (suffix: string) =>
  errorsAt(builder, suffix === '' ? path.value : `${path.value}.${suffix}`)

const key = ref(field.value.key)
watch(
  () => field.value.key,
  (next) => (key.value = next),
)

function commitKey(): void {
  const next = key.value.trim()
  const before = field.value.key

  if (next === before) return

  if (next !== '' && !takenKeys(builder.schema.value).includes(next)) {
    renameKey(builder.schema.value, before, next)
  }

  field.value.key = next
}

function text(name: 'label' | 'unit', next: LocalizedValue | string | number | undefined): void {
  setText(field.value, name, typeof next === 'number' ? String(next) : (next ?? null))
}

function setNumber(
  name: 'min' | 'max' | 'step' | 'default',
  next: number | null | undefined,
): void {
  const target = field.value as unknown as Record<string, unknown>

  if (typeof next !== 'number' || !Number.isFinite(next)) {
    if (name === 'step' || name === 'default') delete target[name]
    else target[name] = 0
  } else {
    target[name] = next
  }
}

const above = computed(() => stepsAbove(builder.schema.value, props.step))

function setCondition(which: 'when' | 'unless', next: Condition | undefined): void {
  const target: FieldDoc = field.value

  if (next === undefined) delete target[which]
  else target[which] = next
}
</script>

<template>
  <div class="wx-configurator-field" :data-path="path">
    <div class="wx-configurator-field__grid">
      <wx-form-item :label="t('configurator.field-label')" :error="errors('label')" required>
        <wx-input
          :model-value="localized(field.label, locale)"
          localized
          size="sm"
          :disabled="disabled"
          @update:model-value="(next) => text('label', next)"
        />
      </wx-form-item>
      <wx-form-item
        :label="t('configurator.step-key')"
        :help="t('configurator.step-key-help')"
        :error="errors('key')"
        required
      >
        <wx-input
          v-model="key"
          size="sm"
          :disabled="disabled"
          @change="commitKey"
          @blur="commitKey"
        />
      </wx-form-item>
      <wx-form-item :label="t('configurator.field-unit')" :error="errors('unit')">
        <wx-input
          :model-value="localized(field.unit, locale)"
          localized
          size="sm"
          :disabled="disabled"
          @update:model-value="(next) => text('unit', next)"
        />
      </wx-form-item>
    </div>

    <wx-form-item :error="[...errors(''), ...errors('step'), ...errors('default')]">
      <div class="wx-configurator-field__numbers">
        <label v-for="name in ['min', 'max', 'step', 'default'] as const" :key="name">
          <span>{{ t(`configurator.field-${name}`) }}</span>
          <wx-input-number
            :model-value="typeof field[name] === 'number' ? (field[name] as number) : null"
            size="sm"
            :controls="false"
            :disabled="disabled"
            @update:model-value="(next: number | null | undefined) => setNumber(name, next)"
          />
        </label>
      </div>
    </wx-form-item>

    <conditions-editor
      :model-value="field.when"
      :label="t('configurator.when')"
      :steps="above"
      :locale="locale"
      :disabled="disabled"
      :errors="conditionErrors(builder, `${path}.when`)"
      @update:model-value="(next) => setCondition('when', next)"
    />
    <conditions-editor
      v-if="above.length > 0"
      :model-value="field.unless"
      :label="t('configurator.unless')"
      :steps="above"
      :locale="locale"
      :disabled="disabled"
      :errors="conditionErrors(builder, `${path}.unless`)"
      @update:model-value="(next) => setCondition('unless', next)"
    />
  </div>
</template>

<style scoped>
.wx-configurator-field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-configurator-field__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr));
  gap: var(--wx-space-12);
}

.wx-configurator-field__numbers {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 100px), 1fr));
  gap: var(--wx-space-8);
}

.wx-configurator-field__numbers label {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}
</style>
