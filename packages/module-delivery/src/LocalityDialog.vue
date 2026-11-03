<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  confirm,
  toast,
  WxAlert,
  WxBadge,
  WxButton,
  WxDialog,
  WxSkeleton,
  WxSpace,
} from '@webx-ui/core'
import { useAdmin, useDefaultLocale, useErrorText, WxScreen } from '@webx-ui/module-admin'
import type { Patch, ScreenModel } from '@webx-ui/schema'
import { bodyOf, createDeliveryApi, statusOf } from './api'
import { useDeliveryWords } from './i18n'
import type { DuplicateAnswer, LocalityBrief, LocalityDetail } from './types'

/**
 * One locality of the shared geography, made or changed by hand (decision 42, §4.6): the screen
 * `delivery.locality-form` in a dialog.
 *
 * The same dialog is the «Add a locality» of a point's form and of the courier's list — `short`
 * then leaves only what a town needs to be found (name, type, region; the country is the one
 * given) and the town made is handed back to the field it was asked from.
 *
 * A locality like one that exists is not refused but asked about (409): the dialog lists the
 * similar ones to pick instead, and «Create anyway» makes the new one regardless.
 */
const props = withDefaults(
  defineProps<{
    /** `null` — a new locality. */
    id?: number | null
    /** The country a new locality is in; the short form does not ask. */
    country?: string | null
    /** What was typed in the search that found nothing: the name it starts with. */
    name?: string
    short?: boolean
  }>(),
  { id: null, country: null, name: '', short: false },
)

const open = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{
  /** Made, changed, or picked among the similar ones. */
  saved: [locality: LocalityBrief]
  removed: [id: number]
}>()

const api = createDeliveryApi(useAdmin())
const t = useDeliveryWords()
const message = useErrorText()
const main = useDefaultLocale()

const values = ref<ScreenModel>({})
const detail = ref<LocalityDetail | null>(null)
const errors = ref<Record<string, string[]>>({})
const similar = ref<DuplicateAnswer['similar']>([])
const loading = ref(false)
const saving = ref(false)

const title = computed(() =>
  props.id === null ? t('locality.new') : (detail.value?.locality.label ?? t('locality.title')),
)

/* The short form keeps what finds a town; the country is the field's, not a question. */
const patch = computed<Patch>(() =>
  props.short
    ? [...(props.country ? ['country'] : []), 'coordinates', 'weight', 'active'].map((target) => ({
        op: 'remove' as const,
        target,
      }))
    : props.id === null
      ? []
      : // A locality stays in the country its carriers bound it in.
        [{ op: 'set', target: 'country', props: { disabled: true } }],
)

async function reset(): Promise<void> {
  errors.value = {}
  similar.value = []
  detail.value = null

  if (props.id === null) {
    values.value = {
      names: props.name.trim() === '' ? {} : { [main.value]: props.name.trim() },
      kind: 'town',
      country: props.country,
      region_id: null,
      lat: null,
      lng: null,
      weight: null,
      active: true,
    }

    return
  }

  loading.value = true

  try {
    detail.value = await api.locality(props.id)
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

async function save(force = false): Promise<void> {
  if (saving.value) return

  saving.value = true
  errors.value = {}

  try {
    const answer =
      props.id === null
        ? await api.createLocality(values.value, force)
        : await api.saveLocality(props.id, values.value, force)

    similar.value = []
    toast.success(t(props.id === null ? 'locality.created' : 'locality.saved'))
    emit('saved', answer.locality)
    open.value = false
  } catch (error) {
    if (statusOf(error) === 409) {
      similar.value = bodyOf<DuplicateAnswer>(error)?.similar ?? []

      return
    }

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

/** One of the similar ones instead of a new town: the field gets it, nothing is made. */
function pick(locality: LocalityBrief): void {
  emit('saved', locality)
  open.value = false
}

async function remove(): Promise<void> {
  const record = detail.value?.locality

  if (!record) return

  const held = record.held_by.length > 0
  const agreed = await confirm({
    title: t('locality.delete-title', { name: record.name }),
    message: held
      ? t('locality.held-text', { what: record.held_by.join(', ') })
      : t('locality.delete-text'),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    const { removal } = await api.removeLocality(record.id)

    toast.success(t(removal === 'deleted' ? 'locality.deleted' : 'locality.deactivated'))
    emit('removed', record.id)
    open.value = false
  } catch (error) {
    toast.danger(message(error))
  }
}

const manual = computed(() => detail.value?.locality.manual_fields ?? [])
</script>

<template>
  <wx-dialog v-model:open="open" :title="title" :width="560">
    <div class="wx-delivery-locality">
      <wx-skeleton v-if="loading" :rows="4" />

      <template v-else>
        <wx-alert
          v-if="manual.length > 0"
          type="info"
          :description="t('locality.manual-fields', { fields: manual.join(', ') })"
        />

        <wx-alert
          v-if="similar.length > 0"
          type="warning"
          :title="t('locality.duplicate')"
          data-test="duplicate"
        >
          <p class="wx-delivery-locality__help">{{ t('locality.duplicate-help') }}</p>
          <ul class="wx-delivery-locality__similar">
            <li v-for="item in similar" :key="item.id">
              <span class="wx-delivery-locality__label">{{ item.label }}</span>
              <wx-badge size="sm">{{ t(`locality.sources.${item.source}`) }}</wx-badge>
              <wx-badge v-if="!item.active" size="sm" type="warning">{{
                t('locality.inactive')
              }}</wx-badge>
              <wx-button size="sm" variant="outline" @click="pick(item)">
                {{ t('locality.pick') }}
              </wx-button>
            </li>
          </ul>
        </wx-alert>

        <wx-screen
          v-model="values"
          name="delivery.locality-form"
          :patch="patch"
          :errors="errors"
          :disabled="saving"
        />
      </template>
    </div>

    <template #footer>
      <wx-space size="sm" class="wx-delivery-locality__footer">
        <wx-button
          v-if="id !== null && detail"
          variant="text"
          type="danger"
          icon="trash"
          @click="remove"
        >
          {{ t('common.delete') }}
        </wx-button>
        <span class="wx-delivery-locality__gap" />
        <wx-button variant="outline" @click="open = false">{{ t('common.cancel') }}</wx-button>
        <wx-button
          v-if="similar.length > 0"
          variant="outline"
          :loading="saving"
          data-test="force"
          @click="save(true)"
        >
          {{ t('locality.create-anyway') }}
        </wx-button>
        <wx-button v-else type="primary" :loading="saving" :disabled="loading" @click="save()">
          {{ id === null ? t('common.create') : t('common.save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-delivery-locality {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-delivery-locality__help {
  margin: 0;
}

.wx-delivery-locality__similar {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  margin: var(--wx-space-8) 0 0;
  padding: 0;
  list-style: none;
}

.wx-delivery-locality__similar li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-delivery-locality__label {
  flex: 1 1 160px;
  min-width: 0;
}

.wx-delivery-locality__footer {
  width: 100%;
}

.wx-delivery-locality__gap {
  flex: 1 1 auto;
}
</style>
