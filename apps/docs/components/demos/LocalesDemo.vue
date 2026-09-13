<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  localesKey,
  WxFormItem,
  WxInput,
  WxLocales,
  WxTextarea,
  type LocaleOption,
} from '@webx-ui/core'
import { provide } from 'vue'

/*
 * A panel does this for you out of the manifest. Here the site's languages are made up, so the
 * demo can be looked at without one.
 */
const editing = ref('uk')

provide(localesKey, {
  list: computed<LocaleOption[]>(() => [{ code: 'uk' }, { code: 'ru' }, { code: 'en' }]),
  active: editing,
})

const title = ref<Record<string, string>>({ uk: 'Двигуни у зборі', ru: 'Двигатели в сборе' })
const description = ref<Record<string, string>>({ uk: 'Опис українською' })
const section = ref<Record<string, string>>({ uk: 'Заголовок секції' })
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">On the field</span>
      <wx-form-item label="Title">
        <wx-input v-model="title" localized name="title" placeholder="Title" />
      </wx-form-item>
      <wx-form-item label="Description">
        <wx-textarea v-model="description" localized name="description" :rows="3" />
      </wx-form-item>
      <p style="margin: 0; color: var(--wx-text-muted); font-size: 13px">
        Editing <b>{{ editing }}</b> — {{ JSON.stringify(title) }}
      </p>
    </div>

    <div>
      <span class="wx-demo__label">Around a section</span>
      <wx-locales variant="tabs">
        <template #default="{ locale }">
          <wx-input
            :model-value="section[locale!.code] ?? ''"
            :placeholder="`Heading (${locale!.code})`"
            @update:model-value="(value) => (section[locale!.code] = String(value))"
          />
        </template>
      </wx-locales>
    </div>
  </div>
</template>
