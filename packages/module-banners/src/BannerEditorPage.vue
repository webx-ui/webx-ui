<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  confirm,
  localizedValue,
  toast,
  useLocales,
  WxActionBar,
  WxBadge,
  WxButton,
  WxCard,
  WxFormItem,
  WxSelect,
  WxSkeleton,
  type LocalizedValue,
} from '@webx-ui/core'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxSaveState,
  WxScreen,
  WxScreenHead,
  type ScreenAction,
  useBodyKeys,
} from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import { createBannersApi } from './api'
import { useBannersMessages } from './i18n'
import type { BannerDetail, BannerSummary, PlaceRow } from './types'

/**
 * One banner, on a page of its own: a head, the place, the described screen, and one button.
 *
 * A page and not a pane beside the list, because the form is long — three pictures, the words,
 * up to three buttons — and `banners.form` is a described screen a project adds its own fields
 * to (decision 14). One explicit save, no autosave: a banner has no draft (decision 13), so what
 * is saved is on the site at once, if it is switched on.
 *
 * The place is not a field of the screen (decision 9). The list of places is alive — own places
 * come and go — and the options of a described select are patched on when the server boots, so a
 * live list has nowhere to go there. It is a select of its own above the screen, and rides beside
 * the values: `PUT { values, place }`. Changing it marks the form changed like any field does.
 *
 * A new banner is a form first and a record on the first save: pressing "New banner" and walking
 * away should not leave an empty, switched-off row at the end of a place.
 */
defineOptions({ name: 'WxBannerEditorPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/banners' })

const admin = useAdmin()
const api = createBannersApi(admin)
const route = useRoute()
const router = useRouter()
const locales = useLocales()
useBannersMessages()

const t = useTranslate('webx-banners')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

/** `null` — a banner not written yet (`/banners/new`). */
const id = computed<number | null>(() => {
  const raw = route.params.id

  return typeof raw === 'string' && raw !== '' ? Number(raw) : null
})

const banner = ref<BannerSummary | null>(null)
const values = ref<ScreenModel>({})
const snapshot = ref('')
const place = ref<string | null>(null)
/** The place the banner stands in on the server — what "changed" and "back" are measured by. */
const placeSaved = ref<string | null>(null)
const places = ref<PlaceRow[]>([])

const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const canManage = computed(() => admin.can('banners.manage'))

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(
  () =>
    snapshot.value !== '' && (current.value !== snapshot.value || place.value !== placeSaved.value),
)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value || id.value === null ? 'unsaved' : 'saved'
})

/** The title follows the field rather than the answer: an edit shows at the top as it is typed. */
const title = computed(() => {
  const written = values.value.title as LocalizedValue | string | null | undefined
  const fallback = id.value === null ? t('banner.new') : (banner.value?.title ?? '')

  return localizedValue(written, locales.active.value, fallback) || t('banner.untitled')
})

/** Back to the place the banner stands in, and not to the section's first one. */
const back = computed(() => {
  const key = placeSaved.value ?? place.value

  return key === null ? props.base : `${props.base}?place=${encodeURIComponent(key)}`
})

const section = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'banners')?.title ??
    t('module.banners'),
)

const placeOptions = computed(() =>
  places.value.map((one) => ({ value: one.key, label: `${one.title} · ${one.key}` })),
)

const placeModel = computed<string | number | null>({
  get: () => place.value,
  set: (value) => {
    place.value = value === null || value === undefined ? null : String(value)
  },
})

/** What the server would answer for a banner with nothing in it yet (§5.6). */
function blank(): ScreenModel {
  return {
    image: null,
    image_mobile: null,
    video: null,
    title: {},
    text: {},
    buttons: [],
    enabled: false,
  }
}

function take(detail: BannerDetail): void {
  banner.value = detail.banner
  values.value = detail.values
  snapshot.value = JSON.stringify(detail.values)
  place.value = detail.banner.place
  placeSaved.value = detail.banner.place
}

async function load(): Promise<void> {
  loading.value = true

  try {
    const [list, detail] = await Promise.all([
      api.places(),
      id.value === null ? Promise.resolve(null) : api.get(id.value),
    ])

    places.value = list

    if (detail !== null) {
      take(detail)
    } else {
      const asked = route.query.place
      const key = typeof asked === 'string' && list.some((one) => one.key === asked) ? asked : null
      const fresh = blank()

      banner.value = null
      values.value = fresh
      snapshot.value = JSON.stringify(fresh)
      place.value = key ?? list[0]?.key ?? null
      placeSaved.value = place.value
    }
  } catch (error) {
    toast.danger(message(error))
    void router.push(props.base)
  } finally {
    loading.value = false
  }
}

