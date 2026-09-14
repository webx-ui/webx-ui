<script setup lang="ts">
import { computed, ref, watch, type Component } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxSelect,
  WxSpace,
  WxSwitch,
} from '@webx-ui/core'
import SeoCard from './SeoCard.vue'
import { createSeoApi } from './api'
import { useSeoMessages } from './i18n'
import { ruleInput, seoOf } from './rule'
import type { MatchType, SeoUrlRule, SeoValue } from './types'

/**
 * One rule: which addresses it covers, and what it says about them.
 *
 * A dialog rather than a second route, and a template rather than a described screen — the same
 * decision `admins.form` is on, until the screens mechanism has been round the block a few more
 * times. What it edits below the address is `WxSeo`, the same card a content module will get by
 * patching it into its own form.
 */
const props = withDefaults(defineProps<{ rule?: SeoUrlRule | null; mediaField?: Component }>(), {
  rule: null,
  mediaField: undefined,
})

const { resolve, dismiss, open } = useModal<SeoUrlRule>()

const context = useAdmin()
const api = createSeoApi(context)
useSeoMessages()

const t = useTranslate('webx-seo')

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref({
  match_type: 'exact' as MatchType,
  pattern: '',
  priority: 0,
  is_active: true,
})

const seo = ref<SeoValue>({})

const editing = computed(() => props.rule !== null)

const kindOptions = computed(() => [
  { value: 'exact', label: t('page.exact') },
  { value: 'mask', label: t('page.mask') },
  { value: 'regex', label: t('page.regex') },
])

watch(
  () => props.rule,
  (rule) => {
    form.value = {
      match_type: rule?.match_type ?? 'exact',
      pattern: rule?.pattern ?? '',
      priority: rule?.priority ?? 0,
      is_active: rule?.is_active ?? true,
    }
    seo.value = seoOf(rule)
    errors.value = {}
  },
  { immediate: true },
)

function errorOf(field: string): string | undefined {
  return errors.value[field]?.[0]
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  const input = ruleInput(form.value, seo.value)

  try {
    const saved = props.rule
      ? await api.updateUrl(props.rule.id, input)
      : await api.createUrl(input)

    toast.success(t('page.saved'))
    resolve(saved)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]>; message?: string } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('page.failed'))
    } else {
      toast.danger(body?.message ?? t('page.failed'))
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('page.rule')" :width="860">
    <div class="wx-seo-rule">
      <div class="wx-seo-rule__where">
        <wx-form-item :label="t('page.kind')">
          <wx-select v-model="form.match_type" :options="kindOptions" />
        </wx-form-item>

        <wx-form-item
          :label="t('page.address')"
          :help="t('page.address-help')"
          :error="errorOf('pattern')"
          class="wx-seo-rule__address"
        >
          <wx-input v-model="form.pattern" placeholder="/catalog/shoes" />
        </wx-form-item>

        <wx-form-item :label="t('page.priority')" :help="t('page.priority-help')">
          <wx-input-number v-model="form.priority" :step="10" />
        </wx-form-item>

        <wx-form-item :label="t('page.state')">
          <wx-switch v-model="form.is_active" />
        </wx-form-item>
      </div>

      <seo-card v-model="seo" :media-field="props.mediaField" />
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('page.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" @click="save">
          {{ editing ? t('page.save') : t('page.new-rule') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-seo-rule {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  container-type: inline-size;
}

/* The address and its kind on one line: they are one decision, and reading them apart is
   reading half of it. */
.wx-seo-rule__where {
  display: grid;
  grid-template-columns: 180px 1fr 140px 100px;
  gap: var(--wx-space-12);
  align-items: start;
}

@container (max-width: 720px) {
  .wx-seo-rule__where {
    grid-template-columns: 1fr 1fr;
  }

  .wx-seo-rule__address {
    grid-column: 1 / -1;
  }
}
</style>
