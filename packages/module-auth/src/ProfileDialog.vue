<script setup lang="ts">
import { computed, inject, ref, type Component } from 'vue'
import { routerKey } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useI18n,
  useTheme,
  useTranslate,
  type ThemePreference,
} from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSelect,
  WxSwitch,
  WxTab,
  WxTabs,
  WxText,
  WxThemeSwitch,
} from '@webx-ui/core'
import { useAuth } from './session'
import { useAuthMessages } from './i18n'

/**
 * Somebody editing themselves (WEBX_UI_PERMISSIONS.md §8).
 *
 * Not `AdminDialog` with the fields taken away. What a person may change about themselves and
 * what an administrator may change about them are different lists, and the difference is the
 * whole point: roles, super and active are not here, which is what makes this reachable by
 * everybody rather than by whoever holds `admins.manage`.
 *
 * Four sections, each answering one question. The profile and the password are saved together
 * by the button at the foot, as they always were. The interface takes effect as it is touched —
 * the same switches as the corner menu, and a theme that waits for a button is a theme nobody
 * can preview. The address is its own errand: a letter to the new one, and nothing changes
 * until its link is followed — so it has its own button, and where the site sends no mail it
 * says who changes the address instead.
 */
const props = withDefaults(
  defineProps<{
    /** The field to pick the photograph with — `WxMediaField` in a panel with the library. */
    avatarField?: Component
  }>(),
  { avatarField: undefined },
)

type Section = 'main' | 'interface' | 'password' | 'email'

const { resolve, dismiss, open } = useModal<true>()

const context = useAdmin()
const auth = useAuth()
const i18n = useI18n()
const theme = useTheme()
// Asked for quietly: a dialog drawn outside a panel's router simply has no page to point at.
const router = inject(routerKey, null)
useAuthMessages()

const t = useTranslate('webx-auth')
// The theme switch is the panel's own control, so its words are the panel's too.
const panel = i18n.scope('webx-admin')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const section = ref<Section>('main')
const saving = ref(false)
const sending = ref(false)
const errors = ref<Record<string, string[]>>({})

const user = computed(() => context.state.user)

const form = ref({
  name: user.value?.name ?? '',
  avatar: (user.value?.avatar as string | null | undefined) ?? null,
  password: '',
  current_password: '',
})

const email = ref({ email: '', current_password: '' })
/** Where the last letter went, said under the form until the dialog closes. */
const sent = ref<{ email: string; minutes: number } | null>(null)

/** The field hands back `{ path, … }`; what is stored is the key. */
const photo = ref<{ path: string } | null>(form.value.avatar ? { path: form.value.avatar } : null)

/** An older server says nothing, and nothing is offered: it would refuse the letter anyway. */
const canChangeEmail = computed(() => user.value?.emailChange === true)

// One language is not a choice.
const languages = computed(() =>
  i18n.state.panelLocales.map((one) => ({ value: one.code, label: one.nativeName })),
)

const siteBar = computed(() =>
  typeof user.value?.siteBar === 'boolean' ? user.value.siteBar : null,
)

/** The page with this person's own agents and keys, where the panel has one. */
const hasConnect = computed(
  () =>
    router?.hasRoute('webx.connect') === true &&
    (context.state.manifest?.modules.some((one) => one.id === 'connect') ?? false),
)

/* The same order as the corner menu: the screen first, the account behind it. */
const preference = computed<ThemePreference>({
  get: () => theme.state.preference,
  set: (value) => {
    theme.set(value)
    void auth.setTheme(value)
  },
})

async function chooseLanguage(code: unknown): Promise<void> {
  if (typeof code !== 'string' || code === i18n.state.locale) return

  try {
    await auth.setLocale(code)
  } catch (error) {
    toast.danger(message(error))
  }
}

function toggleSiteBar(value: unknown): void {
  void auth.setSiteBar(value === true).catch((error) => toast.danger(message(error)))
}

function errorOf(field: string): string | undefined {
  return errors.value[field]?.[0]
}

function failed(error: unknown): void {
  const body = (error as { body?: { errors?: Record<string, string[]> } }).body

  if (body?.errors) {
    errors.value = body.errors
  } else {
    toast.danger(message(error))
  }
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
    failed(error)

    // The error is on a field of another section: open the one it is under.
    if (errors.value.password || errors.value.current_password) section.value = 'password'
    else if (errors.value.name) section.value = 'main'
  } finally {
    saving.value = false
  }
}

async function sendLink(): Promise<void> {
  sending.value = true
  errors.value = {}

  try {
    sent.value = await auth.requestEmailChange(email.value.email, email.value.current_password)
    email.value = { email: '', current_password: '' }
  } catch (error) {
    failed(error)
  } finally {
    sending.value = false
  }
}

/** The foot follows the section: the address has its own errand, the interface has none. */
const footer = computed<'save' | 'send' | 'close'>(() => {
  if (section.value === 'email') return canChangeEmail.value ? 'send' : 'close'
  if (section.value === 'interface') return 'close'

  return 'save'
})

