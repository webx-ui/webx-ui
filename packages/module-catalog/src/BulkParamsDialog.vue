<script setup lang="ts">
import { computed, onMounted, reactive } from 'vue'
import { useAdmin, useTranslate, WxSourceSelect } from '@webx-ui/module-admin'
import {
  useModal,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSelect,
  WxSpace,
  WxText,
  WxTreeSelect,
  type SelectOption,
  type TreeSelectValue,
} from '@webx-ui/core'
import { useCatalogMessages } from './i18n'
import { useCategoryTree } from './store'
import type { BulkActionInfo, BulkParam } from './types'

/**
 * What a bulk action asks for before it runs — a category, a label — and the last word before it
 * does: how many products it is about to touch. The fields are drawn from what the server said
 * about the action, so a satellite's action gets its dialog without this file knowing it: a
 * param with a `source` is a choice out of that reference book (a label, a brand), and one that is
 * not required says what choosing nothing does — for `set-brand`, take the brand off.
 */
const props = defineProps<{ action: BulkActionInfo; count: number }>()

const { open, resolve, dismiss } = useModal<Record<string, unknown>>()

const context = useAdmin()
const tree = useCategoryTree(context)
useCatalogMessages()

const t = useTranslate('webx-catalog')

const values = reactive<Record<string, unknown>>({})

const ready = computed(() =>
  props.action.params.every(
    (param) =>
      !param.rules?.includes('required') ||
      (values[param.name] !== undefined &&
        values[param.name] !== null &&
        values[param.name] !== ''),
  ),
)

onMounted(() => {
  if (props.action.params.some((param) => param.type === 'category')) {
    void tree.load().catch(() => undefined)
  }
})

function optionsOf(param: BulkParam): SelectOption[] {
  return Array.isArray(param.values)
    ? param.values.map((one) =>
        typeof one === 'object'
          ? { value: one.value, label: one.label }
          : { value: one, label: String(one) },
      )
    : []
}

function required(param: BulkParam): boolean {
  return param.rules?.includes('required') ?? false
}

function onCategory(param: BulkParam, value: TreeSelectValue): void {
  values[param.name] = Array.isArray(value) ? (value[0] ?? null) : value
}

function apply(): void {
  if (ready.value) resolve({ ...values })
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="t('panel.bulk-confirm-title', { action: action.label })"
    :width="440"
  >
    <wx-text tone="muted" size="sm">{{ t('panel.bulk-confirm-text', { count }) }}</wx-text>

    <wx-form-item
      v-for="param in action.params"
      :key="param.name"
      :label="param.label"
      :required="required(param)"
      class="wx-catalog-bulk-params__field"
    >
      <wx-tree-select
        v-if="param.type === 'category'"
        :model-value="(values[param.name] as number | null | undefined) ?? null"
        :nodes="tree.nodes.value ?? []"
        node-key="id"
        label-key="name"
        children-key="children"
        check-strictly
        filterable
        :filter-placeholder="t('panel.search-categories')"
        :aria-label="param.label"
        @update:model-value="(value: TreeSelectValue) => onCategory(param, value)"
      />
      <wx-source-select
        v-else-if="param.source"
        :model-value="(values[param.name] as number | null | undefined) ?? null"
        :source="param.source"
        filterable
        :clearable="!required(param)"
        :placeholder="required(param) ? undefined : t('panel.bulk-param-empty')"
        :aria-label="param.label"
        @update:model-value="(value: unknown) => (values[param.name] = value)"
      />
      <wx-select
        v-else-if="Array.isArray(param.values)"
        :model-value="(values[param.name] as string | number | undefined) ?? null"
        :options="optionsOf(param)"
        :aria-label="param.label"
        @update:model-value="(value: unknown) => (values[param.name] = value)"
      />
      <wx-input v-else v-model="values[param.name] as string" :aria-label="param.label" />
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button
          :type="action.permission === 'catalog.delete' && !action.trashed ? 'danger' : 'primary'"
          :disabled="!ready"
          @click="apply"
        >
          {{ t('panel.bulk-apply') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-catalog-bulk-params__field {
  margin-top: var(--wx-space-16);
}
</style>
