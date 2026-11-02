<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  toast,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSelect,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { useAdmin, useDefaultLocale, useErrorText } from '@webx-ui/module-admin'
import { createDeliveryApi } from '../api'
import { useDeliveryWords } from '../i18n'
import type { Option } from '../types'

/**
 * `wx-delivery-point-kind`: the kind of points of a method. A select of the kinds its type allows
 * and, for the shop's pick-up (`creatable`), «New kind of points» right under it (decision 9) —
 * next to the field it fills, not at the foot of the form where nobody connects the two.
 *
 * A kind named here is offered at once and chosen; the server keeps it for every method after.
 */
defineOptions({ name: 'WxDeliveryPointKindField' })

const props = withDefaults(
  defineProps<{
    options?: Option[]
    /** The shop's pick-up: a kind of its own can be named here. */
    creatable?: boolean
    disabled?: boolean
  }>(),
  { options: () => [], creatable: false, disabled: false },
)

const value = defineModel<string | null>({ default: null })

const api = createDeliveryApi(useAdmin())
const t = useDeliveryWords()
const message = useErrorText()
const main = useDefaultLocale()

/* What the server answered after a kind was named: its list, the new one in it. */
const defined = ref<Option[] | null>(null)
const choices = computed(() => defined.value ?? props.options)

const naming = ref(false)
const key = ref('')
const name = ref('')
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)

function startNaming(): void {
  key.value = ''
  name.value = ''
  errors.value = {}
  naming.value = true
}

async function define(): Promise<void> {
  if (saving.value) return

  saving.value = true
  errors.value = {}

  try {
    defined.value = await api.defineKind(key.value.trim(), { [main.value]: name.value.trim() })
    value.value = key.value.trim()
    toast.success(t('method.kind-created'))
    naming.value = false
  } catch (error) {
    errors.value = (error as { errors?: Record<string, string[]> }).errors ?? {}
    if (Object.keys(errors.value).length === 0) toast.danger(message(error))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="wx-delivery-point-kind">
    <wx-select v-model="value" :options="choices" :disabled="props.disabled" />

    <wx-button
      v-if="props.creatable && !props.disabled"
      class="wx-delivery-point-kind__new"
      size="sm"
      variant="text"
      data-test="new-kind"
      @click="startNaming"
    >
      {{ t('method.new-kind') }}
    </wx-button>

    <wx-dialog v-model:open="naming" :title="t('method.new-kind')" :width="440">
      <div class="wx-delivery-point-kind__form">
        <wx-text tone="muted" size="sm">{{ t('method.new-kind-help') }}</wx-text>
        <wx-form-item :label="t('method.kind-name')" :error="errors.names?.[0]">
          <wx-input v-model="name" :maxlength="60" autofocus />
        </wx-form-item>
        <wx-form-item
          :label="t('method.kind-key')"
          :help="t('method.kind-key-help')"
          :error="errors.kind?.[0]"
        >
          <wx-input v-model="key" :maxlength="32" />
        </wx-form-item>
      </div>

      <template #footer>
        <wx-space size="sm">
          <wx-button variant="outline" @click="naming = false">{{ t('common.cancel') }}</wx-button>
          <wx-button
            type="primary"
            :loading="saving"
            :disabled="key.trim() === '' || name.trim() === ''"
            @click="define"
          >
            {{ t('common.add') }}
          </wx-button>
        </wx-space>
      </template>
    </wx-dialog>
  </div>
</template>

<style scoped>
.wx-delivery-point-kind {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-delivery-point-kind > :deep(.wx-select) {
  align-self: stretch;
}

.wx-delivery-point-kind__form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}
</style>
