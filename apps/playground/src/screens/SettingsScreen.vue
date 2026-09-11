<script setup lang="ts">
import { computed, ref } from 'vue'
import type { ValidationErrors } from '@webx-ui/core'

/**
 * The other screen every admin has: a long form behind tabs, with a bar at the
 * bottom that only appears once something has changed. The failed save is wired to
 * a Laravel-shaped 422 — `{ field: [message] }` — because that is what `WxForm`
 * takes, and a form page that never shows an error is not a form page.
 */
const tab = ref('profile')

const form = ref({
  name: 'Олег Мороз',
  email: 'oleh@example.com',
  role: 'admin',
  bio: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
  locale: 'uk',
  timezone: 'Europe/Kyiv',

  company: 'Nova Build',
  vat: '',
  seats: 12,
  plan: 'team',
  tags: ['lorem', 'ipsum'],
  publicProfile: true,

  digest: 'weekly',
  channels: ['email'],
  mentions: true,
  comments: true,
  releases: false,

  twoFactor: false,
  sessionHours: 12,
})

/** The last saved copy, so the footer knows whether there is anything to save. */
const saved = ref(JSON.stringify(form.value))
const dirty = computed(() => JSON.stringify(form.value) !== saved.value)

const errors = ref<ValidationErrors>({})
const saving = ref(false)
const done = ref(false)

const roles = [
  { label: 'Адміністратор', value: 'admin' },
  { label: 'Редактор', value: 'editor' },
  { label: 'Спостерігач', value: 'viewer' },
]

const locales = [
  { label: 'Українська', value: 'uk' },
  { label: 'English', value: 'en' },
  { label: 'Deutsch', value: 'de' },
]

const timezones = [
  { label: 'Europe/Kyiv', value: 'Europe/Kyiv' },
  { label: 'Europe/Berlin', value: 'Europe/Berlin' },
  { label: 'UTC', value: 'UTC' },
]

const plans = [
  { label: 'Solo — €9', value: 'solo' },
  { label: 'Team — €29', value: 'team' },
  { label: 'Business — €99', value: 'business' },
]

function save() {
  saving.value = true
  done.value = false

  /* Stands in for the request. What comes back is a 422 or a 200. */
  setTimeout(() => {
    saving.value = false

    const found: ValidationErrors = {}
    if (!form.value.name.trim()) found.name = ['Вкажіть імʼя.']
    if (!form.value.email.includes('@')) found.email = ['Це не схоже на адресу пошти.']
    if (form.value.plan !== 'solo' && form.value.vat.trim() === '') {
      found.vat = ['Для командних тарифів потрібен податковий номер.']
    }

    errors.value = found
    if (Object.keys(found).length > 0) {
      /* Send the reader to the tab the complaint is about. */
      tab.value = found.name || found.email ? 'profile' : 'company'
      return
    }

    saved.value = JSON.stringify(form.value)
    done.value = true
  }, 600)
}

function revert() {
  form.value = JSON.parse(saved.value)
  errors.value = {}
  done.value = false
}
</script>

