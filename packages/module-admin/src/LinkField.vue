<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { WxButton, WxIcon, WxPopover, WxText, fileIconName, type IconName } from '@webx-ui/core'
import { useAdmin } from './admin'
import FieldRow from './FieldRow.vue'
import { readableSize } from './fileSize'
import type { PickedFile } from './types'
import { useI18n, useTranslate } from './i18n'
import LinkPicker from './LinkPicker.vue'
import { linkSources } from './linkSources'
import {
  createLinksApi,
  type LinkRel,
  type LinkSourceInfo,
  type LinkValue,
  type ResolvedLink,
} from './links'

/**
 * A link field that takes one row of the form: what the link points at, said in two lines, and
 * the picker in a panel under it.
 *
 * The picker is the one every link in the panel uses, untouched — this only reads the value to
 * draw the row, so the value, its validation and what the server keeps are exactly what they
 * were. Most links are "the page X"; the picker's six controls are for the few that are not, and
 * a dialog that showed them all the time spent half of its height on them.
 *
 * On a described screen this is `wx-link`: the label, the help line and the errors are the
 * renderer's. Everything else the node wrote travels to the picker as an attribute rather than as
 * a declared prop: declaring the picker's would turn every boolean the node left alone into
 * `false` on the way through.
 */
defineOptions({ name: 'WxLinkField', inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    /** A link that goes nowhere is a choice on a menu item and noise on a button. */
    allowNone?: boolean
    /** Off where the two attributes belong to whatever holds the link rather than to the field. */
    attributes?: boolean
    /**
     * A file of the library as a target — what a button that downloads a brochure needs. On
     * everywhere, as on the server; off (`props.files: false`) only where the link has to be a
     * page — where a widget stands, which page to preview.
     */
    files?: boolean
    disabled?: boolean
    /** What the empty row says. The panel's own words by default. */
    placeholder?: string
    /** The name of the panel for screen readers — usually the label of the field it belongs to. */
    label?: string
  }>(),
  {
    allowNone: true,
    attributes: true,
    files: true,
    disabled: false,
    placeholder: undefined,
    label: undefined,
  },
)

const emit = defineEmits<{ open: []; close: [] }>()

const value = defineModel<LinkValue | null>({ default: null })

const admin = useAdmin()
const i18n = useI18n()
const api = createLinksApi(admin)
const t = useTranslate('webx-admin')

const open = ref(false)

/** The entity behind the value, as the server names it and addresses it right now. */
const resolved = ref<ResolvedLink | null>(null)

/** The sections' own names — "Pages", "Articles" — to say which kind of thing was chosen. */
const sectionTitles = ref<Record<string, string>>({})

const pair = computed(() => {
  const link = value.value

  if (link === null || link.target !== 'entity') return null
  if (link.entity_type === null || link.entity_id === null) return null

  return { type: link.entity_type, id: link.entity_id }
})

/*
 * The address is asked of the server rather than kept: the row shows where the page is today, and
 * a value saved before the page moved holds only the pair. Asked again whenever the pair changes —
 * a pick in the panel, a form that discarded a draft.
 */
watch(
  () => (pair.value === null ? '' : `${pair.value.type}:${pair.value.id}`),
  async (key) => {
    if (pair.value === null) {
      resolved.value = null

      return
    }

    const asked = pair.value
    let sources: LinkSourceInfo[]
    let found: ResolvedLink[]

    try {
      ;[sources, found] = await Promise.all([
        linkSources(admin, api),
        api.resolve([asked], i18n.state.locale),
      ])
    } catch {
      // The row is only a picture of the value: without an answer it says what it can, and the
      // picker in the panel asks again on its own when it opens.
      return
    }

    // An answer to a question nobody is asking any more: the pair changed while it travelled.
    if (key !== `${pair.value?.type}:${pair.value?.id}`) return

    sectionTitles.value = Object.fromEntries(sources.map((source) => [source.type, source.title]))
    resolved.value = found.find((one) => one.type === asked.type && one.id === asked.id) ?? null
  },
  { immediate: true },
)

type Kind = 'empty' | 'entity' | 'url' | 'file' | 'none'

interface Summary {
  kind: Kind
  icon: IconName
  title: string
  detail: string | null
  /** The second line is an address or a key, said in the monospace face; a size is words. */
  mono?: boolean
  accent?: 'primary' | 'danger' | null
}

/** The file behind a `file` link, as the library describes it now; `null` — it has no such key. */
const described = ref<PickedFile | null | undefined>(undefined)

watch(
  () => (value.value?.target === 'file' ? (value.value.path ?? null) : null),
  async (path) => {
    described.value = undefined

    const describe = admin.describeFile

    if (path === null || !describe) return

    let found: PickedFile | null

    try {
      found = await describe(path)
    } catch {
      // The row says what it can from the key; the picker in the panel asks again on its own.
      return
    }

    if (path === (value.value?.path ?? null)) described.value = found
  },
  { immediate: true },
)

/** The fragment as the site writes it, or nothing. */
function anchor(link: LinkValue): string {
  return link.hash ? `#${link.hash}` : ''
}

/**
 * What the first line calls an address: the host of a full one, the address itself otherwise —
 * a path of this site, a `mailto:` — where there is no host worth saying apart.
 */
function host(url: string): string {
  try {
    const parsed = new URL(url)

    if (parsed.host !== '') return parsed.host.replace(/^www\./, '')

    return url.replace(/^(mailto|tel):/i, '')
  } catch {
    return url
  }
}

/**
 * An address of the panel's own site as a path: the resolver answers with a full address, and on
 * one row the host in front of every page is the same twenty characters the path gets cut for.
 */
