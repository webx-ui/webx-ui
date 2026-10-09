<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use WebxUi\Themes\TokenType;
use WebxUi\Themes\Vocabulary;

class TokenTypeTest extends PHPUnitTestCase
{
    /**
     * @return iterable<string, array{TokenType, string}>
     */
    public static function accepted(): iterable
    {
        yield 'hex' => [TokenType::Color, '#0e6b5c'];
        yield 'short hex with alpha' => [TokenType::Color, '#fffa'];
        yield 'named colour' => [TokenType::Color, 'transparent'];
        yield 'oklch' => [TokenType::Color, 'oklch(62% 0.12 160 / 0.9)'];
        yield 'colour mix' => [TokenType::Color, 'color-mix(in oklch, #0e6b5c 80%, white)'];
        yield 'another token' => [TokenType::Color, 'var(--site-color-bg)'];
        yield 'rem' => [TokenType::Length, '1.125rem'];
        yield 'zero' => [TokenType::Length, '0'];
        yield 'clamp' => [TokenType::Length, 'clamp(1rem, 0.9rem + 0.5vw, 1.25rem)'];
        yield 'font stack' => [TokenType::Font, '"Inter", system-ui, sans-serif'];
        yield 'font in cyrillic' => [TokenType::Font, "'Шрифт', serif"];
        yield 'shadow' => [TokenType::Shadow, '0 1px 2px rgb(0 0 0 / 0.08), 0 0 0 1px #0001'];
        yield 'no shadow' => [TokenType::Shadow, 'none'];
        yield 'number' => [TokenType::Number, '1.6'];
        yield 'time' => [TokenType::Time, '180ms'];
        yield 'easing keyword' => [TokenType::Easing, 'ease-out'];
        yield 'cubic bezier' => [TokenType::Easing, 'cubic-bezier(0.2, 0, 0, 1)'];
    }

    /**
     * @return iterable<string, array{TokenType, string}>
     */
    public static function refused(): iterable
    {
        foreach (TokenType::cases() as $type) {
            yield "{$type->value} closing the style" => [$type, '</style><script>alert(1)</script>'];
            yield "{$type->value} leaving the declaration" => [$type, 'red; } body { display: none'];
            yield "{$type->value} empty" => [$type, ''];
        }

        yield 'hex of the wrong length' => [TokenType::Color, '#12345'];
        yield 'colour with a url' => [TokenType::Color, 'url(https://example.com/x.png)'];
        yield 'colour with a comment' => [TokenType::Color, 'red/**/'];
        yield 'important' => [TokenType::Color, 'red !important'];
        yield 'length without a unit' => [TokenType::Length, '12'];
        yield 'unbalanced function' => [TokenType::Length, 'calc(1rem + 2px'];
        yield 'closing paren first' => [TokenType::Shadow, ')0 0 1px('];
        yield 'unclosed quote' => [TokenType::Font, '"Inter, serif'];
        yield 'font with an escape' => [TokenType::Font, 'Inter\\3c /style'];
        yield 'time without a unit' => [TokenType::Time, '200'];
        yield 'number with a unit' => [TokenType::Number, '1.5em'];
        yield 'easing made up' => [TokenType::Easing, 'bouncy'];
        yield 'reference outside the site' => [TokenType::Color, 'var(--wx-color-primary)'];
        yield 'too long' => [TokenType::Font, str_repeat('a', 201)];
    }

    #[Test]
    #[DataProvider('accepted')]
    public function a_value_of_its_type_is_accepted(TokenType $type, string $value): void
    {
        $this->assertTrue($type->accepts($value));
    }

    #[Test]
    #[DataProvider('refused')]
    public function a_value_that_could_break_out_or_is_not_its_type_is_refused(TokenType $type, string $value): void
    {
        $this->assertFalse($type->accepts($value));
    }

    #[Test]
    public function the_engine_vocabulary_is_the_first_edition_of_the_spec(): void
    {
        $vocabulary = Vocabulary::base();

        $this->assertCount(40, $vocabulary->tokens);
        $this->assertSame(TokenType::Color, $vocabulary->type('color-accent-2'));
        $this->assertSame(TokenType::Length, $vocabulary->type('font-size-6'));
        $this->assertSame(TokenType::Number, $vocabulary->type('line-height-base'));
        $this->assertSame(TokenType::Length, $vocabulary->type('space-8'));
        $this->assertSame(TokenType::Easing, $vocabulary->type('easing'));
        $this->assertFalse($vocabulary->accepts('color-olive', '#708238'));
    }
}
