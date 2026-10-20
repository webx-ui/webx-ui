import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'
import { localesKey, type CodeEditorCompleter } from '@webx-ui/core'
import { createI18n, i18nKey } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import { provideChannel, type ChannelContext } from './context'
import { fillOnEvent } from './defaults'
import TemplateField from './TemplateField.vue'
import type { NotificationsMeta } from './types'

const { confirm } = vi.hoisted(() => ({ confirm: vi.fn() }))

vi.mock('@webx-ui/core', async (original) => ({
  ...(await original<Record<string, unknown>>()),
  confirm,
}))

/** What of CodeMirror's view the tests touch: this package does not depend on it. */
interface EditorView {
  state: { doc: { toString(): string } }
  contentDOM: HTMLElement
  dispatch(spec: { selection: { anchor: number } }): void
}

const META: NotificationsMeta = {
  environment: 'production',
  mode: 'queue',
  providers: [
    {
      key: 'email',
      label: 'E-mail',
      icon: 'mail',
      fields: [],
      subject: true,
      address: 'email',
      limit: null,
      find_recipients: false,
    },
  ],
  events: [
    {
      key: 'shop.order',
      label: 'An order',
      module: 'shop',
      variables: [
        { name: 'number', label: 'Number', example: '42', address: null, fields: [] },
        { name: 'total', label: 'Total', example: '9.00', address: null, fields: [] },
      ],
      sample: {
        email: {
          subject: { en: 'Order {{number}}', ru: 'Заказ {{number}}' },
          template: { en: '<p>Order {{number}}</p>', ru: '<p>Заказ {{number}}</p>' },
        },
      },
    },
    { key: 'shop.quiet', label: 'Nothing to say', module: 'shop', variables: [], sample: {} },
  ],
}

/** The message card of a channel's form: the subject over the template, as the screen lays them. */
function form(values: ScreenModel) {
  let context: ChannelContext | null = null

  const Host = defineComponent({
    setup() {
      const model = ref<ScreenModel>(values)

      context = provideChannel(ref(META), model, ref(null))

      return () =>
        h('div', [
          h(TemplateField, {
            class: 'subject',
            localized: true,
            singleLine: true,
            part: 'subject',
            chips: false,
            modelValue: model.value.subject as Record<string, string>,
            'onUpdate:modelValue': (subject: unknown) =>
              (model.value = { ...model.value, subject }),
          }),
          h(TemplateField, {
            class: 'template',
            localized: true,
            modelValue: model.value.template as Record<string, string>,
            'onUpdate:modelValue': (template: unknown) =>
              (model.value = { ...model.value, template }),
          }),
        ])
    },
  })

  const i18n = createI18n()
  const wrapper = mount(Host, {
    attachTo: document.body,
    global: {
      provide: {
        [i18nKey as symbol]: i18n,
        [localesKey as symbol]: { list: ref([{ code: 'en' }, { code: 'ru' }]), active: ref('en') },
      },
    },
  })

  return { wrapper, context: () => context as unknown as ChannelContext }
}

/** The editor is loaded on demand: wait until both have mounted CodeMirror. */
async function loaded(wrapper: ReturnType<typeof mount>): Promise<void> {
  await vi.waitFor(() => expect(wrapper.findAll('.cm-editor')).toHaveLength(2))
}

/**
 * The editors themselves, the subject first: the lazy wrapper that loads one is named the same, so
 * it is the one that exposes a view.
 */
function editors(wrapper: ReturnType<typeof mount>) {
  return wrapper
    .findAllComponents({ name: 'WxCodeEditor' })
    .filter((one) => (one.vm as unknown as { view?: unknown }).view !== undefined)
}

function viewIn(wrapper: ReturnType<typeof mount>, part: string): EditorView {
  const editor = editors(wrapper)[part === 'subject' ? 0 : 1]
  const exposed = (editor?.vm as unknown as { view: EditorView | { value: EditorView } }).view

  return 'state' in exposed ? exposed : exposed.value
}

