<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  toast,
  WxBadge,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxSelect,
  WxSkeleton,
  WxSpace,
  WxSwitch,
} from '@webx-ui/core'
import { useAdmin, useErrorText, WxDate } from '@webx-ui/module-admin'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import type { CarrierDetail, CarrierField } from './types'

/**
 * One carrier's settings and keys (§13.2), and its runs.
 *
 * A key is never sent to the panel: the dialog says whether each one is set, and an empty field
 * means «leave it» — typing a new value replaces it, «Clear» removes it. «Check the connection»
 * tries the stored keys with whatever is typed over them, without saving.
 */
const props = defineProps<{ carrier: string | null }>()

const open = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{ saved: [] }>()

const context = useAdmin()
const api = createDeliveryApi(context)
const t = useDeliveryWords()
const message = useErrorText()

const detail = ref<CarrierDetail | null>(null)
const settings = ref<Record<string, unknown>>({})
const typed = ref<Record<string, string>>({})
const loading = ref(false)
const saving = ref(false)
const testing = ref(false)

const canManage = computed(() => context.can('delivery.manage'))

function label(field: CarrierField): string {
  if (typeof field.label === 'string') return field.label
  if (field.label && typeof field.label === 'object') {
    const locale = context.i18n.state.locale

    return field.label[locale] ?? Object.values(field.label)[0] ?? field.name
  }

  return field.name
}

async function load(): Promise<void> {
  if (!props.carrier) return

  loading.value = true
  typed.value = {}

  try {
    detail.value = await api.carrier(props.carrier)
    settings.value = { ...detail.value.settings.values }
  } catch (error) {
    toast.danger(message(error))
    open.value = false
  } finally {
    loading.value = false
  }
}

watch(open, (now) => {
  if (now) void load()
})

function credentials(): Record<string, string> {
  return Object.fromEntries(Object.entries(typed.value).filter(([, value]) => value.trim() !== ''))
}

async function save(clear: string[] = []): Promise<void> {
  if (!props.carrier) return

  saving.value = true

  try {
    detail.value = await api.saveCarrier(props.carrier, {
      settings: settings.value,
      credentials: credentials(),
      clear,
    })
    typed.value = {}
    toast.success(t('carrier.saved'))
    emit('saved')
  } catch (error) {
    toast.danger(message(error))
  } finally {
    saving.value = false
  }
}

async function test(): Promise<void> {
  if (!props.carrier) return

  testing.value = true

  try {
    const result = await api.testCarrier(props.carrier, credentials())

    if (result.ok) toast.success(result.message ?? t('carrier.test-ok'))
    else toast.danger(t('carrier.test-failed', { message: result.message ?? '' }))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    testing.value = false
  }
}

const statusType = {
  running: 'primary',
  succeeded: 'success',
  failed: 'danger',
  held: 'warning',
} as const
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="detail?.title ?? t('carrier.choose')"
    :width="640"
    scroll="body"
  >
    <wx-skeleton v-if="loading || !detail" :rows="5" />

    <div v-else class="wx-delivery-carrier-form">
      <section v-if="detail.settings.fields.length > 0">
        <h4 class="wx-delivery-carrier-form__title">{{ t('carrier.settings') }}</h4>
        <wx-form-item
          v-for="field in detail.settings.fields"
          :key="field.name"
          :label="label(field)"
        >
          <wx-switch
            v-if="field.type === 'boolean'"
            v-model="settings[field.name] as boolean"
            :disabled="!canManage"
          />
          <wx-input-number
            v-else-if="field.type === 'number'"
            v-model="settings[field.name] as number"
            :disabled="!canManage"
          />
          <wx-select
            v-else-if="field.type === 'select'"
            v-model="settings[field.name] as string"
            :options="field.options ?? []"
            :disabled="!canManage"
          />
          <wx-input v-else v-model="settings[field.name] as string" :disabled="!canManage" />
        </wx-form-item>
      </section>

      <section v-if="detail.credentials.length > 0" data-test="credentials">
        <h4 class="wx-delivery-carrier-form__title">{{ t('carrier.credentials') }}</h4>
        <wx-form-item v-for="field in detail.credentials" :key="field.name" :label="label(field)">
          <div class="wx-delivery-carrier-form__secret">
            <wx-badge :type="field.set ? 'success' : 'default'" dot>
              {{ field.set ? t('carrier.set') : t('carrier.not-set') }}
            </wx-badge>
            <wx-input
              v-model="typed[field.name]"
              type="password"
              autocomplete="new-password"
              :placeholder="t('carrier.replace')"
              :disabled="!canManage"
              class="wx-delivery-carrier-form__key"
            />
            <wx-button
              v-if="field.set && canManage"
              size="sm"
              variant="text"
              @click="save([field.name])"
            >
              {{ t('carrier.clear') }}
            </wx-button>
          </div>
        </wx-form-item>
      </section>

      <section>
        <h4 class="wx-delivery-carrier-form__title">{{ t('carrier.runs') }}</h4>
        <p v-if="detail.runs.length === 0" class="wx-delivery-carrier-form__muted">
          {{ t('carrier.never') }}
        </p>
        <ul v-else class="wx-delivery-carrier-form__runs">
          <li v-for="run in detail.runs" :key="run.id">
            <wx-badge :type="statusType[run.status]" size="sm">
              {{ t(`carrier.statuses.${run.status}`) }}
            </wx-badge>
            <span>{{ t(`carrier.directories.${run.directory}`) }}</span>
            <wx-date v-if="run.started_at" :value="run.started_at" />
            <span class="wx-delivery-carrier-form__muted">
              {{ t('carrier.seen') }}: {{ run.seen }} · +{{ run.created }} · −{{ run.removed }}
            </span>
            <span v-if="run.error" class="wx-delivery-carrier-form__error">{{ run.error }}</span>
          </li>
        </ul>
      </section>
    </div>

    <template v-if="canManage && detail" #footer>
      <wx-space size="sm">
        <wx-button
          v-if="detail.credentials.length > 0"
          variant="outline"
          :loading="testing"
          @click="test"
        >
          {{ t('carrier.test') }}
        </wx-button>
        <wx-button type="primary" :loading="saving" data-test="carrier-save" @click="save()">
          {{ t('common.save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-delivery-carrier-form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
}

.wx-delivery-carrier-form__title {
  margin: 0 0 var(--wx-space-8);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
}

.wx-delivery-carrier-form__secret {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  width: 100%;
}

.wx-delivery-carrier-form__key {
  flex: 1 1 200px;
  min-width: 0;
}

.wx-delivery-carrier-form__runs {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-delivery-carrier-form__runs li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  font-size: var(--wx-font-size-sm);
}

.wx-delivery-carrier-form__muted {
  color: var(--wx-text-muted);
}

.wx-delivery-carrier-form__error {
  flex-basis: 100%;
  color: var(--wx-color-danger);
}
</style>
