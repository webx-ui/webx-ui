<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { confirm, toast, WxButton, WxDialog, WxSkeleton, WxSpace, WxText } from '@webx-ui/core'
import { useAdmin, useErrorText, WxScreen } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import type { PointDetail } from './types'

/**
 * One of the shop's own points (§14.2): the screen `delivery.point-form` in a dialog. Its
 * locality field searches the shared geography and adds a town it does not find (decision 42).
 */
const props = withDefaults(defineProps<{ id?: number | null }>(), { id: null })

const open = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{ saved: []; removed: [] }>()

const context = useAdmin()
const api = createDeliveryApi(context)
const t = useDeliveryWords()
const message = useErrorText()

const detail = ref<PointDetail | null>(null)
const values = ref<ScreenModel>({})
const errors = ref<Record<string, string[]>>({})
const loading = ref(false)
const saving = ref(false)

const canManage = computed(() => context.can('delivery.manage'))
const title = computed(() =>
  props.id === null ? t('point.new') : detail.value?.point.title || t('point.title'),
)

async function reset(): Promise<void> {
  errors.value = {}
  detail.value = null

  if (props.id === null) {
    values.value = {
      kind: 'store',
      titles: {},
      address: {},
      locality_id: null,
      lat: null,
      lng: null,
      phone: null,
      active: true,
      schedule: null,
    }

    return
  }

  loading.value = true

  try {
    detail.value = await api.point(props.id)
    values.value = detail.value.values
  } catch (error) {
    toast.danger(message(error))
    open.value = false
  } finally {
    loading.value = false
  }
}

watch(open, (now) => {
  if (now) void reset()
})

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    if (props.id === null) await api.createPoint(values.value)
    else await api.savePoint(props.id, values.value)

    toast.success(t('common.saved'))
    emit('saved')
    open.value = false
  } catch (error) {
    const fields = (error as { errors?: Record<string, string[]> }).errors

    if (fields && Object.keys(fields).length > 0) {
      errors.value = fields
      toast.danger(t('common.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

async function remove(): Promise<void> {
  if (props.id === null) return

  const agreed = await confirm({
    title: t('point.delete-title', { name: title.value }),
    message: t('point.delete-text'),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removePoint(props.id)
    toast.success(t('point.deleted'))
    emit('removed')
    open.value = false
  } catch (error) {
    toast.danger(message(error))
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="title" :width="640" scroll="body">
    <!-- What a point is, before the fields: the shop's own, never a carrier's branch. -->
    <wx-text class="wx-delivery-point__intro" tone="muted" size="sm">{{
      t('point.intro')
    }}</wx-text>
    <wx-skeleton v-if="loading" :rows="6" />
    <wx-screen
      v-else
      v-model="values"
      name="delivery.point-form"
      :errors="errors"
      :disabled="!canManage || saving"
    />

    <template v-if="canManage" #footer>
      <wx-space size="sm" class="wx-delivery-point__footer">
        <wx-button v-if="id !== null" variant="text" type="danger" icon="trash" @click="remove">
          {{ t('common.delete') }}
        </wx-button>
        <span class="wx-delivery-point__gap" />
        <wx-button variant="outline" @click="open = false">{{ t('common.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" :disabled="loading" @click="save">
          {{ id === null ? t('common.create') : t('common.save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-delivery-point__intro {
  display: block;
  margin-bottom: var(--wx-space-16);
}

.wx-delivery-point__footer {
  width: 100%;
}

.wx-delivery-point__gap {
  flex: 1 1 auto;
}
</style>
