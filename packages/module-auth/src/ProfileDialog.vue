<script setup lang="ts">
import { ref, type Component } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { toast, useModal, WxButton, WxDialog, WxFormItem, WxInput } from '@webx-ui/core'
import { useAuth } from './session'
import { useAuthMessages } from './i18n'

/**
 * Somebody editing themselves.
 *
 * Not `AdminDialog` with the fields taken away. What a person may change about themselves and
 * what an administrator may change about them are different lists, and the difference is the
 * whole point: roles, super, active and the sign-in address are not here, which is what makes
 * this reachable by everybody rather than by whoever holds `admins.manage`.
 *
 * The address is shown and not editable: changing where somebody signs in has to be proved at
 * the new address first, and nothing here can do that yet.
 */
const props = withDefaults(
  defineProps<{
    /** The field to pick the photograph with — `WxMediaField` in a panel with the library. */
    avatarField?: Component
  }>(),
  { avatarField: undefined },
)

const { resolve, dismiss, open } = useModal<true>()

const context = useAdmin()
const auth = useAuth()
useAuthMessages()

const t = useTranslate('webx-auth')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref({
  name: context.state.user?.name ?? '',
  avatar: (context.state.user?.avatar as string | null | undefined) ?? null,
  password: '',
  current_password: '',
})

/** The field hands back `{ path, … }`; what is stored is the key. */
const photo = ref<{ path: string } | null>(form.value.avatar ? { path: form.value.avatar } : null)

function errorOf(field: string): string | undefined {
  return errors.value[field]?.[0]
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    await auth.updateProfile({
      name: form.value.name,
      avatar: photo.value?.path ?? null,
      // Blank is left out entirely rather than sent as an empty string: the server reads a
      // filled password as "change it", and an empty one would be a change to nothing.
      ...(form.value.password
        ? { password: form.value.password, current_password: form.value.current_password }
        : {}),
    })

    toast.success(t('profile.saved'))
    resolve(true)
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
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('profile.title')" :width="560">
    <!-- The host is what the query below measures: a container query on an element's own
         class resolves against its nearest ancestor container, never against itself. -->
    <div class="wx-profile-host">
      <div class="wx-profile">
        <div v-if="props.avatarField" class="wx-profile__aside">
          <component
            :is="props.avatarField"
            v-model="photo"
            :label="t('profile.avatar')"
            aspect="1/1"
            :captions="false"
          />
        </div>

        <div class="wx-profile__fields">
          <wx-form-item :label="t('profile.name')" :error="errorOf('name')">
            <wx-input v-model="form.name" name="name" />
          </wx-form-item>

          <!-- Shown, not offered: see the note on the component. -->
          <wx-form-item :label="t('admins.email')">
            <wx-input :model-value="context.state.user?.email ?? ''" readonly />
          </wx-form-item>

          <wx-form-item
            :label="t('profile.password')"
            :help="t('profile.password-hint')"
            :error="errorOf('password')"
          >
            <wx-input v-model="form.password" type="password" autocomplete="new-password" />
          </wx-form-item>

          <wx-form-item
            v-if="form.password"
            :label="t('profile.current-password')"
            :help="t('profile.current-password-hint')"
            :error="errorOf('current_password')"
          >
            <wx-input
              v-model="form.current_password"
              type="password"
              autocomplete="current-password"
            />
          </wx-form-item>
        </div>
      </div>
    </div>

    <template #footer>
      <wx-button @click="dismiss()">{{ t('profile.cancel') }}</wx-button>
      <wx-button type="primary" :loading="saving" @click="save">{{ t('profile.save') }}</wx-button>
    </template>
  </wx-dialog>
</template>

<style scoped>
/*
 * The photograph beside the fields, the same shape as the administrators form — and on a
 * narrow dialog it goes back on top, which is the only arrangement that fits a phone.
 */
.wx-profile-host {
  container-type: inline-size;
}

.wx-profile {
  display: flex;
  gap: var(--wx-space-16);
}

.wx-profile__aside {
  flex: 0 0 180px;
}

.wx-profile__fields {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  flex: 1 1 auto;
  min-width: 0;
}

@container (max-width: 480px) {
  .wx-profile {
    flex-direction: column;
  }

  .wx-profile__aside {
    flex: none;
  }
}
</style>
