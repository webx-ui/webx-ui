import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { localesKey, WebxUI } from '@webx-ui/core'
import { adminKey, createI18n, i18nKey, type AdminContext } from '@webx-ui/module-admin'
import FieldDialog from './FieldDialog.vue'
import type { InboxField, InboxForm } from './types'

const form = { id: 1, slug: 'contact', title: { en: 'Contact' } } as unknown as InboxForm

const phone: InboxField = {
  id: 5,
  form_id: 1,
  name: 'phone',
  key: 'phone',
  type: 'tel',
  title: { en: 'Phone' },
  placeholder: {},
  help: {},
  options: {},
  is_enabled: true,
  is_required: false,
  is_fullsize: true,
  in_table: false,
  position: 0,
}

function dialog(
  field: InboxField | null,
  taken: string[] = [],
  fields: InboxField[] = [],
  extra: Partial<AdminContext> = {},
  more: Record<string, unknown> = {},
) {
  const post = vi
    .fn()
    .mockImplementation((_url: string, body: object) =>
      Promise.resolve({ data: { ...phone, ...body } }),
    )
  const put = vi.fn().mockResolvedValue({ data: phone })
  const i18n = createI18n()

  const admin = {
    apiPath: '/api/cms',
    http: { get: vi.fn(), post, put },
    i18n,
    state: { manifest: null, user: null, status: 'ready', error: null },
    can: () => true,
    ...extra,
  } as unknown as AdminContext

  const wrapper = mount(FieldDialog, {
    props: { field, form, taken, fields, ...more },
    attachTo: document.body,
    global: {
      plugins: [WebxUI],
      provide: {
        [adminKey as symbol]: admin,
        [i18nKey as symbol]: i18n,
        [localesKey as symbol]: { list: ref([{ code: 'ru' }, { code: 'en' }]), active: ref('ru') },
      },
    },
  })

  /** The control inside the form item with this label. */
  const control = (label: string) => {
    const item = wrapper
      .findAllComponents({ name: 'WxFormItem' })
      .find((one) => one.props('label') === label)

    if (item === undefined) throw new Error(`No field labelled ${label}`)

    return item.findComponent({ name: 'WxInput' })
  }

  const labels = () =>
    wrapper
      .findAllComponents({ name: 'WxFormItem' })
      .map((one) => one.props('label'))
      .filter((one): one is string => typeof one === 'string')

  return { wrapper, post, put, control, labels }
}

