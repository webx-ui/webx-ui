<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSelect,
  WxSpace,
  WxSwitch,
} from '@webx-ui/core'
import { createSeoApi } from './api'
import { useSeoMessages } from './i18n'
import type { MatchType, SeoRedirect, SeoRoute } from './types'

/** One address that has moved, and where to. Four fields, so four fields. */
const props = withDefaults(defineProps<{ redirect?: SeoRedirect | null }>(), { redirect: null })

const { resolve, dismiss, open } = useModal<SeoRedirect>()

const context = useAdmin()
const api = createSeoApi(context)
useSeoMessages()

const t = useTranslate('webx-seo')

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const occupant = ref<SeoRoute | null>(null)

const form = ref({
  match_type: 'exact' as MatchType,
  pattern: '',
  target: '',
  status: 301,
  is_active: true,
})

const editing = computed(() => props.redirect !== null)

const kindOptions = computed(() => [
  { value: 'exact', label: t('page.exact') },
  { value: 'mask', label: t('page.mask') },
  { value: 'regex', label: t('page.regex') },
])

/* Numbers, not words: 301 and 302 are what a person types into a search when they want to know
   what one is, and a label would only stand between them and that. */
const statusOptions = [
  { value: 301, label: '301' },
  { value: 302, label: '302' },
]

watch(
  () => props.redirect,
  (redirect) => {
    form.value = {
      match_type: redirect?.match_type ?? 'exact',
      pattern: redirect?.pattern ?? '',
      target: redirect?.target ?? '',
      status: redirect?.status ?? 301,
      is_active: redirect?.is_active ?? true,
    }
    errors.value = {}
    occupant.value = null
    void shadowed()
  },
  { immediate: true },
)

/**
 * Whether a live page is standing at the address this rule is about to take over.
 *
 * A rule wins: `RedirectRequests` runs before routing, deliberately, so that an editor can
 * redirect an address the site still answers at. Which is exactly why this has to be said out
 * loud — the page does not disappear from the panel, it only stops being reachable, and the
 * person who finds out otherwise is a reader.
 *
 * Only for an exact address. A mask covers addresses that do not exist yet and a regular
 * expression covers ones nobody can enumerate; guessing at either would produce a warning that
 * is wrong often enough to be ignored, and a warning that is ignored is worse than none.
 */
async function shadowed(): Promise<void> {
  const pattern = form.value.pattern.trim()

  if (form.value.match_type !== 'exact' || pattern === '') {
    occupant.value = null

    return
  }

  try {
    occupant.value = (await api.test(pattern)).route
  } catch {
    // The check is a courtesy; a rule is not held up because it could not be made.
    occupant.value = null
  }
}

function errorOf(field: string): string | undefined {
  return errors.value[field]?.[0]
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    const saved = props.redirect
      ? await api.updateRedirect(props.redirect.id, form.value)
      : await api.createRedirect(form.value)

    toast.success(t('page.saved'))
    resolve(saved)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]>; message?: string } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('page.failed'))
    } else {
      toast.danger(body?.message ?? t('page.failed'))
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('page.redirect')" :width="640">
    <div class="wx-seo-redirect">
      <wx-form-item :label="t('page.kind')">
        <wx-select v-model="form.match_type" :options="kindOptions" @change="shadowed" />
      </wx-form-item>

      <wx-form-item
        :label="t('page.address')"
        :help="t('page.address-help')"
        :error="errorOf('pattern')"
      >
        <wx-input v-model="form.pattern" placeholder="/old-address" @blur="shadowed" />
      </wx-form-item>

      <!-- Said, not refused: the rule is allowed to shadow the page, and whoever writes it
           should know that is what they are doing. -->
      <wx-alert
        v-if="occupant"
        type="warning"
        variant="soft"
        :description="
          occupant.kind === 'alias'
            ? t('page.occupied-alias', { target: occupant.target ?? occupant.path })
            : t('page.occupied', { path: occupant.path })
        "
      />

      <wx-form-item
        :label="t('page.target')"
        :help="t('page.target-help')"
        :error="errorOf('target')"
      >
        <wx-input v-model="form.target" placeholder="/new-address" />
      </wx-form-item>

      <div class="wx-seo-redirect__row">
        <wx-form-item :label="t('page.status')">
          <wx-select v-model="form.status" :options="statusOptions" />
        </wx-form-item>

        <wx-form-item :label="t('page.state')">
          <wx-switch v-model="form.is_active" />
        </wx-form-item>
      </div>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('page.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" @click="save">
          {{ editing ? t('page.save') : t('page.new-redirect') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-seo-redirect {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-seo-redirect__row {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: var(--wx-space-12);
  align-items: start;
}
</style>
