import { describe, expect, it } from 'vitest'
import { mustacheCompletions, mustacheProblems, openSections, scanMustache } from './mustache'
import type { CodeEditorCompletionResult, MustacheVariable } from './types'

const variables: MustacheVariable[] = [
  { name: 'form', label: 'The form', example: 'Contact us' },
  { name: 'note', label: 'A note' },
  {
    name: 'items',
    label: 'The lines of the order',
    fields: [
      { name: 'title', label: 'What' },
      { name: 'qty', label: 'How many' },
    ],
  },
  { name: 'field.*', label: 'A field by its name' },
]

/** Asks the source about the place marked `|`. */
function suggest(marked: string, list = variables): CodeEditorCompletionResult | null {
  const pos = marked.indexOf('|')
  const text = marked.replace('|', '')

  return mustacheCompletions(list)({ text, pos, explicit: false })
}

function labels(result: CodeEditorCompletionResult | null): string[] {
  return result?.options.map((option) => option.label) ?? []
}

function option(result: CodeEditorCompletionResult | null, label: string) {
  return result?.options.find((one) => one.label === label)
}

describe('scanMustache', () => {
  it('finds every kind of tag with its name', () => {
    const tags = scanMustache(
      'Hi {{ form }} {{#items}}{{title}}{{/items}}{{^note}}-{{/note}}{{! c }}',
    )

    expect(tags.map((tag) => [tag.kind, tag.name])).toEqual([
      ['variable', 'form'],
      ['section', 'items'],
      ['variable', 'title'],
      ['close', 'items'],
      ['inverted', 'note'],
      ['close', 'note'],
      ['comment', ''],
    ])
  })

  it('tells raw output, partials and a tag that never ends apart', () => {
    const tags = scanMustache('{{{form}}} {{&form}} {{>footer}} {{=<% %>=}} {{form')

    expect(tags.map((tag) => tag.kind)).toEqual(['raw', 'raw', 'other', 'other', 'unclosed'])
    expect(tags[0]).toMatchObject({ name: 'form', from: 0, to: 10 })
  })

  it('knows which sections are open at a place', () => {
    const text = '{{#items}}{{#note}}x{{/note}}'

    expect(openSections(scanMustache(text), text.length)).toEqual(['items'])
  })
})

describe('mustacheProblems', () => {
  it('reports an unclosed section, a stray close and a refused tag', () => {
    const problems = mustacheProblems('{{#items}}{{title}} {{/note}} {{>footer}}')

    expect(problems.unclosed).toEqual(['items'])
    expect(problems.stray).toEqual(['note'])
    expect(problems.refused).toEqual(['{{>footer}}'])
    expect(problems.unknown).toEqual([])
  })

  it('judges the names by the variables and the fields of the open list', () => {
    const problems = mustacheProblems(
      '{{form}} {{field.phone}} {{#items}}{{title}} {{form}}{{/items}} {{title}} {{nope}}',
      variables,
    )

    expect(problems.unknown).toEqual(['title', 'nope'])
    expect(problems.unclosed).toEqual([])
  })

  it('says a template that ends inside a tag is broken', () => {
    expect(mustacheProblems('Hello {{form').broken).toBe(true)
  })
})

describe('mustacheCompletions', () => {
  it('offers nothing outside a tag', () => {
    expect(suggest('Hello |')).toBeNull()
    expect(suggest('{{form}} |')).toBeNull()
  })

  it('offers the variables after {{, with the example beside and the words in the info', () => {
    const result = suggest('Hi {{|')

    expect(labels(result)).toEqual(expect.arrayContaining(['form', 'note', 'items', 'field.…']))
    expect(option(result, 'form')).toMatchObject({
      apply: 'form}}',
      detail: 'Contact us',
      info: 'The form\nExample: Contact us',
    })
    expect(result?.from).toBe(5)
  })

  it('takes over the }} that closing brackets typed', () => {
    const result = suggest('Hi {{fo|}}')

    expect(result).toMatchObject({ from: 5, to: 9 })
    expect(option(result, 'form')?.apply).toBe('form}}')
  })

  it('writes a list as a whole section with the caret inside', () => {
    expect(option(suggest('{{|'), 'items')?.apply).toBe('#items}}\n${}\n{{/items}}')
  })

  it('offers "has a value" and "is empty" sections of a plain variable', () => {
    const result = suggest('{{|')

    expect(option(result, '#note')?.apply).toBe('#note}}${}{{/note}}')
    expect(option(result, '^note')?.apply).toBe('^note}}${}{{/note}}')
  })

  it('offers the fields of the list being looped over, first', () => {
    const result = suggest('{{#items}}\n- {{|')
    const ordered = [...(result?.options ?? [])].sort((a, b) => (b.boost ?? 0) - (a.boost ?? 0))

    expect(ordered.slice(0, 2).map((one) => one.label)).toEqual(['title', 'qty'])
    expect(option(result, 'title')?.detail).toBe('field of items')
    // The end of the open section is one of the suggestions too.
    expect(option(result, '/items')?.apply).toBe('/items}}')
  })

  it('does not offer the fields once the section is closed', () => {
    expect(labels(suggest('{{#items}}{{/items}} {{|'))).not.toContain('title')
  })

  it('closes the nearest open section after {{/', () => {
    const result = suggest('{{#items}}{{#note}}x{{/|')

    expect(result?.from).toBe(23)
    expect(labels(result)[0]).toBe('note')
    expect(option(result, 'note')?.apply).toBe('note}}')
    expect(option(result, 'note')?.boost).toBeGreaterThan(option(result, 'items')?.boost ?? 0)
  })

  it('has nothing to close when no section is open', () => {
    expect(suggest('{{/|')).toBeNull()
  })

  it('after {{# names what can be a section', () => {
    const result = suggest('{{#it|')

    expect(result?.from).toBe(3)
    expect(option(result, 'items')?.apply).toBe('items}}\n${}\n{{/items}}')
    expect(labels(result)).not.toContain('field.…')
  })

  it('without variables offers only the syntax', () => {
    const result = suggest('{{|', [])

    expect(labels(result)).toEqual(['#…', '^…', '!…'])
  })

  it('stays out of a comment and out of a tag with something no name has', () => {
    expect(suggest('{{! |')).toBeNull()
    expect(suggest('{{a b|')).toBeNull()
  })

  it('speaks the words it is given', () => {
    const result = mustacheCompletions(variables, { list: 'список' })({
      text: '{{',
      pos: 2,
      explicit: false,
    })

    expect(option(result, 'items')?.detail).toBe('список')
  })
})
