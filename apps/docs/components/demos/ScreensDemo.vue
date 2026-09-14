<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { provideLocales } from '@webx-ui/core'
import {
  WxScreenRenderer,
  validatePatch,
  validateScreen,
  type Patch,
  type PatchError,
  type ScreenModel,
  type ScreenNode,
} from '@webx-ui/schema'

/** The tree a settings module would ship — the example from the specification. */
const treeText = ref(
  JSON.stringify(
    [
      {
        id: 'tabs',
        type: 'wx-tabs',
        children: [
          {
            id: 'general',
            type: 'wx-tab',
            label: 'trans::webx-settings::screen.general',
            children: [
              {
                id: 'main',
                type: 'wx-card',
                children: [
                  {
                    id: 'project-name',
                    type: 'wx-input',
                    name: 'general.project-name',
                    label: 'trans::webx-settings::screen.project-name',
                    localized: true,
                  },
                  {
                    id: 'project-logo',
                    type: 'wx-media',
                    name: 'branding.project-logo',
                    label: 'trans::webx-settings::screen.logo',
                    props: { aspect: '1/1', accept: 'image/*' },
                  },
                ],
              },
            ],
          },
          {
            id: 'seo',
            type: 'wx-tab',
            label: 'trans::webx-settings::screen.seo',
            children: [
              {
                id: 'seo-card',
                type: 'wx-card',
                children: [
                  {
                    id: 'seo-row',
                    type: 'wx-row',
                    props: { gutter: 16 },
                    children: [
                      {
                        id: 'og-col',
                        type: 'wx-col',
                        props: { span: 24, md: 8 },
                        children: [
                          {
                            id: 'default-og',
                            type: 'wx-input',
                            name: 'seo.default-og',
                            label: 'trans::webx-settings::screen.default-og',
                          },
                        ],
                      },
                      {
                        id: 'robots-col',
                        type: 'wx-col',
                        props: { span: 24, md: 16 },
                        children: [
                          {
                            id: 'robots',
                            type: 'wx-textarea',
                            name: 'seo.robots-txt',
                            label: 'trans::webx-settings::screen.robots',
                            props: { rows: 6 },
                          },
                        ],
                      },
                    ],
                  },
                ],
              },
            ],
          },
        ],
      },
    ],
    null,
    2,
  ),
)

/** What a project lays over it, without touching the module. */
const patchText = ref(
  JSON.stringify(
    [
      { op: 'remove', target: 'project-logo' },
      { op: 'set', target: 'robots', props: { rows: 10 }, label: 'robots.txt' },
      {
        op: 'add',
        target: 'tabs',
        position: 'after:general',
        node: {
          id: 'contacts',
          type: 'wx-tab',
          label: 'Contacts',
          children: [
            {
              id: 'contacts-card',
              type: 'wx-card',
              children: [
                { id: 'phone', type: 'wx-input', name: 'contacts.phone', label: 'Phone' },
                {
                  id: 'email',
                  type: 'wx-input',
                  name: 'contacts.email',
                  label: 'Email',
                  props: { type: 'email' },
                },
                {
                  id: 'show-map',
                  type: 'wx-switch',
                  name: 'contacts.show-map',
                  label: 'Show the office on a map',
                },
                {
                  id: 'map-address',
                  type: 'wx-input',
                  name: 'contacts.map-address',
                  label: 'Address for the map',
                  visible: { when: 'contacts.show-map', is: true },
                },
              ],
            },
          ],
        },
      },
      { op: 'move', target: 'seo', position: 'first' },
    ],
    null,
    2,
  ),
)

const dictionary: Record<string, string> = {
  'webx-settings::screen.general': 'General',
  'webx-settings::screen.project-name': 'Project name',
  'webx-settings::screen.logo': 'Logo',
  'webx-settings::screen.seo': 'SEO',
  'webx-settings::screen.default-og': 'Default OG image',
  'webx-settings::screen.robots': 'robots.txt',
}
const translate = (key: string) => dictionary[key] ?? key

/** The site's content languages: the localized field gets its selector from these. */
provideLocales(computed(() => [{ code: 'en' }, { code: 'uk' }]))

const model = ref<ScreenModel>({
  'general.project-name': { en: 'Acme', uk: 'Акме' },
  'seo.robots-txt': 'User-agent: *\nDisallow: /cms',
})

function parse(text: string): { value: unknown; error: string | null } {
  try {
    return { value: JSON.parse(text), error: null }
  } catch (error) {
    return { value: null, error: (error as Error).message }
  }
}

const tree = computed(() => parse(treeText.value))
const patch = computed(() => parse(patchText.value))
const patchErrors = ref<PatchError[]>([])

const problems = computed(() => {
  const list: string[] = []
  if (tree.value.error) list.push(`Tree: ${tree.value.error}`)
  else for (const e of validateScreen(tree.value.value)) list.push(`Tree ${e.path}: ${e.message}`)
  if (patch.value.error) list.push(`Patch: ${patch.value.error}`)
  else for (const e of validatePatch(patch.value.value)) list.push(`${e.path}: ${e.message}`)
  for (const e of patchErrors.value) list.push(`patch[${e.index}] ${e.op.op}: ${e.message}`)
  return list
})

/** The last tree that parsed and validated stays on screen while the next one is being typed. */
const current = ref<{ root: ScreenNode[]; patch: Patch }>({ root: [], patch: [] })
watch(
  [tree, patch],
  ([nextTree, nextPatch]) => {
    const rootOk = !nextTree.error && validateScreen(nextTree.value).length === 0
    const patchOk = !nextPatch.error && validatePatch(nextPatch.value).length === 0
    if (rootOk && patchOk) {
      current.value = { root: nextTree.value as ScreenNode[], patch: nextPatch.value as Patch }
    }
  },
  { immediate: true },
)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-row :gutter="12">
      <wx-col :span="24" :md="12">
        <span class="wx-demo__label">The module's tree</span>
        <wx-code-editor v-model="treeText" language="json" max-height="360px" aria-label="Tree" />
      </wx-col>
      <wx-col :span="24" :md="12">
        <span class="wx-demo__label">The project's patch</span>
        <wx-code-editor v-model="patchText" language="json" max-height="360px" aria-label="Patch" />
      </wx-col>
    </wx-row>

    <wx-alert v-if="problems.length" type="danger" :title="`${problems.length} problem(s)`">
      <ul style="margin: 0; padding-left: 18px">
        <li v-for="problem in problems" :key="problem">{{ problem }}</li>
      </ul>
    </wx-alert>

    <span class="wx-demo__label">What the panel draws</span>
    <wx-screen-renderer
      v-model="model"
      :root="current.root"
      :patch="current.patch"
      :translate="translate"
      @patch-error="patchErrors = $event"
    />

    <details>
      <summary style="cursor: pointer; color: var(--wx-text-muted); font-size: 13px">
        The model
      </summary>
      <pre
        style="
          margin: 8px 0 0;
          padding: 12px;
          overflow-x: auto;
          background: var(--wx-bg-fill);
          border-radius: 8px;
          font-size: 12px;
        "
        >{{ JSON.stringify(model, null, 2) }}</pre>
    </details>
  </div>
</template>
