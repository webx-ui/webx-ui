<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebxUi\Themes\Checks\MailCheck;

/** Rule 25 of spec §16.1 in a letter (§7.5): colours from theme_token(), never var() or a literal. */
final class MailCheckTest extends TestCase
{
    #[Test]
    public function values_from_theme_token_in_style_are_clean(): void
    {
        $this->assertSame([], MailCheck::check(<<<'BLADE'
            @php $accent = theme_token('color-accent'); @endphp
            <a href="{{ $url }}" style="background-color:{{ $accent }};color:{{ theme_token('color-accent-contrast') }};font-family:{{ theme_token('font-body') }}, sans-serif;">
                {{ __('theme::theme.mail.open') }}
            </a>
            @if ($note !== '')
                <p style="color: {{ $look('color-text-muted') }}; border-bottom: 1px solid transparent;">{{ $note }}</p>
            @endif
            BLADE));
    }

    #[Test]
    public function each_way_a_literal_colour_or_a_variable_gets_into_a_letter_is_found(): void
    {
        $findings = MailCheck::check(<<<'BLADE'
            <td style="color:#333;background:white">
            <table bgcolor="#fff" text="black">
            {{ theme_token('color-bg', 'rgb(255 255 255)') }}
            <p style="color: var(--site-color-text)">
            <style>.x { border-color: hsl(0 0% 50%); }</style>
            BLADE);

        $this->assertSame([1, 1, 2, 2, 3, 4, 5], array_column($findings, 'line'));
        $this->assertStringContainsString('literal colour #333 in a letter', $findings[0]['message']);
        $this->assertStringContainsString('literal colour white', $findings[1]['message']);
        $this->assertStringContainsString('literal colour rgb(255 255 255)', $findings[4]['message']);
        $this->assertStringContainsString('var(--site-color-text) in a letter', $findings[5]['message']);
        $this->assertStringContainsString('(rule 25)', $findings[6]['message']);
    }

    #[Test]
    public function blade_comments_are_not_read(): void
    {
        $this->assertSame([], MailCheck::check("{{-- not var(--site-x), not #fff --}}\n<p>{{ \$x }}</p>"));
    }
}
