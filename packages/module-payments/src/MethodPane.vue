<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  confirm,
  localizedValue,
  toast,
  useLocales,
  WxActionBar,
  WxAlert,
  WxBadge,
  WxButton,
  WxCard,
  WxFormItem,
  WxInput,
  WxSkeleton,
  WxText,
  WxTextarea,
  type LocalizedValue,
} from '@webx-ui/core'
import { useAdmin, useErrorText, WxSaveState, WxScreen, WxScreenHead } from '@webx-ui/module-admin'
import type { Patch, ScreenModel, ScreenNode } from '@webx-ui/schema'
import { createPaymentsApi } from './api'
import { usePaymentsWords } from './i18n'
import type { KeyField, MethodDetail, MethodRow, SettingField, TypeParts } from './types'

/**
 * One method (spec §13.3): the pane beside the list, the whole screen on a phone. The described
 * screen `payments.method-form` — words, the rule of delivery, the limits — with the fields of its
 * type laid over it, then the gateway's keys, «Test the connection» and the addresses to copy into
 * the gateway's account.
 *
 * A key is never sent here: a field shows «set» or nothing, typing replaces it on save, an empty
 * field keeps it, «Remove key» clears it. A key set in the site's environment is not offered at all.
 */
const props = withDefaults(
  defineProps<{
    id: number | null
    /** The type of a new method. */
    type?: string | null
    inline?: boolean
    /** Where the list has it and beside what: changed by a drag, the form follows. */
    standing?: string
  }>(),
  { type: null, inline: true, standing: undefined },
)

const emit = defineEmits<{
  back: []
  saved: [method: MethodRow]
  created: [method: MethodRow]
}>()

const admin = useAdmin()
const api = createPaymentsApi(admin)
const locales = useLocales()
const t = usePaymentsWords()
const message = useErrorText()

const SETTING = 'settings.'

const method = ref<MethodRow | null>(null)
const parts = ref<TypeParts | null>(null)
const warnings = ref<string[]>([])
const values = ref<ScreenModel>({})
const typed = ref<Record<string, string>>({})
const clear = ref<string[]>([])
const replacing = ref<string[]>([])
const typeTitle = ref('')
const snapshot = ref('')
const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const tested = ref<{ ok: boolean; message: string | null } | null>(null)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => admin.can('payments.manage'))
const keysDirty = computed(
  () => clear.value.length > 0 || Object.values(typed.value).some((value) => value.trim() !== ''),
)
const dirty = computed(() => keysDirty.value || snapshot.value !== JSON.stringify(values.value))
const state = computed<'saving' | 'unsaved' | 'saved'>(() =>
  saving.value ? 'saving' : dirty.value || props.id === null ? 'unsaved' : 'saved',
)

const title = computed(
  () =>
    localizedValue(
      values.value.titles as LocalizedValue | null | undefined,
      locales.active.value,
      props.id === null ? t('method.new') : (method.value?.title ?? ''),
    ) || typeTitle.value,
)

/** Whether the switch the field names is on: the hold, or another field of the type. */
function isOff(field: SettingField): boolean {
  return Boolean(field.off_with && values.value[`${SETTING}${field.off_with}`])
}

function offHelp(field: SettingField): string {
  const name =
    field.off_with === 'hold'
      ? t('method.hold')
      : (parts.value?.settings.fields.find((one) => one.name === field.off_with)?.label ?? '')

  return field.off_help ?? t('method.off-with', { name })
}

/** A field of the type as a node of the screen: its value lives under `settings.<name>`. */
function node(field: SettingField): ScreenNode {
  const off = isOff(field)
  const help = off ? offHelp(field) : field.help
  const state = off ? { disabled: true } : {}
  const base = {
    id: `setting-${field.name}`,
    name: `${SETTING}${field.name}`,
    label: field.label,
    ...(help ? { help } : {}),
    ...(field.localized ? { localized: true } : {}),
  }

  switch (field.type) {
    case 'textarea':
      return { ...base, type: 'wx-textarea', props: { rows: 3, maxlength: 2000, ...state } }
    case 'select':
      return { ...base, type: 'wx-select', props: { options: field.options, ...state } }
    case 'number':
      return { ...base, type: 'wx-input-number', props: { ...state } }
    case 'switch':
      return { ...base, type: 'wx-switch', props: { ...state } }
    default:
      return { ...base, type: 'wx-input', props: { maxlength: 2000, ...state } }
  }
}

