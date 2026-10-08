import { describe, expect, it } from 'vitest'
import { lintBlock, stringOnText, undeclared } from './lint'

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

  it('says when the marker is not the identifier, which the runtime matches exactly', () => {
    const codes = (template: string) =>
      lintBlock('quote', { template, styles: '', schema: [] }, t).map((lint) => lint.code)

    expect(codes('<q data-wx-block="Quote"></q>')).toEqual(['marker-slug'])
    expect(codes('<q data-wx-block="quote"></q>')).toEqual([])
    expect(codes('<q data-wx-block="{{ $block }}"></q>')).toEqual([])
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

describe('a text field changed by a string function', () => {
  const schema = [
    {
      id: 'card',
      type: 'wx-card',
      children: [
        { id: 'heading', type: 'wx-input' },
        { id: 'lead', type: 'wx-textarea' },
      ],
    },
    { id: 'tel', type: 'wx-input', props: { type: 'tel' } },
    { id: 'items', type: 'wx-repeater', children: [{ id: 'quote', type: 'wx-textarea' }] },
  ]

  it('names each field once, on the line it is first changed on', () => {
    const template = [
      '<div data-wx-block="cta">',
      "  <h2>{{ rtrim(trim($heading), '.') }}</h2>",
      '  <p>{{ (string) $lead }}</p>',
      "  <p>{{ Str::limit($heading, 10) }} {{ $lead . '!' }}</p>",
      "  @foreach ($items as $item)<q>{{ trim($item['quote'], '“”\"') }}</q>@endforeach",
      '</div>',
    ].join('\n')

    expect(stringOnText(template, schema)).toEqual([
      ['$heading', 2],
      ['$lead', 3],
      ["$item['quote']", 5],
    ])

    const lints = lintBlock('cta', { template, styles: '', schema }, t)
    expect(lints.filter((lint) => lint.code === 'string-on-text')).toHaveLength(3)
  })

  it('says nothing about the fix and the safe ways', () => {
    const template = [
      "<h2>{{ wx_text($heading)->trimEnd('.') }}</h2>",
      "<h3>{{ $heading }} {{ rtrim($heading->plain(), '.') }}</h3>",
      "<h4>{!! rtrim(trim(e($heading)), '.') !!}</h4>",
      '<a href="tel:{{ trim($tel) }}">x</a>',
      "@if (trim($heading) !== '') x @endif",
      '{{-- trim($heading) --}}',
      "@foreach ($items as $item)<q>{{ wx_text($item['quote'])->trim('“”') }}</q>@endforeach",
    ].join('\n')

    expect(stringOnText(template, schema)).toEqual([])
  })
})

describe('a closure in a template', () => {
  it('declares its own parameters', () => {
    expect(
      undeclared('{{ wx_text($lead)->map(fn ($text) => Str::limit($text, 9)) }} {{ $x }}', [
        { id: 'lead', type: 'wx-textarea' },
      ]),
    ).toEqual(['x'])
  })
})
