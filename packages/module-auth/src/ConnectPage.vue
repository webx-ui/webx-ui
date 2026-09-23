<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAdmin, useTranslate, WxListScreen } from '@webx-ui/module-admin'
import {
  toast,
  WxAlert,
  WxButton,
  WxCard,
  WxHeading,
  WxIcon,
  WxTab,
  WxTabs,
  WxText,
} from '@webx-ui/core'
import ConnectionList from './ConnectionList.vue'
import { useAuthMessages } from './i18n'

/**
 * How a person connects their own agent, on one page.
 *
 * It exists because the alternative is explaining it — over a call, to somebody who has never
 * heard of MCP and has no reason to. So the page is written for that call: the address large
 * enough to read out, a button that copies it, and three steps for each client, in the order
 * they appear on that client's screen.
 *
 * The address carries no secret, which is the whole point of the OAuth path (§2.3 of the
 * spec): it can be printed in a letter, read aloud, or left on this page for anybody signed
 * in. What a person has to do themselves is sign in, and that happens on our own screen.
 *
 * Under it are their own connections, because the first thing somebody does after connecting
 * is look for proof that it worked — and the second, weeks later, is come back to end it.
 */
const admin = useAdmin()
useAuthMessages()

const t = useTranslate('webx-auth')

const copied = ref(false)
let copiedTimer: ReturnType<typeof setTimeout> | undefined

/**
 * The address agents connect to, as the server printed it — absolute, because it is pasted
 * into a program on another machine.
 */
const url = computed<string>(() => {
  const meta = admin.state.manifest?.modules.find((module) => module.id === 'connect')?.meta

  return typeof meta?.url === 'string' ? meta.url : ''
})

/**
 * What the server is called in the client's own list of servers.
 *
 * The site's own name, taken off the address: somebody with three sites connected reads this
 * word and nothing else, and "webx" three times over would be three servers they cannot tell
 * apart.
 */
const name = computed<string>(() => {
  try {
    return new URL(url.value).hostname.split('.')[0] || 'webx'
  } catch {
    return 'webx'
  }
})

const command = computed(() => `claude mcp add --transport http ${name.value} ${url.value}`)

const toml = computed(() => `[mcp_servers.${name.value}]\nurl = "${url.value}"`)

/**
 * The links Cursor and VS Code take a whole server from. Both are documented shapes, and both
 * carry only the address — there is nothing secret to leak into a link.
 */
const cursorLink = computed(() => {
  const config = encode(JSON.stringify({ url: url.value }))

  return `cursor://anysphere.cursor-deeplink/mcp/install?name=${encodeURIComponent(name.value)}&config=${config}`
})

const vscodeLink = computed(() => {
  const config = JSON.stringify({ name: name.value, type: 'http', url: url.value })

  return `vscode:mcp/install?${encodeURIComponent(config)}`
})

/** Base64 of a UTF-8 string: `btoa` alone throws on anything above ASCII. */
function encode(value: string): string {
  const bytes = new TextEncoder().encode(value)
  let binary = ''

  for (const byte of bytes) binary += String.fromCharCode(byte)

  return encodeURIComponent(btoa(binary))
}

/**
 * Copying, with the old way behind it.
 *
 * The clipboard API is missing outside a secure context and refused inside some frames, and
 * this page is exactly the page where "it did not work and said nothing" is worst: the
 * address is the one thing the person came here for.
 */
async function copy(value: string): Promise<void> {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(value)
    } else if (!byHand(value)) {
      throw new Error('no clipboard')
    }

    copied.value = true
    clearTimeout(copiedTimer)
    copiedTimer = setTimeout(() => (copied.value = false), 2000)
  } catch {
    if (byHand(value)) return

    toast.danger(t('connect.copy-failed'))
  }
}

function byHand(value: string): boolean {
  const field = document.createElement('textarea')

  field.value = value
  field.setAttribute('readonly', '')
  field.style.position = 'fixed'
  field.style.opacity = '0'

  document.body.append(field)
  field.select()

  try {
    return document.execCommand('copy')
  } catch {
    return false
  } finally {
    field.remove()
  }
}
</script>

