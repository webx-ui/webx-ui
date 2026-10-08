<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Panel\Lints;

final class LintsTest extends TestCase
{
    #[Test]
    public function a_clean_block_says_nothing(): void
    {
        $styles = <<<'CSS'
        /* a comment with h2 { } inside */
        .b-hero { display: grid; }
        .b-hero__title, .b-hero__text { margin: 0; }
        .b-hero:hover .b-hero__cta { color: red; }
        @container (max-width: 700px) {
          .b-hero { grid-template-columns: 1fr; }
        }
        @keyframes b-hero-in { from { opacity: 0 } 50% { opacity: .5 } to { opacity: 1 } }
        .b-hero { &__art { border-radius: 8px; } }
        CSS;

        $this->assertSame([], Lints::check('hero', '<div data-wx-block="hero"></div>', $styles));
    }

    #[Test]
    public function each_habit_is_named_with_its_line(): void
    {
        $styles = "\n.b-hero { }\n.promo, #x { }\nh2, a { }\n@media (min-width: 1px) { }";

        $lints = Lints::check('hero', '<div></div>', $styles);

        $this->assertSame(
            [['template', 'no-marker', null], ['styles', 'stray-selectors', 3], ['styles', 'bare-selectors', 4], ['styles', 'media-query', 5]],
            array_map(static fn (array $lint): array => [$lint['file'], $lint['code'], $lint['line']], $lints),
        );

        $this->assertStringContainsString('.promo, #x', $lints[1]['message']);
        $this->assertStringContainsString('h2, a', $lints[2]['message']);
    }

    /*
     * A text field may hold a shortcode, and then a string function hands `{{ }}` its HTML to
     * escape again. Each field is named once, on the line it is first changed on.
     */
    #[Test]
    public function a_string_function_on_a_text_field_printed_with_braces_is_named(): void
    {
        $schema = [
            ['id' => 'card', 'type' => 'wx-card', 'children' => [
                ['id' => 'heading', 'type' => 'wx-input'],
                ['id' => 'lead', 'type' => 'wx-textarea'],
            ]],
            ['id' => 'tel', 'type' => 'wx-input', 'props' => ['type' => 'tel']],
            ['id' => 'count', 'type' => 'wx-input-number'],
            ['id' => 'items', 'type' => 'wx-repeater', 'children' => [['id' => 'quote', 'type' => 'wx-textarea']]],
        ];

        $template = <<<'BLADE'
        <div data-wx-block="cta">
          <h2>{{ rtrim(trim($heading), '.') }}</h2>
          <p>{{ (string) $lead }}</p>
          <p>{{ Str::limit($heading, 10) }} {{ $lead . '!' }}</p>
          @foreach ($items as $item)<q>{{ trim($item['quote'], '“”"') }}</q>@endforeach
        </div>
        BLADE;

        $this->assertSame(
            [['$heading', 2], ['$lead', 3], ["\$item['quote']", 5]],
            Lints::stringOnText($template, $schema),
        );

        $lints = array_values(array_filter(Lints::check('cta', $template, '', $schema), static fn (array $lint): bool => $lint['code'] === 'string-on-text'));
        $this->assertCount(3, $lints);
        $this->assertStringContainsString('wx_text($heading)->trimEnd(".")', $lints[0]['message']);
    }

    #[Test]
    public function the_fix_and_the_safe_ways_say_nothing(): void
    {
        $schema = [
            ['id' => 'heading', 'type' => 'wx-input'],
            ['id' => 'tel', 'type' => 'wx-input', 'props' => ['type' => 'tel']],
            ['id' => 'items', 'type' => 'wx-repeater', 'children' => [['id' => 'quote', 'type' => 'wx-textarea']]],
        ];

        $template = <<<'BLADE'
        <h2>{{ wx_text($heading)->trimEnd('.') }}</h2>
        <h3>{{ $heading }} {{ rtrim($heading->plain(), '.') }}</h3>
        <h4>{!! rtrim(trim(e($heading)), '.') !!}</h4>
        <a href="tel:{{ trim($tel) }}">{{ trim($other ?? '') }}</a>
        @if (trim($heading) !== '') x @endif
        {{-- trim($heading) --}}
        @foreach ($items as $item)<q>{{ wx_text($item['quote'])->trim('“”') }}</q>@endforeach
        BLADE;

        $this->assertSame([], Lints::stringOnText($template, $schema));
    }
}
