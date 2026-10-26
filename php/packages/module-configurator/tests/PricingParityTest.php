<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PlainTestCase;
use WebxUi\Configurator\Engine\Engine;
use WebxUi\Configurator\Schema\Problem;
use WebxUi\Configurator\Schema\Validator;

/**
 * The cases both engines are held to (§8): one directory of files, read here and by the vitest
 * of `@webx-ui/module-configurator` — no copy, so a case changed is a case changed for both.
 *
 * A case is checked like the widget and the server use the engine: the schema first (§9), then
 * the values resolved strictly — a size outside its limits is an error, as it is for a request —
 * then the visitor's actions in order, then the price on the case's own day.
 */
final class PricingParityTest extends PlainTestCase
{
    /** Numbers are doubles in both copies; this is room for the last bit, not for a cent. */
    private const float TOLERANCE = 1e-6;

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function cases(): iterable
    {
        foreach (glob(dirname(__DIR__).'/resources/fixtures/pricing/*.json') ?: [] as $file) {
            /** @var array<string, mixed> $case */
            $case = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

            yield basename($file, '.json') => [$case];
        }
    }

    #[Test]
    public function there_are_enough_cases_and_every_kind_the_spec_asks_for(): void
    {
        $cases = iterator_to_array(self::cases());

        $this->assertGreaterThanOrEqual(12, count($cases));

        $kinds = [];

        foreach ($cases as [$case]) {
            foreach ($case['expect']['lines'] ?? [] as $line) {
                $kinds[$line['kind']] = true;
            }

            foreach ($case['expect']['errors'] ?? [] as $error) {
                $kinds[$error['code']] = true;
            }
        }

        foreach (['fixed', 'perM2', 'percent', 'discount', 'values.range', 'area.syntax', 'area.unknown_key', 'area.main_count'] as $kind) {
            $this->assertArrayHasKey($kind, $kinds, "No case has {$kind}.");
        }
    }

    /**
     * @param  array<string, mixed>  $case
     */
    #[Test]
    #[DataProvider('cases')]
    public function the_engine_gives_what_the_case_expects(array $case): void
    {
        $schema = $case['schema'];
        $expect = $case['expect'];

        $problems = (new Validator('en'))->validate($schema);

        if ($problems !== []) {
            $this->assertSame(
                $expect['errors'] ?? [],
                array_map(static fn (Problem $problem): array => ['path' => $problem->path, 'code' => $problem->code], $problems),
                $case['name'],
            );

            return;
        }

        $state = Engine::resolve($schema, $case['values']);

        if ($state->errors !== []) {
            $this->assertSame($expect['errors'] ?? [], $state->errors, $case['name']);

            return;
        }

        $this->assertArrayNotHasKey('errors', $expect, "{$case['name']}: expected errors, got none.");

        foreach ($case['actions'] ?? [] as $action) {
            if (isset($action['select'])) {
                $state = Engine::select($schema, $state, (string) $action['select'][0], (string) $action['select'][1]);
            } else {
                $state = Engine::set($schema, $state, (string) $action['set'][0], (float) $action['set'][1]);
            }
        }

        $quote = Engine::price($schema, $state, (string) $case['today'])->toArray();

        $this->assertSame(array_keys($expect['normalized']), array_keys($quote['normalized']), "{$case['name']}: the keys of the values");
        $this->assertNumbers($expect['normalized'], $quote['normalized'], "{$case['name']}: normalized");
        $this->assertSame($expect['hidden'], $quote['hidden'], "{$case['name']}: hidden");

        foreach (['area', 'subtotal', 'total_before_discount', 'total'] as $key) {
            $this->assertEqualsWithDelta($expect[$key], $quote[$key], self::TOLERANCE, "{$case['name']}: {$key}");
        }

        $this->assertCount(count($expect['lines']), $quote['lines'], "{$case['name']}: lines");

        foreach ($expect['lines'] as $at => $line) {
            $this->assertSame(array_keys($line), array_keys($quote['lines'][$at]), "{$case['name']}: the keys of line {$at}");
            $this->assertNumbers($line, $quote['lines'][$at], "{$case['name']}: line {$at}");
        }
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     */
    private function assertNumbers(array $expected, array $actual, string $message): void
    {
        foreach ($expected as $key => $value) {
            if (is_int($value) || is_float($value)) {
                $this->assertIsNumeric($actual[$key], "{$message}.{$key}");
                $this->assertEqualsWithDelta($value, $actual[$key], self::TOLERANCE, "{$message}.{$key}");
            } else {
                $this->assertSame($value, $actual[$key], "{$message}.{$key}");
            }
        }
    }
}
