import { afterEach, describe, expect, it, vi } from 'vitest'
import { createHelp, HELP_PANEL_ID } from './help'

function panel(keys: string[] = ['a.index', 'b.index']) {
  return createHelp({ state: { manifest: { help: keys } } } as never)
}

function button(parent: HTMLElement = document.body): HTMLButtonElement {
  const element = document.createElement('button')
  element.setAttribute('aria-controls', HELP_PANEL_ID)
  parent.append(element)

  return element
}

afterEach(() => {
  document.body.innerHTML = ''
  vi.unstubAllGlobals()
  vi.useRealTimers()
})

describe('the help panel, as a «?» sees it', () => {
  it('knows only the pages the manifest lists', () => {
    expect(panel().available('a.index')).toBe(true)
    expect(panel().available('c.index')).toBe(false)
    expect(createHelp({ state: { manifest: null } } as never).available('a.index')).toBe(false)
  })

  it('opens a page and closes it on the second press', () => {
    const help = panel()

    help.toggle('a.index', button())
    expect([help.open.value, help.key.value, help.blocking.value]).toEqual([true, 'a.index', false])

    help.toggle('a.index', null)
    expect(help.open.value).toBe(false)
  })

  it('shows the other page in the open panel rather than closing it', () => {
    const help = panel()

    help.toggle('a.index', button())
    help.toggle('b.index', button())

    expect([help.open.value, help.key.value]).toEqual([true, 'b.index'])
  })

  it('is modal for a «?» inside a dialog, and opens a turn later', () => {
    vi.useFakeTimers()
    const dialog = document.createElement('div')
    dialog.setAttribute('role', 'dialog')
    document.body.append(dialog)

    const help = panel()
    help.toggle('a.index', button(dialog))

    expect([help.open.value, help.blocking.value]).toEqual([false, true])

    vi.runAllTimers()
    expect(help.open.value).toBe(true)
  })

  it('is modal on a phone', () => {
    vi.useFakeTimers()
    vi.stubGlobal('matchMedia', () => ({ matches: true }))

    const help = panel()
    help.toggle('a.index', button())
    vi.runAllTimers()

    expect([help.open.value, help.blocking.value]).toEqual([true, true])
  })
})

describe('where the focus goes when the panel closes', () => {
  it('goes back to the «?»', () => {
    const help = panel()
    const trigger = button()

    help.toggle('a.index', trigger)
    help.returnFocus()

    expect(document.activeElement).toBe(trigger)
  })

  it('leaves the form where the reader scrolled it', () => {
    const help = panel()
    const trigger = button()
    const focus = vi.spyOn(trigger, 'focus')

    help.toggle('a.index', trigger)
    help.returnFocus()

    expect(focus).toHaveBeenCalledWith({ preventScroll: true })
  })

  it('stays in the field the reader is typing in', () => {
    const help = panel()
    const field = document.createElement('input')
    document.body.append(field)

    help.toggle('a.index', button())
    field.focus()
    help.returnFocus()

    expect(document.activeElement).toBe(field)
  })

  it('goes to the «?» of the new screen when the old one is gone', () => {
    const help = panel()
    const old = button()

    help.toggle('a.index', old)
    old.remove()

    const fresh = button()
    help.returnFocus()

    expect(document.activeElement).toBe(fresh)
  })
})