describe('WxInboxFieldDialog', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('asks for the type, then the label, and keeps the name for last, under “Advanced”', async () => {
    const { labels } = dialog(null)
    await flushPromises()

    const order = labels()
    expect(order.slice(0, 2)).toEqual(['Type', 'Label'])
    expect(order.at(-1)).toBe('Name')
    expect(document.body.textContent).toContain('Advanced')
    // The name is explained by what it is for, not by the row number a nameless field answers to.
    expect(document.body.textContent).not.toMatch(/\bf\d+\b/)
  })

  it('names a new field after its label until somebody writes a name', async () => {
    const { control, post, wrapper } = dialog(null, ['vash_telefon'])
    await flushPromises()

    control('Label').vm.$emit('update:modelValue', { ru: 'Ваш телефон' })
    await flushPromises()

    // Taken by another field of the form: the next one along.
    expect(control('Name').props('modelValue')).toBe('vash_telefon_2')
    expect(document.body.textContent).toContain('{{field.vash_telefon_2}}')

    // Transliterated by the label's language, as the server does it: Russian «Щука» is `schuka`,
    // not the `shchuka` of a table that knows no language.
    control('Label').vm.$emit('update:modelValue', { ru: 'Щука' })
    await flushPromises()

    expect(control('Name').props('modelValue')).toBe('schuka')

    control('Name').vm.$emit('update:modelValue', 'mobile')
    control('Label').vm.$emit('update:modelValue', { ru: 'Мобильный' })
    await flushPromises()

    expect(control('Name').props('modelValue')).toBe('mobile')

    await wrapper
      .findAllComponents({ name: 'WxButton' })
      .find((one) => one.text() === 'Save')!
      .trigger('click')
    await flushPromises()

    expect(post).toHaveBeenCalledWith(
      '/api/cms/inbox/forms/1/fields',
      expect.objectContaining({ name: 'mobile' }),
    )
  })

  it('leaves the name of a field that has one, and warns before it is changed', async () => {
    const { control } = dialog(phone)
    await flushPromises()

    control('Label').vm.$emit('update:modelValue', { en: 'Your phone' })
    await flushPromises()
    expect(control('Name').props('modelValue')).toBe('phone')
    expect(document.querySelector('.wx-alert')).toBeNull()

    control('Name').vm.$emit('update:modelValue', 'mobile')
    await flushPromises()

    const warning = document.querySelector('.wx-alert')?.textContent ?? ''
    expect(warning).toContain('{{field.phone}}')
    expect(warning).toContain('{{field.mobile}}')
  })

  describe('Show (FORM_STEPS §9.2)', () => {
    const make = (patch: Partial<InboxField> & Pick<InboxField, 'id' | 'key' | 'type'>) =>
      ({ ...phone, name: patch.key, title: { en: patch.key }, options: {}, ...patch }) as InboxField

    const purpose = make({
      id: 1,
      key: 'purpose',
      type: 'radio',
      title: { en: 'Purpose' },
      options: {
        choices: [
          { value: 'agency', label: { en: 'Agency meeting' } },
          { value: 'question', label: { en: 'Just a question' } },
        ],
      },
    })
    const note = make({ id: 2, key: 'note', type: 'textarea' })
    const agent = make({ id: 3, key: 'agent', type: 'consent', title: { en: 'Has an agent' } })
    const agency = make({ id: 4, key: 'agency', type: 'text', title: { en: 'Agency' } })
    const later = make({ id: 5, key: 'later', type: 'select', options: { choices: [] } })
    const all = [purpose, note, agent, agency, later]

    const section = () => document.querySelector('.wx-inbox-condition')
    const selects = (wrapper: ReturnType<typeof dialog>['wrapper']) =>
      wrapper.findComponent({ name: 'FieldCondition' }).findAllComponents({ name: 'WxSelect' })
    const segmented = (wrapper: ReturnType<typeof dialog>['wrapper']) =>
      wrapper.findComponent({ name: 'FieldCondition' }).findComponent({ name: 'WxSegmented' })
    const save = async (wrapper: ReturnType<typeof dialog>['wrapper']) => {
      await wrapper
        .findAllComponents({ name: 'WxButton' })
        .find((one) => one.text() === 'Save')!
        .trigger('click')
      await flushPromises()
    }

    it('offers only the choice fields above, and writes “if” as options.when', async () => {
      const { wrapper, put } = dialog(agency, [], all)
      await flushPromises()

      expect(section()?.textContent).toContain('Show')
      segmented(wrapper).vm.$emit('update:modelValue', 'when')
      await flushPromises()

      // A text above and a choice below are not offered: one cannot count on them.
      const [field, values] = selects(wrapper)
      expect(field!.props('options')).toEqual([
        { value: 'purpose', label: 'Purpose' },
        { value: 'agent', label: 'Has an agent' },
      ])
      expect(field!.props('modelValue')).toBe('purpose')
      expect(values!.props('options')).toEqual([
        { value: 'agency', label: 'Agency meeting' },
        { value: 'question', label: 'Just a question' },
      ])

      values!.vm.$emit('update:modelValue', ['agency'])
      await flushPromises()
      await save(wrapper)

      expect(put).toHaveBeenCalledWith(
        '/api/cms/inbox/fields/4',
        expect.objectContaining({ options: { when: { purpose: ['agency'] } } }),
      )
    })

    it('“and” adds the next field; a consent counts on its tick; “unless” and “always” rewrite it', async () => {
      const { wrapper, put } = dialog(agency, [], all)
      await flushPromises()

      segmented(wrapper).vm.$emit('update:modelValue', 'unless')
      await flushPromises()
      selects(wrapper)[1]!.vm.$emit('update:modelValue', ['question'])
      await wrapper
        .findAllComponents({ name: 'WxButton' })
        .find((one) => one.text() === 'One more condition')!
        .trigger('click')
      await flushPromises()

      const tick = selects(wrapper)[3]!
      expect(selects(wrapper)[2]!.props('modelValue')).toBe('agent')
      expect(tick.props('options')).toEqual([{ value: '1', label: 'Ticked' }])
      tick.vm.$emit('update:modelValue', ['1'])
      await flushPromises()
      await save(wrapper)

      expect(put).toHaveBeenLastCalledWith(
        '/api/cms/inbox/fields/4',
        expect.objectContaining({
          options: { unless: { purpose: ['question'], agent: ['1'] } },
        }),
      )

      segmented(wrapper).vm.$emit('update:modelValue', 'always')
      await flushPromises()
      await save(wrapper)

      expect(put).toHaveBeenLastCalledWith(
        '/api/cms/inbox/fields/4',
        expect.objectContaining({ options: {} }),
      )
    })

    it('shows an answer the field above no longer has in red, and keeps it until it is closed', async () => {
      const broken = { ...agency, options: { when: { purpose: ['agency', 'tender'] } } }
      const { wrapper, put } = dialog(broken, [], all)
      await flushPromises()

      expect(selects(wrapper)[1]!.props('modelValue')).toEqual(['agency'])
      const badge = wrapper
        .findAllComponents({ name: 'WxBadge' })
        .find((one) => one.text().includes('tender'))
      expect(badge?.props('type')).toBe('danger')

      // Picking another answer does not drop it quietly: the server is the one to say no.
      selects(wrapper)[1]!.vm.$emit('update:modelValue', ['agency', 'question'])
      await flushPromises()
      await save(wrapper)
      expect(put).toHaveBeenLastCalledWith(
        '/api/cms/inbox/fields/4',
        expect.objectContaining({
          options: { when: { purpose: ['tender', 'agency', 'question'] } },
        }),
      )

      badge!.vm.$emit('close', new MouseEvent('click'))
      await flushPromises()
      await save(wrapper)
      expect(put).toHaveBeenLastCalledWith(
        '/api/cms/inbox/fields/4',
        expect.objectContaining({ options: { when: { purpose: ['agency', 'question'] } } }),
      )
    })

    it('marks a field that is not above any more, and says what the server refused', async () => {
      const moved = { ...agency, options: { when: { later: ['x'] } } }
      const { wrapper, put } = dialog(moved, [], all)
      put.mockRejectedValueOnce({
        status: 422,
        body: { errors: { 'options.when': ['The condition names “later”, which stands below.'] } },
      })
      await flushPromises()

      expect(selects(wrapper)[0]!.props('status')).toBe('error')
      expect(section()?.textContent).toContain('no longer a choice field above')

      await save(wrapper)
      expect(section()?.querySelector('[role=alert]')?.textContent).toContain('stands below')
    })

    it('keeps the other key of a field saved with both, and says it', async () => {
      const both = {
        ...agency,
        options: { when: { purpose: ['agency'] }, unless: { agent: ['1'] } },
      }
      const { wrapper, put } = dialog(both, [], all)
      await flushPromises()

      expect(section()?.textContent).toContain('Also hidden if Has an agent = Ticked')
      await save(wrapper)
      expect(put).toHaveBeenLastCalledWith(
        '/api/cms/inbox/fields/4',
        expect.objectContaining({
          options: { when: { purpose: ['agency'] }, unless: { agent: ['1'] } },
        }),
      )
    })

    it('says there is nothing to count on for the first field, and has no section for a hidden one', async () => {
      const { wrapper } = dialog(purpose, [], all)
      await flushPromises()

      expect(section()?.textContent).toContain('there is none yet')

      wrapper.findComponent({ name: 'WxSelect' }).vm.$emit('update:modelValue', 'hidden')
      await flushPromises()
      expect(section()).toBeNull()
    })
  })

  describe('cards (FORM_STEPS §5, §9.2)', () => {
    const purpose: InboxField = {
      ...phone,
      id: 7,
      name: 'purpose',
      key: 'purpose',
      type: 'radio',
      title: { en: 'Purpose' },
      options: {
        choices: [
          { value: 'viewing', label: { en: 'Viewing' } },
          { value: 'question', label: { en: 'Question' } },
        ],
      },
    }

    /** What the media module registers as `wx-media`: a stand-in that says what it was given. */
    const Picker = defineComponent({
      name: 'FakeMediaField',
      props: {
        modelValue: { type: Object, default: null },
        aspect: { type: String, default: '' },
        captions: Boolean,
      },
      emits: ['update:modelValue'],
      setup: (props) => () => h('div', { class: 'fake-media' }, props.modelValue?.path ?? 'empty'),
    })
    const withLibrary = {
      types: { 'wx-media': { component: Picker } },
    } as unknown as Partial<AdminContext>

    const view = (wrapper: ReturnType<typeof dialog>['wrapper']) =>
      wrapper
        .findAllComponents({ name: 'WxSegmented' })
        .find((one) => one.props('ariaLabel') === 'Show the choices as')
    const save = async (wrapper: ReturnType<typeof dialog>['wrapper']) => {
      await wrapper
        .findAllComponents({ name: 'WxButton' })
        .find((one) => one.text() === 'Save')!
        .trigger('click')
      await flushPromises()
    }

    it('switches a radio to cards with columns, a description and a picture per choice', async () => {
      const { wrapper, put, labels } = dialog(purpose, [], [], withLibrary)
      await flushPromises()

      expect(view(wrapper)!.props('modelValue')).toBe('list')
      expect(labels()).not.toContain('Cards in a row')
      expect(document.querySelectorAll('.fake-media')).toHaveLength(0)

      view(wrapper)!.vm.$emit('update:modelValue', 'cards')
      await flushPromises()

      expect(labels()).toContain('Cards in a row')
      expect(document.querySelectorAll('.fake-media')).toHaveLength(2)

      const columns = wrapper
        .findAllComponents({ name: 'WxFormItem' })
        .find((one) => one.props('label') === 'Cards in a row')!
        .findComponent({ name: 'WxInputNumber' })
      columns.vm.$emit('update:modelValue', 2)

      const descriptions = wrapper
        .findAllComponents({ name: 'WxInput' })
        .filter(
          (one) =>
            one.attributes('aria-label') === 'Description' ||
            one.props('placeholder') === 'Description',
        )
      expect(descriptions).toHaveLength(2)
      descriptions[0]!.vm.$emit('update:modelValue', { en: 'See the flat with an agent' })
      wrapper.findAllComponents(Picker)[0]!.vm.$emit('update:modelValue', {
        path: 'media/ab/viewing.svg',
        url: '/storage/media/ab/viewing.svg',
      })
      await flushPromises()
      await save(wrapper)

      expect(put).toHaveBeenCalledWith(
        '/api/cms/inbox/fields/7',
        expect.objectContaining({
          options: {
            view: 'cards',
            columns: 2,
            choices: [
              {
                value: 'viewing',
                label: { en: 'Viewing' },
                description: { en: 'See the flat with an agent' },
                image: { path: 'media/ab/viewing.svg', url: '/storage/media/ab/viewing.svg' },
              },
              { value: 'question', label: { en: 'Question' } },
            ],
          },
        }),
      )
    })

    it('back to a list drops the columns and keeps what the choices carry', async () => {
      const { wrapper, put } = dialog({
        ...purpose,
        options: {
          view: 'cards',
          columns: 3,
          choices: [{ value: 'viewing', label: 'Viewing', description: 'Kept' }],
        },
      })
      await flushPromises()

      view(wrapper)!.vm.$emit('update:modelValue', 'list')
      await flushPromises()
      await save(wrapper)

      expect(put).toHaveBeenCalledWith(
        '/api/cms/inbox/fields/7',
        expect.objectContaining({
          options: { choices: [{ value: 'viewing', label: 'Viewing', description: 'Kept' }] },
        }),
      )
    })

    it('has no picture without the library, and no cards for a select', async () => {
      const { wrapper } = dialog({ ...purpose, options: { ...purpose.options, view: 'cards' } })
      await flushPromises()

      // Cards still work — the description is there — but there is nothing to pick a picture with.
      expect(document.querySelectorAll('.wx-inbox-choices__description')).toHaveLength(2)
      expect(document.querySelectorAll('.fake-media')).toHaveLength(0)

      wrapper.findComponent({ name: 'WxSelect' }).vm.$emit('update:modelValue', 'select')
      await flushPromises()

      expect(view(wrapper)).toBeUndefined()
      expect(document.querySelectorAll('.wx-inbox-choices__description')).toHaveLength(0)
    })
  })

  describe('a step (FORM_STEPS §9.1)', () => {
    it('asks for what a step has — no type, no «Required» — and saves a row of type step', async () => {
      const { wrapper, post, control, labels } = dialog(null, [], [phone], {}, { step: true })
      await flushPromises()

      expect(document.body.textContent).toContain('New step')
      expect(labels()).not.toContain('Type')
      expect(labels()).toEqual(
        expect.arrayContaining([
          'Title of the step',
          'Short label in the indicator',
          'Line under the title',
          'Words of the «Next» button',
        ]),
      )
      const ticks = wrapper
        .findAllComponents({ name: 'WxCheckbox' })
        .map((one) => one.props('label'))
      expect(ticks).toEqual(['Shown'])

      control('Title of the step').vm.$emit('update:modelValue', { en: 'Your details' })
      control('Words of the «Next» button').vm.$emit('update:modelValue', { en: 'Continue' })
      await flushPromises()

      // Named as a step, so a list of names tells it from a field.
      expect(control('Name').props('modelValue')).toBe('step_your_details')

      await wrapper
        .findAllComponents({ name: 'WxButton' })
        .find((one) => one.text() === 'Save')!
        .trigger('click')
      await flushPromises()

      expect(post).toHaveBeenCalledWith(
        '/api/cms/inbox/forms/1/fields',
        expect.objectContaining({
          type: 'step',
          name: 'step_your_details',
          title: { en: 'Your details' },
          options: { 'next-text': { en: 'Continue' } },
          is_required: false,
          in_table: false,
        }),
      )
    })

    it('says that a step switched off takes its fields with it', async () => {
      const row: InboxField = {
        ...phone,
        id: 9,
        type: 'step',
        key: 'step_x',
        name: 'step_x',
        is_enabled: false,
      }
      dialog(row, [], [phone, row])
      await flushPromises()

      expect(document.body.textContent).toContain('A step that is off takes its fields with it')
    })
  })
  describe('a summary (FORM_STEPS §7)', () => {
    const purpose: InboxField = {
      ...phone,
      id: 6,
      key: 'purpose',
      name: 'purpose',
      type: 'radio',
      title: { en: 'Purpose' },
    }
    const terms: InboxField = {
      ...phone,
      id: 7,
      key: 'terms',
      name: 'terms',
      type: 'consent',
      title: { en: 'Terms' },
    }
    const step: InboxField = {
      ...phone,
      id: 8,
      key: 'step_contacts',
      name: 'step_contacts',
      type: 'step',
      title: { en: 'Contacts' },
    }

    it('is chosen among the types and asks for the fields it shows, of the ones a summary may show', async () => {
      const { wrapper, post, control, labels } = dialog(null, [], [purpose, terms, step, phone])
      await flushPromises()

      const type = wrapper.findComponent({ name: 'WxSelect' })
      expect(type.props('options')).toContainEqual({ value: 'summary', label: 'Summary' })
      type.vm.$emit('update:modelValue', 'summary')
      await flushPromises()

      expect(document.body.textContent).toContain('Nothing of it goes into the submission')
      expect(labels()).toEqual(
        expect.arrayContaining(['Label', 'Hint under the field', 'What to show']),
      )
      expect(labels()).not.toContain('Placeholder')
      // Nothing a question has: no «Required», no column, no condition.
      const ticks = wrapper
        .findAllComponents({ name: 'WxCheckbox' })
        .map((one) => one.props('label'))
      expect(ticks).toEqual(['Shown'])
      expect(wrapper.findComponent({ name: 'WxSegmented' }).exists()).toBe(false)
      // The form has steps: no word of a summary the site would not show.
      expect(document.body.textContent).not.toContain('The form has no steps')

      const shown = wrapper
        .findAllComponents({ name: 'WxFormItem' })
        .find((one) => one.props('label') === 'What to show')!
        .findComponent({ name: 'WxSelect' })
      expect(shown.props('multiple')).toBe(true)
      expect(shown.props('options')).toEqual([
        { value: 'purpose', label: 'Purpose' },
        { value: 'phone', label: 'Phone' },
      ])
      shown.vm.$emit('update:modelValue', ['purpose'])
      control('Label').vm.$emit('update:modelValue', { en: 'Your request' })
      await flushPromises()

      await wrapper
        .findAllComponents({ name: 'WxButton' })
        .find((one) => one.text() === 'Save')!
        .trigger('click')
      await flushPromises()

      expect(post).toHaveBeenCalledWith(
        '/api/cms/inbox/forms/1/fields',
        expect.objectContaining({
          type: 'summary',
          title: { en: 'Your request' },
          options: { fields: ['purpose'] },
          is_required: false,
          in_table: false,
        }),
      )
    })

    it('says that a form without steps does not show it', async () => {
      const row: InboxField = {
        ...phone,
        id: 9,
        key: 'summary',
        name: 'summary',
        type: 'summary',
        title: { en: 'Summary' },
      }
      dialog(row, [], [purpose, row])
      await flushPromises()

      expect(document.body.textContent).toContain('The form has no steps')
    })
  })

  describe('a day and a time (FORM_STEPS §6, §9.2)', () => {
    const meeting: InboxField = {
      ...phone,
      id: 10,
      key: 'meeting',
      name: 'meeting',
      type: 'datetime',
      title: { en: 'Meeting' },
      options: { 'time-step': 30, 'time-from': '09:00', 'time-to': '17:00' },
    }

    it('sets the grid and the days of the week, and names the zone and the times of a day', async () => {
      const { wrapper, put, labels } = dialog(
        meeting,
        [],
        [meeting],
        {},
        {
          form: { ...form, timezone: 'Europe/Kyiv' },
        },
      )
      await flushPromises()

      expect(labels()).toEqual(
        expect.arrayContaining(['Step of the times', 'From', 'To', 'Days of the week']),
      )
      expect(labels()).not.toContain('Placeholder')
      expect(document.body.textContent).toContain('Time zone of the site: Europe/Kyiv')
      expect(document.body.textContent).toContain('Times of a day: 09:00, 09:30 … 16:30')

      const step = wrapper
        .findAllComponents({ name: 'WxFormItem' })
        .find((one) => one.props('label') === 'Step of the times')!
        .findComponent({ name: 'WxSelect' })
      expect(step.props('options')).toHaveLength(6)
      step.vm.$emit('update:modelValue', 60)
      await wrapper
        .findAllComponents({ name: 'WxButton' })
        .find((one) => one.text() === 'Weekdays')!
        .trigger('click')
      await flushPromises()
      expect(document.body.textContent).toContain('Times of a day: 09:00, 10:00 … 16:00')

      await wrapper
        .findAllComponents({ name: 'WxButton' })
        .find((one) => one.text() === 'Save')!
        .trigger('click')
      await flushPromises()

      expect(put).toHaveBeenCalledWith(
        '/api/cms/inbox/fields/10',
        expect.objectContaining({
          type: 'datetime',
          options: expect.objectContaining({ 'time-step': 60, weekdays: [1, 2, 3, 4, 5] }),
        }),
      )
    })
  })
})