function openConnect(): void {
  dismiss()
  void router?.push({ name: 'webx.connect' })
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('profile.title')" :width="600">
    <wx-tabs v-model="section" variant="line" keep-alive :aria-label="t('profile.title')">
      <wx-tab value="main" :label="t('profile.tab-main')">
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

              <!-- Shown here, changed in its own section: see the note on the component. -->
              <wx-form-item :label="t('admins.email')">
                <wx-input :model-value="user?.email ?? ''" readonly />
              </wx-form-item>

              <div v-if="hasConnect" class="wx-profile__agents">
                <div class="wx-profile__agents-text">
                  <wx-text size="sm" weight="medium">{{ t('profile.agents') }}</wx-text>
                  <wx-text size="xs" tone="muted">{{ t('profile.agents-hint') }}</wx-text>
                </div>
                <wx-button variant="outline" size="sm" @click="openConnect">
                  {{ t('profile.agents-open') }}
                </wx-button>
              </div>
            </div>
          </div>
        </div>
      </wx-tab>

      <wx-tab value="interface" :label="t('profile.tab-interface')">
        <div class="wx-profile__section">
          <wx-text size="sm" tone="muted">{{ t('profile.interface-hint') }}</wx-text>

          <wx-form-item v-if="languages.length > 1" :label="t('profile.language')">
            <wx-select
              :model-value="i18n.state.locale"
              :options="languages"
              name="locale"
              @update:model-value="chooseLanguage"
            />
          </wx-form-item>

          <wx-form-item :label="t('profile.theme')">
            <wx-theme-switch
              v-model="preference"
              :aria-label="panel('theme.label')"
              :light-label="panel('theme.light')"
              :dark-label="panel('theme.dark')"
              :system-label="panel('theme.system')"
            />
          </wx-form-item>

          <wx-form-item v-if="siteBar !== null" :help="t('profile.site-bar-hint')">
            <wx-switch
              :model-value="siteBar"
              :label="t('profile.site-bar')"
              name="site_bar"
              @update:model-value="toggleSiteBar"
            />
          </wx-form-item>
        </div>
      </wx-tab>

      <wx-tab value="password" :label="t('profile.tab-password')">
        <div class="wx-profile__section">
          <wx-form-item
            :label="t('profile.current-password')"
            :help="t('profile.current-password-hint')"
            :error="section === 'password' ? errorOf('current_password') : undefined"
          >
            <wx-input
              v-model="form.current_password"
              type="password"
              name="current_password"
              autocomplete="current-password"
            />
          </wx-form-item>

          <wx-form-item
            :label="t('profile.password')"
            :help="t('profile.password-hint')"
            :error="errorOf('password')"
          >
            <wx-input
              v-model="form.password"
              type="password"
              name="password"
              autocomplete="new-password"
            />
          </wx-form-item>
        </div>
      </wx-tab>

      <wx-tab value="email" :label="t('profile.tab-email')">
        <div class="wx-profile__section">
          <wx-form-item :label="t('profile.email-current')">
            <wx-input :model-value="user?.email ?? ''" readonly />
          </wx-form-item>

          <wx-alert v-if="!canChangeEmail" type="info" :closable="false">
            {{ t('profile.email-unavailable') }}
          </wx-alert>

          <template v-else>
            <wx-alert v-if="sent" type="success" :closable="false">
              {{ t('profile.email-sent', { email: sent.email, minutes: sent.minutes }) }}
            </wx-alert>

            <wx-form-item
              :label="t('profile.email-new')"
              :help="t('profile.email-hint')"
              :error="errorOf('email')"
            >
              <wx-input v-model="email.email" type="email" name="new_email" autocomplete="email" />
            </wx-form-item>

            <wx-form-item
              :label="t('profile.current-password')"
              :help="t('profile.email-password-hint')"
              :error="section === 'email' ? errorOf('current_password') : undefined"
            >
              <wx-input
                v-model="email.current_password"
                type="password"
                name="email_current_password"
                autocomplete="current-password"
              />
            </wx-form-item>
          </template>
        </div>
      </wx-tab>
    </wx-tabs>

    <template #footer>
      <template v-if="footer === 'close'">
        <wx-button @click="dismiss()">{{ t('profile.cancel') }}</wx-button>
      </template>
      <template v-else-if="footer === 'send'">
        <wx-button @click="dismiss()">{{ t('profile.cancel') }}</wx-button>
        <wx-button type="primary" :loading="sending" @click="sendLink">
          {{ t('profile.email-send') }}
        </wx-button>
      </template>
      <template v-else>
        <wx-button @click="dismiss()">{{ t('profile.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" @click="save">{{
          t('profile.save')
        }}</wx-button>
      </template>
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
  padding-top: var(--wx-space-16);
}

.wx-profile__aside {
  flex: 0 0 180px;
}

.wx-profile__fields,
.wx-profile__section {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  flex: 1 1 auto;
  min-width: 0;
}

.wx-profile__section {
  padding-top: var(--wx-space-16);
}

/* A row that points elsewhere rather than a field: the agents live on their own page. */
.wx-profile__agents {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
  padding: var(--wx-space-12);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-sm);
}

.wx-profile__agents-text {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
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
