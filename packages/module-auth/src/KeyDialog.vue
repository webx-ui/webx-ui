<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxIcon,
  WxInput,
  WxRadioGroup,
  WxSelect,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { createAdminsApi } from './admins'
import { copyText } from './clipboard'
import { createKeysApi } from './keys'
import { useAuthMessages } from './i18n'
import type { McpKey } from './types'

/**
 * Issuing a key for a program, and then showing it — once.
 *
 * Two steps in one dialog rather than a form and a toast, because the second step is the one
 * that matters: the secret exists on this screen and nowhere else, and a toast that faded
 * before somebody copied it would mean issuing another. The dialog resolves with the key when
 * it is closed after issuing, so the list behind it can show the new row.
 *
 * The scopes on offer are the owner's: a key acts as the administrator it names and can never
 * do more than they may, so a module none of whose tools they may use is not offered — the
 * server would refuse it anyway (WEBX_UI_PERMISSIONS.md §7). Asked again whenever the owner
 * changes.
 *
 * The lifetime is a choice of a few, not a date picker. A key is for something that runs
 * unattended, and the honest question is "how long until somebody has to come back to it" —
 * a month, a quarter, a year, three, ten. There is no key without an end: a signature that
 * never expires is one nobody can take back by waiting.
 */
const props = withDefaults(
  defineProps<{
    /**
     * A key for whoever is signed in, from the page that connects their agent: no owner to
     * choose, and no list of people to fetch — which somebody without `admins.view` could not.
     */
    mine?: boolean
  }>(),
  { mine: false },
)

const { open, resolve, dismiss } = useModal<McpKey>()

const context = useAdmin()
const keys = createKeysApi(context)
const admins = createAdminsApi(context)
useAuthMessages()

const t = useTranslate('webx-auth')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const LIFETIMES = [30, 90, 365, 1095, 3650] as const

const form = ref({
  name: '',
  admin: (props.mine ? Number(context.state.user?.id) || null : null) as number | null,
  access: 'everything' as 'everything' | 'chosen',
  scopes: [] as string[],
  days: 365 as number,
})

const scopes = ref<string[]>([])
const people = ref<{ value: number; label: string }[]>([])
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

/** Set once the key is issued: the dialog turns into the one place its secret is shown. */
const issued = ref<{ key: McpKey; token: string } | null>(null)
const copied = ref(false)

/** `mcp:use` is offered as the first choice, in words; the list below it is the modules'. */
const scopeOptions = computed(() =>
  scopes.value
    .filter((scope) => scope !== 'mcp:use')
    .map((scope) => ({ value: scope, label: scope })),
)

const accessOptions = computed(() => [
  { value: 'everything', label: t('keys.everything') },
  { value: 'chosen', label: t('keys.chosen') },
])

const lifetimeOptions = computed(() =>
  LIFETIMES.map((days) => ({ value: days, label: t(`keys.days-${days}`) })),
)

/** The address the program connects to, as the server printed it — when it prints one. */
const address = computed<string>(() => {
  const meta = context.state.manifest?.modules.find((module) => module.id === 'connect')?.meta

  return typeof meta?.url === 'string' ? meta.url : ''
})

watch(
  () => form.value.admin,
  (owner) => {
    // Your own key asks without naming anybody: the same answer, and no lookup of a person.
    if (props.mine) {
      void keys.scopesFor().then(
        (usable) => (scopes.value = usable),
        (error) => toast.danger(message(error)),
      )

      return
    }

    if (owner === null) {
      scopes.value = []

      return
    }

    void keys.scopesFor(owner).then(
      (usable) => {
        // Only answers for the owner still chosen: a quick change of mind must not leave the
        // scopes of the person before.
        if (form.value.admin !== owner) return

        scopes.value = usable
        form.value.scopes = form.value.scopes.filter((scope) => usable.includes(scope))
      },
      (error) => toast.danger(message(error)),
    )
  },
  { immediate: props.mine },
)

if (!props.mine) {
  void admins.list({ active: 'yes', per_page: 100, sort: 'name' }).then(
    (page) =>
      (people.value = page.data.map((one) => ({
        value: one.id,
        label: `${one.name} · ${one.email}`,
      }))),
    (error) => toast.danger(message(error)),
  )
}

function errorOf(field: string): string | undefined {
  return errors.value[field]?.[0] ?? errors.value[`${field}.0`]?.[0]
}

