<script setup lang="ts">
import { computed, ref, watch, type Component } from 'vue'
import { useAdmin, useErrorText, useI18n, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxButton,
  WxCheckbox,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSelect,
  WxSpace,
  WxText,
} from '@webx-ui/core'

import { createAdminsApi } from './admins'
import { useAuthMessages } from './i18n'
import type { Admin, AdminInput, Role } from './types'

/**
 * Creating an administrator and editing one — the same form, because they differ in one field.
 *
 * The password is required the first time and optional afterwards, and a blank one on an edit
 * means "leave it alone" rather than "set it to nothing". That is the only conditional here.
 */
const props = withDefaults(
  defineProps<{
    /** Absent creates somebody; present edits them. */
    admin?: Admin | null
    /**
     * The field to pick the photograph with — `WxMediaField` in a panel that has the library.
     *
     * Handed in rather than imported: this package does not depend on the media one, and a
     * panel without a library still edits everything else about a person.
     */
    avatarField?: Component
  }>(),
  { admin: null, avatarField: undefined },
)

const { resolve, dismiss, open } = useModal<Admin>()

const context = useAdmin()
const api = createAdminsApi(context)
const i18n = useI18n()
useAuthMessages()

const t = useTranslate('webx-auth')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const roles = ref<Role[]>([])
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref<AdminInput & { avatar: string | null }>({
  name: '',
  email: '',
  password: '',
  avatar: null,
  is_active: true,
  is_super: false,
  locale: null,
  roles: [],
})

/** The field hands back `{ path, … }`; what is stored is the key. */
const photo = computed({
  get: () => (form.value.avatar ? { path: form.value.avatar } : null),
  set: (value: { path: string } | null) => {
    form.value.avatar = value?.path ?? null
  },
})

const editing = computed(() => props.admin !== null)

const roleOptions = computed(() => roles.value.map((one) => ({ value: one.id, label: one.name })))

/* A word, not an empty string: a select reads "" as nothing chosen and shows a blank control. */
const localeOptions = computed(() => [
  { value: 'auto', label: t('admins.locale-auto') },
  ...i18n.state.panelLocales.map((one) => ({ value: one.code, label: one.nativeName })),
])

void api.roles().then((all) => (roles.value = all))

watch(
  () => props.admin,
  (admin) => {
    form.value = {
      name: admin?.name ?? '',
      email: admin?.email ?? '',
      password: '',
      avatar: admin?.avatar ?? null,
      is_active: admin?.is_active ?? true,
      is_super: admin?.is_super ?? false,
      locale: admin?.locale ?? 'auto',
      roles: admin?.roles.map((one) => one.id) ?? [],
    }
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

  const payload: AdminInput = {
    name: form.value.name,
    email: form.value.email,
    avatar: form.value.avatar,
    is_active: form.value.is_active,
    is_super: form.value.is_super,
    locale: form.value.locale === 'auto' ? null : form.value.locale,
    roles: form.value.roles ?? [],
  }

  if (form.value.password) {
    payload.password = form.value.password
  }

  try {
    const saved = props.admin
      ? await api.update(props.admin.id, payload)
      : await api.create(payload)

    toast.success(t(editing.value ? 'admins.updated' : 'admins.created'))
    resolve(saved)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]>; message?: string } }).body

    if (body?.errors) {
      errors.value = body.errors
    } else {
      // The two refusals that keep a panel reachable — the last super administrator, and doing
      // it to yourself — arrive as a 422 with a message and no field, which is the one shape
      // the panel repeats back word for word.
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="editing ? t('admins.edit-short') : t('admins.new-short')"
    :width="720"
  >
    <!--
      The photograph beside the fields rather than above them: it is square, the fields are a
      column, and stacking the two makes somebody scroll past a picture to reach a name. Under
      `--wx-admin-form-wrap` the sidebar goes back on top, which is the only shape that fits a
      phone.
    -->
    <div class="wx-admin-form">
      <div class="wx-admin-form__grid">
        <div v-if="avatarField" class="wx-admin-form__aside">
          <component
            :is="avatarField"
            v-model="photo"
            :label="t('admins.avatar')"
            aspect="1/1"
            :captions="false"
          />
        </div>

        <div class="wx-admin-form__fields">
          <wx-form-item :label="t('admins.name')" :error="errorOf('name')">
            <wx-input v-model="form.name" name="name" />
          </wx-form-item>

          <wx-form-item :label="t('admins.email')" :error="errorOf('email')">
            <wx-input v-model="form.email" type="email" name="email" autocomplete="off" />
          </wx-form-item>

          <wx-form-item
            :label="t('admins.password')"
            :error="errorOf('password')"
            :help="editing ? t('admins.password-keep') : t('admins.password-hint')"
          >
            <wx-input
              v-model="form.password"
              type="password"
              name="password"
              autocomplete="new-password"
            />
          </wx-form-item>

          <wx-form-item :label="t('admins.roles')" :error="errorOf('roles')">
            <wx-select v-model="form.roles" :options="roleOptions" multiple />
          </wx-form-item>

          <wx-form-item :label="t('admins.locale')">
            <wx-select v-model="form.locale" :options="localeOptions" />
          </wx-form-item>

          <wx-space size="md">
            <wx-checkbox v-model="form.is_active" :label="t('admins.active')" />
            <wx-checkbox v-model="form.is_super" :label="t('admins.super')" />
          </wx-space>

          <wx-text size="sm" tone="muted">{{ t('admins.super-hint') }}</wx-text>
        </div>
      </div>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('admins.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" @click="save">
          {{ t('admins.save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style>
/* The container is the wrapper, not the grid: an element cannot query its own width. */
.wx-admin-form {
  container-type: inline-size;
}

.wx-admin-form__grid {
  display: grid;
  grid-template-columns: 200px minmax(0, 1fr);
  gap: var(--wx-space-24);
}

.wx-admin-form__fields {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

/* The dialog's own width decides this, not the window's. */
@container (max-width: 520px) {
  .wx-admin-form__grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .wx-admin-form__aside {
    max-width: 200px;
  }
}
</style>
