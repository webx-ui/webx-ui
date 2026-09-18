import { describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { WebxUI } from '@webx-ui/core'
import { createI18n, i18nKey } from './i18n'
import { adminMessages } from './messages'
import RenameButton from './RenameButton.vue'

/** The panel is teleported, so what it draws is in the document rather than in the wrapper. */
function form(): HTMLFormElement | null {
  return document.querySelector('.wx-rename')
}

function field(): HTMLInputElement | null {
  return document.querySelector('.wx-rename input')
}

function draw(name: string) {
  const i18n = createI18n({ locale: 'en' })
  i18n.defaults('webx-admin', adminMessages)

  return mount(RenameButton, {
    props: { name },
    global: { plugins: [WebxUI], provide: { [i18nKey as symbol]: i18n } },
    attachTo: document.body,
  })
}

async function open(wrapper: ReturnType<typeof draw>): Promise<void> {
  await wrapper.get('button').trigger('click')
  await nextTick()
  await nextTick()
}

describe('WxRenameButton', () => {
  it('opens on the pencil with the current name in the field', async () => {
    const wrapper = draw('Hero')

    expect(form()).toBeNull()

    await open(wrapper)

    expect(field()?.value).toBe('Hero')

    wrapper.unmount()
  })

  /* The whole point of the control: typing is not renaming, pressing the button is. */
  it('says nothing until the form is submitted', async () => {
    const wrapper = draw('Hero')
    await open(wrapper)

    const input = field()
    input!.value = 'Banner'
    input!.dispatchEvent(new Event('input'))
    await nextTick()

    expect(wrapper.emitted('rename')).toBeUndefined()

    form()!.dispatchEvent(new Event('submit'))
    await nextTick()

    expect(wrapper.emitted('rename')).toEqual([['Banner']])

    wrapper.unmount()
  })

  it('treats a blank name, and one that has not changed, as nothing to do', async () => {
    const wrapper = draw('Hero')
    await open(wrapper)

    const input = field()
    input!.value = '   '
    input!.dispatchEvent(new Event('input'))
    await nextTick()

    form()!.dispatchEvent(new Event('submit'))
    await nextTick()

    expect(wrapper.emitted('rename')).toBeUndefined()

    wrapper.unmount()
  })
})
