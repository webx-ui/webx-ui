import { describe, expect, it } from 'vitest'
import { lintBlock, undeclared } from './lint'

const t = (key: string, params: Record<string, string | number> = {}) =>
  `${key} ${Object.values(params).join(' ')}`.trim()

describe('the checks under the editor', () => {
  it('says nothing about a clean block', () => {
    const lints = lintBlock(
      'hero',
      {
        template:
          '<section data-wx-block="hero">{{ $title }} @foreach($items as $i) {{ $i }} @endforeach</section>',
        styles:
          '/* h2 {} */ .b-hero { display: grid } .b-hero__a, .b-hero__b { margin: 0 } @container (max-width: 700px) { .b-hero { gap: 0 } } @keyframes b-hero-in { from { opacity: 0 } 50% { opacity: .5 } to { opacity: 1 } } .b-hero { &__art { color: red } }',
        schema: [
          { id: 'title', type: 'wx-input' },
          { id: 'card', type: 'wx-card', children: [{ id: 'items', type: 'wx-repeater' }] },
        ],
      },
      t,
    )

    expect(lints).toEqual([])
  })

  it('names each habit with its line', () => {
    const lints = lintBlock(
      'hero',
      {
        template: '<div>{{ $title }} {{ $nothing }}</div>',
        styles: '\n.b-hero { }\n.promo, #x { }\nh2, a { }\n@media (min-width: 1px) { }',
        schema: [{ id: 'title', type: 'wx-input' }],
      },
      t,
    )

    expect(lints.map((lint) => [lint.file, lint.code, lint.line])).toEqual([
      ['template', 'no-marker', null],
      ['template', 'variables-missing', null],
      ['styles', 'stray-selectors', 3],
      ['styles', 'bare-selectors', 4],
      ['styles', 'media-query', 5],
    ])
    expect(lints[1]!.message).toContain('$nothing')
    expect(lints[2]!.message).toContain('.promo, #x')
  })

  it('does not count what the template itself declares', () => {
    expect(
      undeclared(
        '@php $n = 1; @endphp @foreach($rows as $k => $row) {{ $row }} {{ $n }} {{ $loop->index }} {{ $entity?->title }} @endforeach {{ $x }}',
        [{ id: 'rows', type: 'wx-repeater' }],
      ),
    ).toEqual(['x'])
  })
})

describe('what a called type is handed', () => {
  it('knows $slot without a schema field: every type can be called with a body', () => {
    expect(
      undeclared('<span>{{ $slot }} {{ $aside }}</span>', [{ id: 'aside', type: 'wx-slot' }]),
    ).toEqual([])
  })
})