async function save(): Promise<void> {
  if (saving.value || !canManage.value || place.value === null) return

  saving.value = true
  errors.value = {}

  try {
    const made = id.value === null
    const detail = made
      ? await api.create(place.value, values.value)
      : await api.save(
          id.value as number,
          values.value,
          place.value !== placeSaved.value ? place.value : undefined,
        )

    take(detail)
    toast.success(t('banner.saved'))

    // A record now, with an address of its own; `replace`, so "back" does not reopen a blank form.
    if (made) void router.replace(`${props.base}/${detail.banner.id}`)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('banner.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

async function remove(): Promise<void> {
  const record = banner.value

  if (!record) return

  const agreed = await confirm({
    title: t('banner.delete-title', { name: record.title }),
    message: t('banner.delete-text'),
    confirmText: t('banner.delete'),
    cancelText: t('banner.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(record.id)
    toast.success(t('banner.deleted'))
    // Nothing left to protect: the record is in the bin, and asking about its unsaved edits on
    // the way out would be asking about a banner that is gone.
    snapshot.value = current.value
    place.value = placeSaved.value
    void router.push(back.value)
  } catch (error) {
    toast.danger(message(error))
  }
}

/* The browser's own guard: it cannot wait for a request, so all it can do is ask. */
function leaveGuard(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

/* Ctrl+S saves, as it does everywhere else that has a save button. */
function onKeydown(event: KeyboardEvent): void {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
    event.preventDefault()
    if (dirty.value || id.value === null) void save()
  }
}

// And from the page around the form, where a click on empty space leaves the focus.
useBodyKeys(onKeydown)

/*
 * `/banners/new` and `/banners/5` are one component on two routes, and the router patches it
 * rather than mounting it again (CLAUDE.md §4) — so a change of banner is watched for. The first
 * save moving the address to the record it just made is not a change: that banner is on screen.
 */
watch(id, (next) => {
  if (next !== null && banner.value?.id === next) return

  void load()
})

onMounted(() => {
  void load()
  window.addEventListener('beforeunload', leaveGuard)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', leaveGuard)
})

onBeforeRouteLeave(async () => {
  if (!dirty.value || !canManage.value) return true

  return await confirm({
    title: t('banner.leave-title'),
    message: t('banner.leave-text'),
    confirmText: t('banner.leave'),
    cancelText: t('banner.cancel'),
    tone: 'danger',
  })
})

/* What leads away from the banner. On a phone the head folds it into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value && banner.value !== null
    ? [
        {
          key: 'delete',
          label: t('banner.delete'),
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
  <div class="wx-banner-editor" @keydown="onKeydown">
    <template v-if="loading">
      <!-- Shaped like the screen it stands in for: the head, and a card for what is coming. -->
      <wx-skeleton class="wx-banner-editor__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="6" /></wx-card>
    </template>

    <template v-else>
      <wx-screen-head divider :back="back" :back-label="section" :title="title" :actions="actions">
        <template v-if="banner && !banner.enabled" #title-after>
          <wx-badge dot>{{ t('banner.disabled') }}</wx-badge>
        </template>
      </wx-screen-head>

      <wx-card class="wx-banner-editor__place">
        <wx-form-item
          :label="t('banner.place')"
          :help="id === null ? undefined : t('banner.place-help')"
          :error="errors.place?.[0]"
          required
        >
          <wx-select
            v-model="placeModel"
            :options="placeOptions"
            :disabled="!canManage || saving"
            :aria-label="t('banner.place')"
          />
        </wx-form-item>
      </wx-card>

      <wx-screen
        v-model="values"
        name="banners.form"
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
          :disabled="(!dirty && id !== null) || place === null"
          @click="save"
        >
          {{ t('banner.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-banner-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-banner-editor__ghost {
  max-width: 420px;
}

/* A select as wide as a place's name needs, not as the page: it is one choice, not a text. */
.wx-banner-editor__place :deep(.wx-form-item) {
  max-width: 420px;
  margin-bottom: 0;
}
</style>