async function issue(): Promise<void> {
  errors.value = {}

  if (form.value.access === 'chosen' && form.value.scopes.length === 0) {
    errors.value = { scopes: [t('keys.scopes-required')] }

    return
  }

  saving.value = true

  try {
    issued.value = await keys.issue({
      name: form.value.name,
      admin: form.value.admin ?? 0,
      scopes: form.value.access === 'chosen' ? form.value.scopes : [],
      days: form.value.days,
    })
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

async function copy(): Promise<void> {
  if (issued.value === null) return

  if (await copyText(issued.value.token)) {
    copied.value = true
  } else {
    toast.danger(t('keys.copy-failed'))
  }
}

/** Closing after issuing hands the new row back; closing before it is cancelling. */
function close(): void {
  if (issued.value) {
    resolve(issued.value.key)
  } else {
    dismiss()
  }
}
</script>

<template>
  <wx-dialog
    :open="open"
    :title="issued ? t('keys.issued-title') : t('keys.issue')"
    :width="640"
    @update:open="(next: boolean) => (next ? undefined : close())"
  >
    <div v-if="issued" class="wx-key-dialog">
      <wx-alert type="warning" :closable="false">{{ t('keys.issued-text') }}</wx-alert>

      <wx-form-item :label="t('keys.secret')">
        <div class="wx-key-dialog__secret">
          <wx-input :model-value="issued.token" readonly name="token" />
          <wx-button type="primary" @click="copy">
            <template #icon><wx-icon :name="copied ? 'check' : 'copy'" /></template>
            {{ copied ? t('keys.copied') : t('keys.copy') }}
          </wx-button>
        </div>
      </wx-form-item>

      <wx-form-item v-if="address" :label="t('keys.address')">
        <wx-input :model-value="address" readonly name="address" />
      </wx-form-item>

      <wx-text size="sm" tone="muted">{{ t('keys.header-hint') }}</wx-text>
    </div>

    <div v-else class="wx-key-dialog">
      <wx-text size="sm" tone="muted">{{ t(props.mine ? 'keys.lead-mine' : 'keys.lead') }}</wx-text>

      <wx-form-item :label="t('keys.name')" :error="errorOf('name')" :help="t('keys.name-hint')">
        <wx-input v-model="form.name" name="name" autocomplete="off" />
      </wx-form-item>

      <wx-form-item
        v-if="!props.mine"
        :label="t('keys.who')"
        :error="errorOf('admin')"
        :help="t('keys.who-hint')"
      >
        <wx-select v-model="form.admin" :options="people" filterable name="admin" />
      </wx-form-item>

      <wx-form-item :label="t('keys.allowed')">
        <wx-radio-group v-model="form.access" :options="accessOptions" name="access" />
      </wx-form-item>

      <wx-form-item
        v-if="form.access === 'chosen'"
        :label="t('keys.scopes')"
        :error="errorOf('scopes')"
        :help="t('keys.scopes-hint')"
      >
        <wx-select
          v-model="form.scopes"
          :options="scopeOptions"
          multiple
          filterable
          name="scopes"
        />
      </wx-form-item>

      <wx-form-item
        :label="t('keys.lifetime')"
        :error="errorOf('days')"
        :help="t('keys.lifetime-hint')"
      >
        <wx-select v-model="form.days" :options="lifetimeOptions" name="days" />
      </wx-form-item>
    </div>

    <template #footer>
      <wx-space size="sm">
        <template v-if="issued">
          <wx-button type="primary" @click="close">{{ t('keys.done') }}</wx-button>
        </template>
        <template v-else>
          <wx-button variant="outline" @click="close">{{ t('keys.cancel') }}</wx-button>
          <wx-button type="primary" :loading="saving" @click="issue">
            {{ t('keys.issue-short') }}
          </wx-button>
        </template>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style>
.wx-key-dialog {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
  container-type: inline-size;
}

/* The key and its button on one line, under each other when they do not fit: the key is long,
   and squeezing it is what would make it hard to check. */
.wx-key-dialog__secret {
  display: flex;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-key-dialog__secret > :first-child {
  flex: 1 1 auto;
  min-width: 0;
}

@container (max-width: 420px) {
  .wx-key-dialog__secret {
    flex-direction: column;
  }
}
</style>
