// @vitest-environment node
import { describe, expect, it } from 'vitest'
import {
  cropBox,
  defaultLayers,
  frame,
  layerVars,
  layerWords,
  motion,
  newLayer,
  ratioOf,
  style,
  unknownFields,
  type BannerLayer,
} from './layers'

const text = (over: Partial<BannerLayer> = {}): BannerLayer => ({
  id: 'abcd',
  type: 'text',
  text: { en: 'Hi' },
  tag: 'p',
  style: style(),
  frames: { desktop: frame(), mobile: null },
  motion: motion(),
  ...over,
})

describe('the words of a layer', () => {
  it('speak the page language, else the default, else the first written (decision 6)', () => {
    const order = ['en', 'ru', 'uk']

    expect(layerWords({ en: 'Sale', ru: 'Скидка' }, 'ru', order, {})).toBe('Скидка')
    expect(layerWords({ en: 'Sale' }, 'ru', order, {})).toBe('Sale')
    expect(layerWords({ uk: 'Знижка' }, 'ru', order, {})).toBe('Знижка')
    expect(layerWords({}, 'ru', order, {})).toBe('')
  })

  it('put the fields of the banner in, in the language of the page', () => {
    const fields = { title: 'Весна', text: '' }

    expect(layerWords({ en: '{title} — up to 50%' }, 'ru', ['en', 'ru'], fields)).toBe(
      'Весна — up to 50%',
    )
    expect(layerWords({ en: '{text}' }, 'ru', ['en', 'ru'], fields)).toBe('')
  })

  it('say which braces are not a field', () => {
    expect(unknownFields('{title} {titel} {2026}')).toEqual(['titel'])
  })
})

describe('what the canvas prints', () => {
  it('writes both layouts as the server does, the phone falling back to the computer', () => {
    const vars = layerVars(
      text({ frames: { desktop: frame({ x: 5.224, origin: 'tr' }), mobile: null } }),
      3,
    )

    expect(vars['--x']).toBe('5.22')
    expect(vars['--m-x']).toBe('5.22')
    expect(vars['--ox']).toBe('-100%')
    expect(vars['--display']).toBe('block')
    expect(vars['z-index']).toBe('3')
  })

  it('turns a colour of the palette into its custom property and drops anything else', () => {
    const vars = layerVars(text({ style: style({ color: 'token:accent', background: 'red' }) }), 1)

    expect(vars['--color']).toBe('var(--site-color-accent)')
    expect(vars['--background']).toBeUndefined()
  })
})

describe('the frame and the picture', () => {
  it('reads a ratio written either way', () => {
    expect(ratioOf('16/8', 1)).toBe(2)
    expect(ratioOf('2.5', 1)).toBe(2.5)
    expect(ratioOf('wide', 1)).toBe(1)
  })

  it('crops the longer side of the picture, centred', () => {
    expect(cropBox(2, 1)).toEqual({ left: 25, top: 0, width: 50, height: 100 })
    expect(cropBox(1, 2)).toEqual({ left: 0, top: 25, width: 100, height: 50 })
  })
})

describe('new layers', () => {
  it('start a banner with its title, text and buttons (decision 5)', () => {
    const layers = defaultLayers('uk')

    expect(layers.map((layer) => [layer.type, layer.text?.uk ?? null])).toEqual([
      ['text', '{title}'],
      ['text', '{text}'],
      ['buttons', null],
    ])
    expect(new Set(layers.map((layer) => layer.id)).size).toBe(3)
  })

  it('get an id nobody else has', () => {
    expect(newLayer('image', ['aaaaaa'], 'en').id).toMatch(/^[a-z0-9]{4,12}$/)
  })
})
