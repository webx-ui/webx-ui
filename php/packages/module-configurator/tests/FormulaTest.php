<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;
use WebxUi\Configurator\Engine\Formula;
use WebxUi\Configurator\Engine\FormulaError;

/**
 * The area formula (§7.1): five operators and brackets, and nothing a parser could be talked into.
 */
final class FormulaTest extends PlainTestCase
{
    #[Test]
    public function it_counts_with_precedence_brackets_and_a_unary_minus(): void
    {
        $sizes = ['w' => 900, 'd' => 900, 'h' => 2000];

        $this->assertEqualsWithDelta(3.6, Formula::evaluate(Formula::parse('(w+d)*h/1e6'), $sizes), 1e-9);
        $this->assertEqualsWithDelta(900 + 900 * 2000 / 1e6, Formula::evaluate(Formula::parse('w + d + w*h/1E6 - w'), $sizes), 1e-9);
        $this->assertEqualsWithDelta(-0.25, Formula::evaluate(Formula::parse('-(.5 * 0.5)'), []), 1e-9);
        $this->assertEqualsWithDelta(2.5e-3, Formula::evaluate(Formula::parse('2.5E-3'), []), 1e-12);
        $this->assertSame(['w', 'd', 'h'], Formula::keys(Formula::parse('(w+d)*h/1e6 + w')));
    }

    #[Test]
    public function a_missing_key_is_zero_and_so_is_a_division_by_it(): void
    {
        $this->assertSame(900.0, Formula::evaluate(Formula::parse('w + d'), ['w' => 900]));
        $this->assertSame(0.0, Formula::evaluate(Formula::parse('w / d'), ['w' => 900]));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function refused(): iterable
    {
        yield 'a statement' => ['h;system(1)'];
        yield 'a call' => ['max(w, h)'];
        yield 'a property' => ['constructor'.'.prototype'];
        yield 'a power' => ['w ** 2'];
        yield 'capitals' => ['W*H'];
        yield 'a variable' => ['$w * h'];
        yield 'a string' => ['"w" * h'];
        yield 'empty' => ['  '];
        yield 'an open bracket' => ['(w + h'];
        yield 'a dangling operator' => ['w +'];
        yield 'two values in a row' => ['w h'];
        yield 'too deep' => [str_repeat('(', 40).'w'.str_repeat(')', 40)];
        yield 'too long' => [str_repeat('w+', 300).'w'];
    }

    #[Test]
    #[DataProvider('refused')]
    public function anything_else_is_refused_with_where(string $formula): void
    {
        $this->expectException(FormulaError::class);

        Formula::parse($formula);
    }

    #[Test]
    public function the_refusal_says_where(): void
    {
        try {
            Formula::parse('w * h;');
            $this->fail('A semicolon is not part of a formula.');
        } catch (FormulaError $error) {
            $this->assertSame(5, $error->position);
        }
    }
}