<template>
  <div class="settings">
    <wx-breadcrumb>
      <wx-breadcrumb-item href="#">Головна</wx-breadcrumb-item>
      <wx-breadcrumb-item>Налаштування</wx-breadcrumb-item>
    </wx-breadcrumb>

    <header class="settings__head">
      <div>
        <wx-heading :level="1" size="lg">Налаштування</wx-heading>
        <wx-text size="sm" tone="muted">Lorem ipsum dolor sit amet, consectetur adipiscing</wx-text>
      </div>
    </header>

    <wx-alert v-if="done" type="success" closable @close="done = false">
      Збережено. Nullam quis risus eget urna mollis ornare vel eu leo.
    </wx-alert>

    <wx-card padding="none" bordered shadow="never">
      <!-- `keep-alive`, so a switch between tabs does not throw away what was typed. -->
      <wx-tabs v-model="tab" variant="line" keep-alive class="settings__tabs">
        <wx-tab value="profile" icon="user" label="Профіль">
          <wx-form :errors="errors" class="settings__pane" @submit.prevent="save">
            <wx-row :gutter="12" wrap>
              <wx-col :span="24" :md="12">
                <wx-form-item label="Імʼя" name="name" required>
                  <wx-input v-model="form.name" placeholder="Як до вас звертатися" />
                </wx-form-item>
              </wx-col>
              <wx-col :span="24" :md="12">
                <wx-form-item label="Пошта" name="email" required help="Сюди приходять сповіщення">
                  <wx-input v-model="form.email" type="email" placeholder="you@example.com" />
                </wx-form-item>
              </wx-col>
            </wx-row>

            <wx-row :gutter="12" wrap>
              <wx-col :span="24" :md="8">
                <wx-form-item label="Роль" name="role">
                  <wx-select v-model="form.role" :options="roles" />
                </wx-form-item>
              </wx-col>
              <wx-col :span="24" :md="8">
                <wx-form-item label="Мова" name="locale">
                  <wx-select v-model="form.locale" :options="locales" />
                </wx-form-item>
              </wx-col>
              <wx-col :span="24" :md="8">
                <wx-form-item label="Часовий пояс" name="timezone">
                  <wx-select v-model="form.timezone" :options="timezones" filterable />
                </wx-form-item>
              </wx-col>
            </wx-row>

            <wx-form-item label="Про себе" name="bio" help="Видно іншим учасникам команди">
              <wx-textarea
                v-model="form.bio"
                :autosize="{ minRows: 3, maxRows: 8 }"
                :maxlength="280"
                show-count
              />
            </wx-form-item>
          </wx-form>
        </wx-tab>

        <wx-tab value="company" icon="users" label="Компанія">
          <wx-form :errors="errors" class="settings__pane" @submit.prevent="save">
            <wx-row :gutter="12" wrap>
              <wx-col :span="24" :md="12">
                <wx-form-item label="Назва" name="company">
                  <wx-input v-model="form.company" />
                </wx-form-item>
              </wx-col>
              <wx-col :span="24" :md="12">
                <wx-form-item label="Податковий номер" name="vat">
                  <wx-input v-model="form.vat" placeholder="UA1234567890" />
                </wx-form-item>
              </wx-col>
            </wx-row>

            <wx-form-item label="Тариф" name="plan">
              <wx-radio-group v-model="form.plan">
                <wx-radio v-for="plan in plans" :key="plan.value" :value="plan.value">
                  {{ plan.label }}
                </wx-radio>
              </wx-radio-group>
            </wx-form-item>

            <wx-row :gutter="12" wrap>
              <wx-col :span="24" :md="8">
                <wx-form-item label="Місць" name="seats" help="Скільки людей має доступ">
                  <wx-input-number v-model="form.seats" :min="1" :max="200" :step="1" />
                </wx-form-item>
              </wx-col>
              <wx-col :span="24" :md="16">
                <wx-form-item label="Мітки" name="tags">
                  <wx-tags-input v-model="form.tags" placeholder="Додайте мітку" />
                </wx-form-item>
              </wx-col>
            </wx-row>

            <wx-form-item name="publicProfile">
              <wx-switch v-model="form.publicProfile" label="Показувати профіль публічно" />
            </wx-form-item>
          </wx-form>
        </wx-tab>

        <wx-tab value="notifications" icon="bell" label="Сповіщення" :badge="3">
          <div class="settings__pane settings__pane--stack">
            <wx-form-item label="Канали" help="Куди надсилати">
              <wx-checkbox-group v-model="form.channels">
                <wx-checkbox value="email" label="Пошта" />
                <wx-checkbox value="telegram" label="Telegram" />
                <wx-checkbox value="webhook" label="Webhook" />
              </wx-checkbox-group>
            </wx-form-item>

            <wx-divider spacing="sm" />

            <ul class="settings__toggles">
              <li>
                <div>
                  <wx-text weight="medium">Згадки</wx-text>
                  <wx-text size="xs" tone="muted" as="div">
                    Коли хтось згадує вас у коментарі
                  </wx-text>
                </div>
                <wx-switch v-model="form.mentions" aria-label="Згадки" />
              </li>
              <li>
                <div>
                  <wx-text weight="medium">Коментарі</wx-text>
                  <wx-text size="xs" tone="muted" as="div">Нові коментарі до ваших записів</wx-text>
                </div>
                <wx-switch v-model="form.comments" aria-label="Коментарі" />
              </li>
              <li>
                <div>
                  <wx-text weight="medium">Оновлення</wx-text>
                  <wx-text size="xs" tone="muted" as="div">Донечки про нові версії</wx-text>
                </div>
                <wx-switch v-model="form.releases" aria-label="Оновлення" />
              </li>
            </ul>

            <wx-divider spacing="sm" />

            <wx-form-item label="Дайджест">
              <wx-radio-group v-model="form.digest">
                <wx-radio value="off" label="Не надсилати" />
                <wx-radio value="daily" label="Щодня" />
                <wx-radio value="weekly" label="Щотижня" />
              </wx-radio-group>
            </wx-form-item>
          </div>
        </wx-tab>

        <wx-tab value="security" icon="lock" label="Безпека">
          <div class="settings__pane settings__pane--stack">
            <wx-alert type="warning" title="Двофакторна автентифікація вимкнена">
              Cum sociis natoque penatibus et magnis dis parturient montes.
              <template #actions>
                <wx-switch v-model="form.twoFactor" label="Увімкнути" />
              </template>
            </wx-alert>

            <wx-form-item
              label="Тривалість сесії, годин"
              help="Після цього доведеться увійти знову"
            >
              <wx-slider v-model="form.sessionHours" :min="1" :max="72" :step="1" show-value />
            </wx-form-item>

            <wx-divider spacing="sm" />

            <wx-heading :level="2" size="sm">Небезпечна зона</wx-heading>
            <wx-text size="sm" tone="muted">
              Donec ullamcorper nulla non metus auctor fringilla. Ця дія незворотна.
            </wx-text>
            <div>
              <wx-button type="danger" variant="outline" size="sm">Видалити акаунт</wx-button>
            </div>
          </div>
        </wx-tab>
      </wx-tabs>
    </wx-card>

    <!-- The bar exists only while there is something to save. -->
    <div v-if="dirty" class="settings__bar">
      <wx-text size="sm" tone="muted">Є незбережені зміни</wx-text>
      <wx-space size="sm">
        <wx-button variant="text" :disabled="saving" @click="revert">Скасувати</wx-button>
        <wx-button type="primary" :loading="saving" @click="save">Зберегти</wx-button>
      </wx-space>
    </div>
  </div>
</template>

<style scoped>
.settings {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  /* Room for the bar, so it never covers the last field. */
  padding-bottom: var(--wx-space-48);
}

.settings__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
}

.settings__tabs {
  padding: var(--wx-space-8) var(--wx-space-16) var(--wx-space-16);
}

.settings__pane {
  max-width: 760px;
  padding-top: var(--wx-space-12);
}

/* A `WxForm` brings its own row spacing; a tab that is not one needs the column. */
.settings__pane--stack {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-24);
}

.settings__toggles {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  margin: 0;
  padding: 0;
  list-style: none;
}

.settings__toggles li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-16);
}

.settings__bar {
  position: sticky;
  bottom: 0;
  z-index: var(--wx-z-index-sticky);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
  margin-top: auto;
  padding: var(--wx-space-10) var(--wx-space-16);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  box-shadow: var(--wx-shadow-popover);
}
</style>