function ownPath(url: string): string {
  try {
    const parsed = new URL(url, window.location.origin)

    if (parsed.origin !== window.location.origin) return url

    return parsed.pathname + parsed.search
  } catch {
    return url
  }
}

const summary = computed<Summary>(() => {
  const link = value.value
  const empty: Summary = {
    kind: 'empty',
    icon: 'plus',
    title: props.placeholder ?? t('links.field-placeholder'),
    detail: null,
  }

  if (link === null) return empty

  if (link.target === 'none') {
    return { kind: 'none', icon: 'ban', title: t('links.field-none'), detail: null }
  }

  if (link.target === 'url') {
    if (!link.url) return empty

    return { kind: 'url', icon: 'link', title: host(link.url), detail: link.url + anchor(link) }
  }

  if (link.target === 'file') {
    if (!link.path) return empty

    const name = link.path.split('/').pop() ?? link.path
    const file = described.value

    if (file === null) {
      return {
        kind: 'file',
        icon: 'warning',
        title: name,
        detail: t('links.file-gone'),
        mono: false,
        accent: 'danger',
      }
    }

    const size = file?.size ? readableSize(file.size, i18n.state.locale) : null
    const extension = (file?.name ?? name).split('.').pop()?.toUpperCase() ?? null

    return {
      kind: 'file',
      icon: fileIconName(file?.name ?? name),
      title: file?.name ?? name,
      detail: size ? [extension, size].filter(Boolean).join(' · ') : link.path,
      mono: !size,
    }
  }

  if (pair.value === null) return empty

  const section = sectionTitles.value[pair.value.type] ?? pair.value.type
  const found = resolved.value
  // A draft has no address yet; the row says so instead of drawing a lone `#anchor`.
  const where = found?.url ? ownPath(found.url) + anchor(link) : t('links.unavailable')

  return {
    kind: 'entity',
    icon: 'file-text',
    accent: 'primary',
    // Until the server answers there is no name to show, and "gone" would be a lie for a moment.
    title: found?.title ?? (sectionTitles.value[pair.value.type] ? t('links.missing') : '…'),
    detail: `${section} · ${where}`,
  }
})

interface Flag {
  key: string
  text: string | null
  icon: IconName | null
  title: string
}

/** The switches that are on, and only those: an off switch is the usual case and says nothing. */
const flags = computed<Flag[]>(() => {
  const link = value.value

  if (link === null || link.target === 'none' || summary.value.kind === 'empty') return []

  const on: Flag[] = []

  if (link.new_tab) {
    on.push({ key: 'new-tab', text: null, icon: 'external-link', title: t('links.new-tab') })
  }

  for (const rel of ['nofollow', 'sponsored', 'ugc'] satisfies LinkRel[]) {
    if (link.rel.includes(rel)) {
      on.push({ key: rel, text: rel, icon: null, title: t(`links.rel-${rel}`) })
    }
  }

  return on
})

const panelName = computed(() => props.label ?? t('links.field-panel'))

function onOpen(): void {
  emit('open')
}

function onClose(): void {
  emit('close')
}
</script>

<template>
  <wx-popover
    v-model:open="open"
    class="wx-link-field__panel"
    side="bottom"
    align="start"
    :offset="8"
    :arrow="false"
    width="var(--reka-popover-trigger-width)"
    :disabled="props.disabled"
    :aria-label="panelName"
    @open="onOpen"
    @close="onClose"
  >
    <template #trigger>
      <field-row
        class="wx-link-field"
        :kind="summary.kind"
        :icon="summary.icon"
        :title="summary.title"
        :detail="summary.detail"
        :mono="summary.mono"
        :accent="summary.accent"
        :open="open"
        :disabled="props.disabled"
      >
        <template #flags>
          <span v-if="flags.length" class="wx-link-field__flags">
            <span
              v-for="flag in flags"
              :key="flag.key"
              class="wx-link-field__flag"
              :class="`is-${flag.key}`"
              :title="flag.title"
            >
              <wx-icon v-if="flag.icon" :name="flag.icon" size="sm" />
              <template v-else>{{ flag.text }}</template>
              <span class="wx-link-field__flag-name">{{ flag.title }}</span>
            </span>
          </span>
        </template>
      </field-row>
    </template>

    <link-picker
      v-model="value"
      v-bind="$attrs"
      :allow-none="props.allowNone"
      :attributes="props.attributes"
      :files="props.files"
      :disabled="props.disabled"
    />

    <template #footer="{ close }">
      <wx-text size="sm" tone="muted" class="wx-link-field__hint">
        {{ t('links.field-hint') }}
      </wx-text>
      <wx-button size="sm" type="primary" @click="close">{{ t('links.field-done') }}</wx-button>
    </template>
  </wx-popover>
</template>

<style scoped>
.wx-link-field__flags {
  display: inline-flex;
  flex: 0 1 auto;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-link-field__flag {
  display: inline-flex;
  align-items: center;
  height: 20px;
  padding: 0 var(--wx-space-6);
  background: var(--wx-bg-fill);
  border-radius: var(--wx-radius-xs);
  color: var(--wx-text-muted);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  line-height: 1;
}

/* The badge is a picture of the word; the word itself is still read out. */
.wx-link-field__flag-name {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
</style>

<style>
/*
 * The panel is teleported, so it cannot be scoped. As wide as the row, whatever the popover's own
 * cap: the picker's search and section stand side by side only with the room the row has.
 */
.wx-popover.wx-link-field__panel {
  min-width: 280px;
  max-width: calc(100vw - var(--wx-space-32));
}

.wx-link-field__panel .wx-popover__footer {
  justify-content: space-between;
  padding-top: var(--wx-space-10);
  border-top: 1px solid var(--wx-border-muted);
}
</style>
