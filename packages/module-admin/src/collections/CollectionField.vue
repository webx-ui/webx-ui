<script setup lang="ts">
import { computed, ref, useId, watch } from 'vue'
import {
  WxButton,
  WxInputNumber,
  WxSelect,
  WxSwitch,
  type SelectModelValue,
  type SelectOption,
} from '@webx-ui/core'
import { useAdmin } from '../admin'
import { createCategoriesApi } from '../categories/api'
import { useTranslate } from '../i18n'
import RelationsField from '../relations/RelationsField.vue'
import {
  COLLECTION_MAX_LIMIT,
  collectionSources,
  defaultMarkup,
  normaliseCollection,
  type CollectionSourceInfo,
  type CollectionValue,
} from './api'

/**
 * What a block shows from another section (`wx-collection`): which of its categories, how many,
 * whether with a filter, whether marked up for search engines.
 *
 * The records themselves are never here. The block keeps the choice, and the site reads the
 * records when it prints the page — so a question edited in the FAQ is edited on every page that
 * shows it, and nothing here goes stale.
 *
 * Which section is the schema's (`props.source`), not the value's: a block built for questions
 * does not turn into a block of reviews by an edit of its content. What that section is — its
 * name, where its categories answer, whether it can mark up — the panel asks the server once
 * (`GET /api/cms/collections`), and a section this administrator may not place is not offered.
 *
 * The markup switch is three-valued underneath. `null` is "as the rule says": on for the whole
 * collection, off for a part of it, because search engines ask not to mark the same question up on
 * several pages and "questions about payment" stands on every service. The switch shows what the
 * rule gives until somebody touches it, and after that it is theirs — with a way back to the rule.
 */
defineOptions({ name: 'WxCollectionField' })

const props = withDefaults(
  defineProps<{
    /** The source's key, as the server registered it: `faq`. */
    source: string
    disabled?: boolean
  }>(),
  { disabled: false },
)

const model = defineModel<CollectionValue | null>({ default: null })

const admin = useAdmin()
const t = useTranslate('webx-admin')
const id = useId()

/** `undefined` while the answer is on its way, `null` when it came and this source is not in it. */
const info = ref<CollectionSourceInfo | null | undefined>(undefined)
const categories = ref<SelectOption[]>([])

watch(
  () => props.source,
  async (source) => {
    info.value = undefined
    categories.value = []

    try {
      info.value = (await collectionSources(admin)).find((one) => one.key === source) ?? null
    } catch {
      info.value = null
    }

    if (!info.value?.categories) return

    try {
      const payload = await createCategoriesApi(admin, info.value.categories).list()
      categories.value = payload.data.map((row) => ({ label: row.name, value: row.id }))
    } catch {
      // The chosen ones still stand in the value; only the names are missing, and the list can be
      // opened again later. Losing the choice because a list did not arrive would be worse.
      categories.value = []
    }
  },
  { immediate: true },
)

const value = computed(() => normaliseCollection(model.value))

function write(patch: Partial<CollectionValue>): void {
  model.value = normaliseCollection({ ...value.value, ...patch })
}

/**
 * A chosen category that is not in the list any more — deleted, or in the bin — is still drawn,
 * by number: dropping it silently would change the block without anybody having touched it.
 */
const options = computed<SelectOption[]>(() => {
  const known = new Set(categories.value.map((one) => one.value))
  const missing = value.value.categories
    .filter((one) => !known.has(one))
    .map((one) => ({ label: `#${one}`, value: one }))

  return [...categories.value, ...missing]
})

function pick(picked: SelectModelValue): void {
  write({ categories: Array.isArray(picked) ? picked.map(Number) : [] })
}

function limit(picked: number | null | undefined): void {
  write({ limit: picked ?? null })
}

/*
 * "Only related to": which target, then which of its records. The target is chosen here rather
 * than fixed by the schema because a source can be related to more than one (recipes to services
 * and to other recipes), and with only one there is nothing to choose and the box is not drawn.
 * The target a choice was started in is kept while nothing of it is chosen yet — the value has no
 * way to say "a target and no records", and a box that jumped back would undo the click.
 */
const relatedType = ref<string | null>(null)

const relationOptions = computed<SelectOption[]>(() =>
  (info.value?.relations ?? []).map((one) => ({ label: one.title, value: one.key })),
)

const relatedTarget = computed(() => {
  const targets = info.value?.relations ?? []

  if (targets.length === 1) return targets[0]!

  const key = value.value.related?.type ?? relatedType.value

  return targets.find((one) => one.key === key) ?? null
})

