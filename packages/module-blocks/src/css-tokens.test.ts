import { describe, expect, it } from 'vitest'
import { cssFindings } from './css-tokens'

/** The engine's base vocabulary, the names the cases below use. */
const tokens = [
  'color-bg',
  'color-text',
  'color-accent',
  'color-border',
  'font-body',
  'font-heading',
  'font-size-2',
  'space-2',
  'space-4',
  'duration',
  'easing',
]

const found = (css: string) =>
  cssFindings(css, tokens).map((finding) => [finding.problem, finding.line, finding.subject])

/** The same cases as `CssCheckTest` of the engine: the two have to agree. */
describe('CSS knows only tokens', () => {
  it('finds a literal colour in any form', () => {
    const css = [
      '.a {',
      '  color: #fff;',
      '  background: rgb(0 0 0 / 0.5);',
      '  border: 1px solid black;',
      '  outline-color: oklch(70% 0.1 200);',
      '  box-shadow: 0 0 2px hsla(0, 0%, 0%, 0.2);',
      '  fill: RebeccaPurple;',
      '  --webx-slider-arrow: white;',
      '  --webx-slider-gap: #0e6b5c;',
      '}',
    ].join('\n')

    expect(found(css)).toEqual([
      ['literal-color', 2, '#fff'],
      ['literal-color', 3, 'rgb(…)'],
      ['literal-color', 4, 'black'],
      ['literal-color', 5, 'oklch(…)'],
      ['literal-color', 6, 'hsla(…)'],
      ['literal-color', 7, 'RebeccaPurple'],
      ['literal-color', 8, 'white'],
      ['literal-color', 9, '#0e6b5c'],
    ])
  })

  it('allows what is nobody’s colour', () => {
    const css = `.a {
      color: currentColor;
      background: transparent;
      border-color: inherit;
      color: var(--site-color-text);
      background: color-mix(in srgb, var(--site-color-accent) 20%, transparent);
      transition: color var(--site-duration) var(--site-easing);
      animation: red-pulse 1s;
      grid-area: tan;
      white-space: nowrap;
      background-image: url("icons.svg#red");
      content: "#fff";
      --webx-slider-gap: var(--site-space-4);
    }`

    expect(found(css)).toEqual([])
  })

  it('finds a literal font family and lets a generic one be', () => {
    const css = [
      '.a { font-family: "Inter", sans-serif; }',
      '.b { font-family: Georgia, serif; }',
      '.c { font: italic 600 1rem/1.4 Open Sans, sans-serif; }',
      '.d { font-family: ui-monospace, monospace; }',
      '.e { font: inherit; }',
      '.f { font: italic bold var(--site-font-size-2)/1.2 var(--site-font-heading); }',
    ].join('\n')

    expect(found(css)).toEqual([
      ['literal-font', 1, '"Inter"'],
      ['literal-font', 2, 'Georgia'],
      ['literal-font', 3, 'Open Sans'],
    ])
  })

  it('finds a token the theme does not have and a fallback, but leaves a block’s own variable', () => {
    const css = [
      '.a {',
      '  color: var(--site-color-olive);',
      '  padding: var(--site-space-20);',
      '  border-color: var(--site-color-border, #ccc);',
      '  grid-template-columns: repeat(var(--team-columns, 3), 1fr);',
      '}',
    ].join('\n')

    expect(found(css)).toEqual([
      ['unknown-token', 2, '--site-color-olive'],
      ['unknown-token', 3, '--site-space-20'],
      ['token-fallback', 4, 'var(--site-color-border, …)'],
      ['literal-color', 4, '#ccc'],
    ])
  })

  it('reads neither comments nor font faces, and reads nested rules', () => {
    const css = [
      '/* color: #fff; */',
      '@font-face { font-family: "Inter"; }',
      '.a {',
      '  color: var(--site-color-text);',
      '  &:hover { color: red; }',
      '}',
    ].join('\n')

    expect(found(css)).toEqual([['literal-color', 5, 'red']])
  })
})