const patch = computed<Patch>(() => {
  const fields = parts.value?.settings.fields ?? []
  const hold = parts.value?.hold
  const groups = parts.value?.groups ?? []
  // The choice depends on the type: «Default (…)» names the group of this one.
  const group: Patch =
    groups.length > 0
      ? [{ op: 'set', target: 'group_key', props: { options: groups } }]
      : [{ op: 'set', target: 'group_key', visible: false }]

  const children: ScreenNode[] = [
    ...fields.map(node),
    ...(hold
      ? [
          {
            id: 'setting-hold',
            type: 'wx-switch',
            name: `${SETTING}hold`,
            label: t('method.hold'),
            help: [
              t('method.hold-help', { days: hold.days }),
              hold.partial ? t('method.hold-partial') : '',
            ]
              .filter(Boolean)
              .join(' '),
          } satisfies ScreenNode,
        ]
      : []),
  ]

  // The card is in the screen, hidden: only a type with fields of its own shows it.
  return children.length === 0
    ? group
    : [
        ...group,
        { op: 'set' as const, target: 'settings', visible: true },
        ...children.map((child) => ({ op: 'add' as const, target: 'settings', node: child })),
      ]
})

// A field taken away is emptied too: left on, it would come back as the server's refusal, on a
// field nobody can touch. Compared as a string: only a change of what is off gets here.
watch(
  () =>
    (parts.value?.settings.fields ?? [])
      .filter(isOff)
      .map((field) => field.name)
      .join(),
  () => {
    const emptied: ScreenModel = {}

    for (const field of parts.value?.settings.fields ?? []) {
      const key = `${SETTING}${field.name}`

      if (isOff(field) && values.value[key]) emptied[key] = field.type === 'switch' ? false : null
    }

    if (Object.keys(emptied).length > 0) values.value = { ...values.value, ...emptied }
  },
)

function take(detail: TypeParts, own: ScreenModel): void {
  parts.value = detail
  const settings: ScreenModel = {}

  for (const field of detail.settings.fields) {
    settings[`${SETTING}${field.name}`] =
      (detail.settings.values[field.name] as ScreenModel[string]) ??
      (field.default as ScreenModel[string]) ??
      null
  }

  if (detail.hold) settings[`${SETTING}hold`] = detail.hold.value

  values.value = { ...own, ...settings }
  snapshot.value = props.id === null ? '' : JSON.stringify(values.value)
  typed.value = {}
  clear.value = []
  replacing.value = []
}

function takeDetail(detail: MethodDetail): void {
  method.value = detail.method
  warnings.value = detail.warnings
  typeTitle.value = detail.method.type_title
  take(detail, detail.values)
}

async function load(): Promise<void> {
  loading.value = true

  try {
    if (props.id !== null) {
      takeDetail(await api.method(props.id))
    } else if (props.type) {
      const blank = await api.blank(props.type)

      typeTitle.value = blank.type_title
      take(blank, {
        titles: {},
        descriptions: {},
        enabled: false,
        group_key: '',
        delivery_mode: 'any',
        delivery_methods: [],
        delivery_keys: '',
        min_amount: null,
        max_amount: null,
      })
    }
  } catch (error) {
    toast.danger(message(error))
    emit('back')
  } finally {
    loading.value = false
  }
}

void load()

/*
 * Dragged to another group while open, or a method like it dragged away: the field follows, or the
 * next save would drag it back, and so does the warning. Only those: whatever else is being typed
 * stays, unsaved as it was.
 */
watch(
  () => props.standing,
  async (_standing, before) => {
    if (props.id === null || before === undefined) return

    try {
      const fresh = await api.method(props.id)
      const key = fresh.values.group_key
      const stored = JSON.parse(snapshot.value || '{}') as ScreenModel

      method.value = fresh.method
      warnings.value = fresh.warnings
      values.value = { ...values.value, group_key: key }
      snapshot.value = JSON.stringify({ ...stored, group_key: key })
    } catch (error) {
      toast.danger(message(error))
    }
  },
)