function relateTo(picked: SelectModelValue): void {
  relatedType.value = typeof picked === 'string' ? picked : null
  write({ related: null })
}

function relate(ids: number[] | null): void {
  const type = relatedTarget.value?.key

  write({ related: type && ids && ids.length > 0 ? { type, ids } : null })
}

const markup = computed(() => value.value.markup ?? defaultMarkup(value.value))

function mark(on: unknown): void {
  write({ markup: on === true })
}

const markupHint = computed(() =>
  t(
    defaultMarkup(value.value)
      ? 'collections.field-markup-auto-on'
      : 'collections.field-markup-auto-off',
  ),
)
</script>

<template>
  <div class="wx-collection-field" :aria-busy="info === undefined">
    <p v-if="info === null" class="wx-collection-field__note">
      {{ t('collections.field-unavailable', { source: props.source }) }}
    </p>

    <template v-else-if="info">
      <p class="wx-collection-field__note">
        {{ t('collections.field-source', { source: info.title }) }}
      </p>

      <div v-if="info.categories" class="wx-collection-field__row">
        <label class="wx-collection-field__label" :for="`${id}-categories`">
          {{ t('collections.field-categories') }}
        </label>
        <!-- The placeholder is what an empty choice means, so it goes once there is a choice: the
             search box stays beside the chips, and "Payment · All categories" says two things. -->
        <wx-select
          :id="`${id}-categories`"
          :model-value="value.categories"
          :options="options"
          :placeholder="value.categories.length === 0 ? t('collections.field-all') : ''"
          :empty-text="t('collections.field-no-categories')"
          :disabled="props.disabled"
          multiple
          filterable
          clearable
          teleport
          @update:model-value="pick"
        />
      </div>

      <div v-if="info.relations.length > 0" class="wx-collection-field__row">
        <span class="wx-collection-field__label">
          {{
            info.relations.length === 1
              ? t('relations.collection-related-to', { target: info.relations[0]!.title })
              : t('relations.collection-related')
          }}
        </span>
        <wx-select
          v-if="info.relations.length > 1"
          :model-value="relatedTarget?.key ?? null"
          :options="relationOptions"
          :placeholder="t('relations.collection-related-type')"
          :aria-label="t('relations.collection-related-type')"
          :disabled="props.disabled"
          clearable
          teleport
          @update:model-value="relateTo"
        />
        <relations-field
          v-if="relatedTarget"
          :model-value="value.related?.ids ?? []"
          :target="relatedTarget.key"
          :sortable="false"
          :empty-text="t('relations.collection-related-any')"
          :disabled="props.disabled"
          @update:model-value="relate"
        />
      </div>

      <div class="wx-collection-field__row">
        <label class="wx-collection-field__label" :for="`${id}-limit`">
          {{ t('collections.field-limit') }}
        </label>
        <!-- Wrapped rather than given the class: a scoped rule on a core component's own element
             does not reach it when its root is a fragment. -->
        <div class="wx-collection-field__limit">
          <wx-input-number
            :id="`${id}-limit`"
            :model-value="value.limit"
            :min="1"
            :max="COLLECTION_MAX_LIMIT"
            :step="1"
            :precision="0"
            :placeholder="t('collections.field-limit-all')"
            :disabled="props.disabled"
            @update:model-value="limit"
          />
        </div>
      </div>

      <wx-switch
        v-if="info.categories"
        :model-value="value.filter"
        :label="t('collections.field-filter')"
        :disabled="props.disabled"
        @update:model-value="write({ filter: $event === true })"
      />

      <div v-if="info.markup" class="wx-collection-field__markup">
        <wx-switch
          :model-value="markup"
          :label="t('collections.field-markup')"
          :disabled="props.disabled"
          @update:model-value="mark"
        />
        <p class="wx-collection-field__hint">{{ markupHint }}</p>
        <wx-button
          v-if="value.markup !== null"
          class="wx-collection-field__reset"
          variant="text"
          size="sm"
          :disabled="props.disabled"
          @click="write({ markup: null })"
        >
          {{ t('collections.field-markup-reset') }}
        </wx-button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.wx-collection-field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-collection-field__row {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-collection-field__label {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

/* A number of up to three digits: the whole line would promise room for a sentence. */
.wx-collection-field__limit {
  max-width: 160px;
}

.wx-collection-field__markup {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}

/* Its own width, at the start of the column: stretched, a text button reads as a bar. */
.wx-collection-field__reset {
  align-self: flex-start;
}

.wx-collection-field__note,
.wx-collection-field__hint {
  margin: 0;
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}
</style>
