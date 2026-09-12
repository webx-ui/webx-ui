<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useTemplateRef } from 'vue'
import { HttpError } from '@webx-ui/admin'
import { useAuth } from './session'

/**
 * The card a panel opens on.
 *
 * No logo, no product name, no "welcome back" — an admin panel's front door should say as
 * little as possible to somebody who has no business behind it, and the people who do have
 * business behind it already know where they are.
 *
 * Every string is a prop. The library speaks English; the panels built with it do not have to.
 */
const props = withDefaults(
  defineProps<{
    emailLabel?: string
    passwordLabel?: string
    rememberLabel?: string
    submitLabel?: string
    capsLockWarning?: string
    throttleMessage?: string
    /** Offer to stay signed in. The server honours it with a long-lived cookie. */
    remember?: boolean
    /** Put the cursor in the email field. Off for a card that is not the only thing on screen. */
    autofocus?: boolean
  }>(),
  {
    emailLabel: 'Email',
    passwordLabel: 'Password',
    rememberLabel: 'Stay signed in',
    submitLabel: 'Sign in',
    capsLockWarning: 'Caps Lock is on.',
    // `{seconds}` is replaced with however long the server said to wait.
    throttleMessage: 'Too many attempts. Try again in {seconds} s.',
    remember: true,
    autofocus: true,
  },
)

const emit = defineEmits<{ success: [] }>()

const auth = useAuth()

const email = ref('')
const password = ref('')
const rememberMe = ref(false)

const busy = ref(false)
const revealed = ref(false)
const capsLock = ref(false)
const message = ref('')
const errors = ref<Record<string, string[]>>({})
const secondsLeft = ref(0)

const emailField = useTemplateRef<HTMLInputElement>('emailField')

let countdown: ReturnType<typeof setInterval> | null = null

onBeforeUnmount(stopCountdown)

const throttled = computed(() => secondsLeft.value > 0)

const throttleNotice = computed(() =>
  props.throttleMessage.replace('{seconds}', String(secondsLeft.value)),
)

async function submit(): Promise<void> {
  if (busy.value || throttled.value) {
    return
  }

  busy.value = true
  message.value = ''
  errors.value = {}

  try {
    await auth.login({
      email: email.value,
      password: password.value,
      remember: rememberMe.value,
    })

    password.value = ''
    emit('success')
  } catch (error) {
    handle(error)
  } finally {
    busy.value = false
  }
}

function handle(error: unknown): void {
  if (!(error instanceof HttpError)) {
    message.value = error instanceof Error ? error.message : String(error)

    return
  }

  // A bad password comes back as a 422 against the email field, the same as an address
  // nobody owns — the server refuses to say which, and so does this.
  if (error.isValidation) {
    errors.value = error.errors

    return
  }

  if (error.isThrottled) {
    startCountdown(error.retryAfter ?? 60)

    return
  }

  message.value = error.message
}

function startCountdown(seconds: number): void {
  stopCountdown()
  secondsLeft.value = seconds

  countdown = setInterval(() => {
    secondsLeft.value -= 1

    if (secondsLeft.value <= 0) {
      stopCountdown()
    }
  }, 1000)
}

function stopCountdown(): void {
  if (countdown !== null) {
    clearInterval(countdown)
    countdown = null
  }

  secondsLeft.value = 0
}

/**
 * Worth saying out loud: a password typed in capitals fails with the same unhelpful message
 * as a wrong one, and people retype it three times before noticing.
 */
function trackCapsLock(event: KeyboardEvent): void {
  capsLock.value = event.getModifierState('CapsLock')
}

defineExpose({ focus: () => emailField.value?.focus() })
</script>

<template>
  <wx-card class="wx-login" :padding="24">
    <wx-form :errors="errors" :disabled="busy" gap="md" @submit.prevent="submit">
      <wx-alert
        v-if="message !== ''"
        type="danger"
        variant="soft"
        live
        :description="message"
        class="wx-login__message"
      />

      <wx-alert
        v-else-if="throttled"
        type="warning"
        variant="soft"
        live
        :description="throttleNotice"
        class="wx-login__message"
      />

      <wx-form-item name="email">
        <wx-input
          ref="emailField"
          v-model="email"
          type="email"
          :placeholder="emailLabel"
          :aria-label="emailLabel"
          autocomplete="username"
          :autofocus="autofocus"
          inputmode="email"
          size="lg"
        >
          <template #prefix><wx-icon name="mail" /></template>
        </wx-input>
      </wx-form-item>

      <wx-form-item name="password" :help="capsLock ? capsLockWarning : undefined">
        <wx-input
          v-model="password"
          :type="revealed ? 'text' : 'password'"
          :placeholder="passwordLabel"
          :aria-label="passwordLabel"
          autocomplete="current-password"
          size="lg"
          @keydown="trackCapsLock"
          @keyup="trackCapsLock"
        >
          <template #prefix><wx-icon name="lock" /></template>
          <template #suffix>
            <!-- A bare icon rather than a button with a surface: it sits inside the field, and
                 a second box in there reads as a second control. Out of the tab order because
                 it is a convenience, not a step. -->
            <button
              type="button"
              class="wx-login__reveal"
              :title="revealed ? 'Hide the password' : 'Show the password'"
              :aria-label="revealed ? 'Hide the password' : 'Show the password'"
              :aria-pressed="revealed"
              tabindex="-1"
              @click="revealed = !revealed"
            >
              <wx-icon :name="revealed ? 'eye-off' : 'eye'" />
            </button>
          </template>
        </wx-input>
      </wx-form-item>

      <wx-checkbox v-if="remember" v-model="rememberMe" :label="rememberLabel" />

      <wx-button
        type="primary"
        native-type="submit"
        size="lg"
        block
        :loading="busy"
        :disabled="throttled"
      >
        {{ submitLabel }}
      </wx-button>
    </wx-form>
  </wx-card>
</template>

<style scoped>
.wx-login {
  width: 100%;
  /* Wide enough for an email address, narrow enough to stay a card rather than a page. */
  max-width: 26rem;
}

.wx-login__message {
  margin-block-end: var(--wx-space-4);
}

.wx-login__reveal {
  display: inline-flex;
  align-items: center;
  padding: 0;
  color: var(--wx-text-muted);
  background: none;
  border: 0;
  cursor: pointer;
}

.wx-login__reveal:hover,
.wx-login__reveal[aria-pressed='true'] {
  color: var(--wx-text-default);
}
</style>