<template>
  <wx-list-screen :title="t('connect.title')" :card="false">
    <div class="wx-connect">
      <wx-text class="wx-connect__lead">{{ t('connect.lead') }}</wx-text>

      <!-- The address, large. Everything else on this page is about what to do with it. -->
      <wx-card bordered>
        <wx-text size="xs" tone="muted" class="wx-connect__label">{{
          t('connect.address')
        }}</wx-text>

        <div class="wx-connect__row">
          <code class="wx-connect__url">{{ url }}</code>

          <wx-button type="primary" :disabled="url === ''" @click="copy(url)">
            <template #icon><wx-icon :name="copied ? 'check' : 'copy'" /></template>
            {{ copied ? t('connect.copied') : t('connect.copy') }}
          </wx-button>
        </div>
      </wx-card>

      <section class="wx-connect__section">
        <wx-heading :level="2" size="sm">{{ t('connect.how') }}</wx-heading>

        <wx-tabs variant="line" :aria-label="t('connect.how')">
          <wx-tab value="claude" :label="t('connect.claude-title')">
            <ol class="wx-connect__steps">
              <li>{{ t('connect.claude-1') }}</li>
              <li>{{ t('connect.claude-2') }}</li>
              <li>{{ t('connect.claude-3') }}</li>
            </ol>
          </wx-tab>

          <wx-tab value="chatgpt" :label="t('connect.chatgpt-title')">
            <ol class="wx-connect__steps">
              <li>{{ t('connect.chatgpt-1') }}</li>
              <li>{{ t('connect.chatgpt-2') }}</li>
              <li>{{ t('connect.chatgpt-3') }}</li>
            </ol>
          </wx-tab>

          <wx-tab value="claude-code" :label="t('connect.claude-code-title')">
            <wx-text size="sm">{{ t('connect.claude-code-hint') }}</wx-text>

            <div class="wx-connect__snippet">
              <pre>{{ command }}</pre>
              <wx-button variant="text" size="sm" @click="copy(command)">
                <template #icon><wx-icon name="copy" /></template>
                {{ t('connect.copy-line') }}
              </wx-button>
            </div>
          </wx-tab>

          <wx-tab value="codex" :label="t('connect.codex-title')">
            <wx-text size="sm">{{ t('connect.codex-hint') }}</wx-text>

            <div class="wx-connect__snippet">
              <pre>{{ toml }}</pre>
              <wx-button variant="text" size="sm" @click="copy(toml)">
                <template #icon><wx-icon name="copy" /></template>
                {{ t('connect.copy-line') }}
              </wx-button>
            </div>
          </wx-tab>
        </wx-tabs>
      </section>

      <section class="wx-connect__section">
        <wx-heading :level="2" size="sm">{{ t('connect.one-click') }}</wx-heading>
        <wx-text size="sm" tone="muted">{{ t('connect.one-click-hint') }}</wx-text>

        <div class="wx-connect__links">
          <wx-button
            :href="cursorLink"
            variant="outline"
            :disabled="url === ''"
            class="wx-connect__link"
          >
            <template #icon><wx-icon name="external-link" /></template>
            {{ t('connect.install-in', { client: t('connect.cursor-title') }) }}
          </wx-button>

          <wx-button
            :href="vscodeLink"
            variant="outline"
            :disabled="url === ''"
            class="wx-connect__link"
          >
            <template #icon><wx-icon name="external-link" /></template>
            {{ t('connect.install-in', { client: t('connect.vscode-title') }) }}
          </wx-button>
        </div>
      </section>

      <wx-alert type="info" :title="t('connect.next')">
        <ol class="wx-connect__steps">
          <li>{{ t('connect.next-1') }}</li>
          <li>{{ t('connect.next-2') }}</li>
          <li>{{ t('connect.next-3') }}</li>
        </ol>
      </wx-alert>

      <section class="wx-connect__section">
        <wx-heading :level="2" size="sm">{{ t('connect.mine') }}</wx-heading>

        <wx-card padding="none">
          <connection-list scope="mine" />
        </wx-card>
      </section>
    </div>
  </wx-list-screen>
</template>

<style scoped>
.wx-connect {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-24);
  min-width: 0;
}

.wx-connect__lead {
  max-width: 68ch;
}

.wx-connect__label {
  display: block;
  margin-bottom: var(--wx-space-8);
}

/* The address and its button on one line, and under each other as soon as they do not fit:
   the address is long, and shrinking it is what would make it unreadable. */
.wx-connect__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-12);
}

.wx-connect__url {
  flex: 1 1 260px;
  min-width: 0;
  padding: var(--wx-space-10) var(--wx-space-12);
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-subtle);
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-md);
  /* Broken anywhere rather than pushed off the card: it is read, not clicked. */
  word-break: break-all;
}

.wx-connect__section {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-connect__steps {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  margin: 0;
  padding-left: var(--wx-space-24);
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-relaxed);
}

.wx-connect__snippet {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12);
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-subtle);
}

.wx-connect__snippet pre {
  flex: 1 1 240px;
  margin: 0;
  min-width: 0;
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  line-height: var(--wx-font-line-height-relaxed);
  white-space: pre-wrap;
  word-break: break-all;
}

.wx-connect__links {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-12);
}
</style>
