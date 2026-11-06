<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  confirm,
  localizedValue,
  toast,
  useLocales,
  WxActionBar,
  WxButton,
  WxSkeleton,
  type LocalizedValue,
} from '@webx-ui/core'
import {
  useAdmin,
  useDefaultLocale,
  useErrorText,
  WxSaveState,
  WxScreen,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
import type { Patch, ScreenModel } from '@webx-ui/schema'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import type { MethodDetail, MethodRow, Option } from './types'

/**
 * One method: the pane beside the list, and the whole screen on a phone. The described screen
 * `delivery.method-form`, short of the fields its type has no use for — the kind of points of a
 * method that asks for no point, the localities of anything but the shop's courier.
 *
 * A new method is a form first and a record on the first save, with its type fixed from then on.
 */
const props = withDefaults(
  defineProps<{
    id: number | null
    /** The type of a new method. */
    type?: string | null
    inline?: boolean
  }>(),
  { type: null, inline: true },
)

const emit = defineEmits<{
  back: []
  saved: [method: MethodRow]
  created: [method: MethodRow]
  removed: [id: number]
}>()

const context = useAdmin()
const api = createDeliveryApi(context)
const locales = useLocales()
const main = useDefaultLocale()
const t = useDeliveryWords()
const message = useErrorText()

const method = ref<MethodRow | null>(null)
const values = ref<ScreenModel>({})
const hidden = ref<string[]>([])
const kinds = ref<Option[]>([])
const typeTitle = ref('')
const snapshot = ref('')
const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => context.can('delivery.manage'))
const dirty = computed(() => snapshot.value !== JSON.stringify(values.value))
const state = computed<'saving' | 'unsaved' | 'saved'>(() =>
  saving.value ? 'saving' : dirty.value || props.id === null ? 'unsaved' : 'saved',
)

const title = computed(
  () =>
    localizedValue(
      values.value.titles as LocalizedValue | null | undefined,
      locales.active.value,
      props.id === null ? t('method.new') : (method.value?.title ?? ''),
    ) || t('common.untitled'),
)

/* The type of the method open: fixed once it is saved. */
const type = computed(() => method.value?.type ?? props.type)

/*
 * The fields of another type are not drawn, and the kinds offered are this type's. Only the
 * shop's pick-up names kinds of its own: a carrier's type says what its points are.
 */
const patch = computed<Patch>(() => [
  ...hidden.value.map((target) => ({ op: 'remove' as const, target })),
  ...(hidden.value.includes('point_kind')
    ? []
    : [
        {
          op: 'set' as const,
          target: 'point_kind',
          props: { options: kinds.value, creatable: type.value === 'store.pickup' },
          // A carrier's kinds are its branches and lockers, not the shop's points.
          ...(type.value === 'store.pickup' ? {} : { help: t('method.point-kind-carrier-help') }),
        },
      ]),
])

function take(detail: MethodDetail): void {
  method.value = detail.method
  values.value = detail.values
  hidden.value = detail.hidden
  kinds.value = detail.point_kinds
  typeTitle.value = detail.method.type_title
  snapshot.value = JSON.stringify(detail.values)
}

async function load(): Promise<void> {
  loading.value = true

  try {
    if (props.id !== null) {
      take(await api.method(props.id))
    } else if (props.type) {
      const blank = await api.blank(props.type)

      hidden.value = blank.hidden
      kinds.value = blank.point_kinds
      typeTitle.value = blank.type_title
      values.value = {
        titles: { [main.value]: blank.type_title },
        descriptions: {},
        price: null,
        point_kind: blank.point_kinds[0]?.value ?? null,
        countries: [],
        honours_paid_by_store: true,
        enabled: true,
        localities: [],
      }
      snapshot.value = ''
    }
  } catch (error) {
    toast.danger(message(error))
    emit('back')
  } finally {
    loading.value = false
  }
}

void load()

async function save(): Promise<void> {
  if (saving.value || !canManage.value) return

  saving.value = true
  errors.value = {}

  try {
    const made = props.id === null
    const detail = made
      ? await api.createMethod(props.type as string, values.value)
      : await api.saveMethod(props.id as number, values.value)

    take(detail)
    toast.success(t('common.saved'))
    if (made) emit('created', detail.method)
    else emit('saved', detail.method)
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
  const record = method.value

  if (!record) return

  const agreed = await confirm({
    title: t('method.delete-title', { name: record.title }),
    message: t('method.delete-text'),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeMethod(record.id)
    toast.success(t('method.deleted'))
    snapshot.value = JSON.stringify(values.value)
    emit('removed', record.id)
  } catch (error) {
    toast.danger(message(error))
  }
}

/* What leads away from the method; on a narrow pane the head folds it into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value && method.value !== null
    ? [
        {
          key: 'delete',
          label: t('common.delete'),
          icon: 'trash',
          danger: true,
          menu: true,
          run: () => void remove(),
        },
      ]
    : [],
)
</script>

<template>
  <div class="wx-delivery-pane">
    <wx-skeleton v-if="loading" title :rows="6" class="wx-delivery-pane__ghost" />

    <template v-else>
      <wx-screen-head
        :level="3"
        help="delivery.method-form"
        :back="!inline"
        :title="title"
        :subtitle="typeTitle"
        :actions="actions"
        :collapse-below="480"
        @back="emit('back')"
      />

      <wx-screen
        v-model="values"
        name="delivery.method-form"
        :patch="patch"
        :errors="errors"
        :disabled="!canManage || saving"
      />

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button
          type="primary"
          :loading="saving"
          :disabled="!dirty && props.id !== null"
          @click="save"
        >
          {{ t('common.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-delivery-pane {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
  padding: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-delivery-pane__ghost {
  max-width: 480px;
}
</style>