/** The screen's own values and the type's settings go apart: the server checks each its own way. */
function body() {
  const own: ScreenModel = {}
  const settings: Record<string, unknown> = {}

  for (const [key, value] of Object.entries(values.value)) {
    if (key.startsWith(SETTING)) settings[key.slice(SETTING.length)] = value
    else own[key] = value
  }

  const credentials: Record<string, string> = {}

  for (const [name, value] of Object.entries(typed.value)) {
    if (value.trim() !== '') credentials[name] = value
  }

  return {
    values: own,
    settings,
    ...(Object.keys(credentials).length > 0 ? { credentials } : {}),
    ...(clear.value.length > 0 ? { clear: clear.value } : {}),
  }
}

async function save(): Promise<void> {
  if (saving.value || !canManage.value) return

  saving.value = true
  errors.value = {}

  try {
    const made = props.id === null
    const detail = made
      ? await api.createMethod(props.type as string, body())
      : await api.saveMethod(props.id as number, body())

    takeDetail(detail)
    toast.success(t('common.saved'))
    for (const warning of detail.warnings) toast.warning(warning)
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

async function test(): Promise<void> {
  if (props.id === null || testing.value) return

  testing.value = true
  tested.value = null

  try {
    tested.value = await api.testMethod(props.id, typed.value)
  } catch (error) {
    tested.value = { ok: false, message: message(error) }
  } finally {
    testing.value = false
  }
}

function replace(field: KeyField): void {
  replacing.value = [...replacing.value, field.name]
  clear.value = clear.value.filter((name) => name !== field.name)
}

function keep(field: KeyField): void {
  replacing.value = replacing.value.filter((name) => name !== field.name)
  clear.value = clear.value.filter((name) => name !== field.name)
  delete typed.value[field.name]
}

/** A gateway's key gone is a method that is no longer offered: asked first, then gone on save. */
async function remove(field: KeyField): Promise<void> {
  const agreed = await confirm({
    title: t('method.key-remove-title', { name: field.label }),
    message: t('method.key-remove-text'),
    confirmText: t('method.key-remove'),
    cancelText: t('common.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  clear.value = [...clear.value, field.name]
  replacing.value = replacing.value.filter((name) => name !== field.name)
  delete typed.value[field.name]
}

/** An input for a key nobody set, or one being replaced; «set» with its buttons otherwise. */
function editing(field: KeyField): boolean {
  return !field.environment && (!field.set || replacing.value.includes(field.name))
}

async function copy(text: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(text)
    toast.success(t('common.copied'))
  } catch {
    toast.danger(text)
  }
}
</script>

<template>
  <div class="wx-payments-pane">
    <wx-skeleton v-if="loading" title :rows="6" class="wx-payments-pane__ghost" />

    <template v-else>
      <wx-screen-head
        :level="3"
        help="payments.method-form"
        :back="!inline"
        :title="title"
        :subtitle="typeTitle === title ? undefined : typeTitle"
        :collapse-below="480"
        @back="emit('back')"
      />

      <wx-alert
        v-for="warning in method?.warnings ?? []"
        :key="warning"
        type="warning"
        :description="t(`methods.warnings.${warning}`)"
      />

      <wx-alert
        v-for="warning in warnings"
        :key="warning"
        type="warning"
        data-test="method-similar"
        :description="warning"
      />

      <wx-screen
        v-model="values"
        name="payments.method-form"
        :patch="patch"
        :errors="errors"
        :disabled="!canManage || saving"
      />

      <wx-card
        v-if="parts && parts.credentials.length > 0"
        :title="t('method.keys')"
        data-test="method-keys"
      >
        <div class="wx-payments-keys">
          <wx-text size="sm" tone="muted">{{ t('method.keys-help') }}</wx-text>
          <wx-alert
            v-if="parts.credentials_unreadable"
            type="warning"
            :description="t('method.unreadable')"
          />

          <wx-form-item
            v-for="field in parts.credentials"
            :key="field.name"
            :label="field.label"
            :help="field.environment ? t('method.key-environment') : undefined"
            :error="errors[`credentials.${field.name}`]?.[0]"
          >
            <div
              v-if="!editing(field)"
              class="wx-payments-keys__row"
              :data-test="`key-${field.name}`"
            >
              <wx-badge
                :type="
                  field.environment || (field.set && !clear.includes(field.name))
                    ? 'success'
                    : 'default'
                "
                dot
              >
                {{
                  clear.includes(field.name)
                    ? t('method.key-removed')
                    : field.set || field.environment
                      ? t('method.key-set')
                      : t('method.key-missing')
                }}
              </wx-badge>
              <template v-if="canManage && !field.environment">
                <wx-button
                  v-if="!clear.includes(field.name)"
                  size="sm"
                  variant="text"
                  @click="replace(field)"
                >
                  {{ t('method.key-replace') }}
                </wx-button>
                <wx-button
                  v-if="!clear.includes(field.name)"
                  size="sm"
                  variant="text"
                  danger
                  @click="void remove(field)"
                >
                  {{ t('method.key-remove') }}
                </wx-button>
                <wx-button v-else size="sm" variant="text" @click="keep(field)">
                  {{ t('common.cancel') }}
                </wx-button>
              </template>
            </div>

            <div v-else class="wx-payments-keys__edit">
              <wx-textarea
                v-if="field.multiline"
                v-model="typed[field.name]"
                :rows="4"
                :placeholder="t('method.key-placeholder')"
                :disabled="!canManage || saving"
                autocomplete="off"
                spellcheck="false"
              />
              <wx-input
                v-else
                v-model="typed[field.name]"
                type="password"
                :placeholder="t('method.key-placeholder')"
                :disabled="!canManage || saving"
                autocomplete="new-password"
                :data-test="`key-input-${field.name}`"
              />
              <wx-button v-if="field.set" size="sm" variant="text" @click="keep(field)">
                {{ t('common.cancel') }}
              </wx-button>
            </div>
          </wx-form-item>

          <div v-if="parts.testable && props.id !== null" class="wx-payments-keys__test">
            <wx-button
              size="sm"
              variant="outline"
              icon="link"
              :loading="testing"
              :disabled="!canManage"
              data-test="method-test"
              @click="test"
            >
              {{ t('method.test') }}
            </wx-button>
            <wx-badge
              v-if="tested"
              :type="tested.ok ? 'success' : 'danger'"
              data-test="method-test-result"
            >
              {{ tested.ok ? t('method.test-ok') : t('method.test-failed') }}
            </wx-badge>
            <wx-text v-if="tested?.message" size="sm" tone="muted">{{ tested.message }}</wx-text>
          </div>
          <wx-text v-if="parts.testable" size="sm" tone="muted">{{
            t('method.test-help')
          }}</wx-text>
        </div>
      </wx-card>

      <wx-card v-if="parts?.online" :title="t('method.addresses')">
        <div class="wx-payments-keys">
          <wx-text size="sm" tone="muted">{{ t('method.addresses-help') }}</wx-text>
          <wx-form-item :label="t('method.notify-url')">
            <div v-if="parts.notify_url" class="wx-payments-keys__copy">
              <code class="wx-payments-keys__url" data-test="notify-url">{{
                parts.notify_url
              }}</code>
              <wx-button size="sm" variant="text" icon="copy" @click="copy(parts.notify_url)">
                {{ t('common.copy') }}
              </wx-button>
            </div>
            <wx-text v-else size="sm" tone="muted">{{ t('method.notify-after-save') }}</wx-text>
          </wx-form-item>
          <!-- The return address goes with every payment: no gateway takes it in its account. -->
          <wx-text size="sm" tone="muted" data-test="return-note">{{
            t('method.return-note')
          }}</wx-text>
        </div>
      </wx-card>

      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button
          type="primary"
          :loading="saving"
          :disabled="!dirty && props.id !== null"
          data-test="method-save"
          @click="save"
        >
          {{ t('common.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-payments-pane {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
  padding: var(--wx-space-16);
  container-type: inline-size;
}

.wx-payments-pane__ghost {
  max-width: 480px;
}

.wx-payments-keys {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-payments-keys__row,
.wx-payments-keys__test,
.wx-payments-keys__copy {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-payments-keys__edit {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-4);
  width: 100%;
}

.wx-payments-keys__edit > :first-child {
  width: 100%;
}

.wx-payments-keys__url {
  flex: 1 1 200px;
  min-width: 0;
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}
</style>