describe('WxNotificationsTemplateField', () => {
  it('suggests the variables of the event in the one-line subject', async () => {
    const { wrapper } = form({
      provider: 'email',
      event: 'shop.order',
      subject: { en: '{{' },
      template: {},
    })
    await loaded(wrapper)

    const editor = editors(wrapper)[0]!

    expect(wrapper.find('.subject .wx-code-editor').classes()).toContain('is-single-line')
    expect(wrapper.find('.subject .wx-notifications-template__variables').exists()).toBe(false)

    // The list the template opens after {{ — the event's variables, with their examples.
    const completer = editor.props('completions') as CodeEditorCompleter
    const found = completer({ text: 'Order {{', pos: 8, explicit: false })

    expect(found?.options.map((one) => one.label)).toEqual(
      expect.arrayContaining(['number', 'total']),
    )
    expect(found?.options.find((one) => one.label === 'number')?.detail).toContain('42')
    wrapper.unmount()
  })

  it('puts a clicked variable into the field the caret was in last', async () => {
    const { wrapper, context } = form({
      provider: 'email',
      event: 'shop.order',
      subject: { en: 'Order ' },
      template: { en: 'Total: ' },
    })
    await loaded(wrapper)

    const chip = () =>
      wrapper.findAll('.wx-notifications-template__chip').find((one) => one.text() === 'number')!

    // Nothing focused yet: the template.
    viewIn(wrapper, 'template').dispatch({ selection: { anchor: 7 } })
    await chip().trigger('click')
    expect(context().values.value.template).toEqual({ en: 'Total: {{number}}' })

    const subject = viewIn(wrapper, 'subject')
    subject.dispatch({ selection: { anchor: 6 } })
    subject.contentDOM.dispatchEvent(new FocusEvent('focus'))
    await chip().trigger('click')

    expect(context().values.value.subject).toEqual({ en: 'Order {{number}}' })
    expect(context().values.value.template).toEqual({ en: 'Total: {{number}}' })
    wrapper.unmount()
  })

  it('inserts the default template at once into an empty message, and asks before replacing one', async () => {
    const { wrapper, context } = form({
      provider: 'email',
      event: 'shop.order',
      subject: { en: '' },
      template: { en: '' },
    })
    await loaded(wrapper)

    const button = () => wrapper.find('.template .wx-notifications-template__default button')

    await button().trigger('click')
    await flushPromises()

    expect(confirm).not.toHaveBeenCalled()
    expect(context().values.value.template).toEqual({
      en: '<p>Order {{number}}</p>',
      ru: '<p>Заказ {{number}}</p>',
    })
    expect(context().values.value.subject).toEqual({
      en: 'Order {{number}}',
      ru: 'Заказ {{number}}',
    })

    context().values.value = { ...context().values.value, template: { en: 'Mine' } }
    confirm.mockResolvedValueOnce(false)
    await button().trigger('click')
    await flushPromises()

    expect(confirm).toHaveBeenCalledTimes(1)
    expect(context().values.value.template).toEqual({ en: 'Mine' })

    confirm.mockResolvedValueOnce(true)
    await button().trigger('click')
    await flushPromises()

    expect(context().values.value.template).toEqual({
      en: '<p>Order {{number}}</p>',
      ru: '<p>Заказ {{number}}</p>',
    })

    // An event without one for the provider has no button.
    context().values.value = { ...context().values.value, event: 'shop.quiet' }
    await nextTick()
    expect(button().exists()).toBe(false)
    wrapper.unmount()
  })
})

describe('fillOnEvent', () => {
  it('fills an empty message when an event is chosen, and leaves a written one alone', () => {
    const values = ref<ScreenModel>({ provider: 'email', event: null, subject: {}, template: {} })
    const busy = ref(false)
    const filled = vi.fn()
    let context: ChannelContext | null = null

    mount(
      defineComponent({
        setup() {
          context = provideChannel(ref(META), values, ref(null))
          fillOnEvent(context, busy, filled)

          return () => h('div')
        },
      }),
    )

    values.value = { ...values.value, event: 'shop.order' }

    expect(filled).toHaveBeenCalledTimes(1)
    expect(values.value.template).toEqual({
      en: '<p>Order {{number}}</p>',
      ru: '<p>Заказ {{number}}</p>',
    })

    // Written: the event changes, the text stays.
    values.value = { ...values.value, event: null, template: { en: 'Mine' } }
    values.value = { ...values.value, event: 'shop.order' }
    expect(values.value.template).toEqual({ en: 'Mine' })

    // What the page puts in while it loads is not a choice.
    busy.value = true
    values.value = { provider: 'email', event: 'shop.order', subject: {}, template: {} }
    expect(values.value.template).toEqual({})
    expect(filled).toHaveBeenCalledTimes(1)
    expect(context).not.toBeNull()
  })
})
