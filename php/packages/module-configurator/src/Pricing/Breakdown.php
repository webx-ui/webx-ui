<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Pricing;

use Illuminate\Support\Number;
use WebxUi\Configurator\Engine\Engine;
use WebxUi\Configurator\Engine\Quote;

/**
 * The words of a breakdown (§7.4): «Construction: Corner enclosure — 4 420 × 3.6 m²» for each
 * line, in the language asked for, from the module's words and the document's labels.
 *
 * Not part of the parity: `NumberFormatter` and `Intl.NumberFormat` differ in their spaces, and
 * the numbers beside the words are what both copies have to agree on.
 */
final class Breakdown
{
    /**
     * The quote as an array, every line with its `line`, and the selection in words.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function describe(array $schema, Quote $quote, string $locale, string $default): array
    {
        $labels = self::labels($schema, $locale, $default);
        $array = $quote->toArray();

        foreach ($array['lines'] as $at => $line) {
            $step = is_string($line['step'] ?? null) ? $line['step'] : '';
            $option = is_string($line['option'] ?? null) ? $line['option'] : '';

            $array['lines'][$at]['line'] = (string) trans('webx-configurator::site.line.'.$line['kind'], [
                'step' => $labels['steps'][$step] ?? $step,
                'option' => $labels['options'][$step][$option] ?? $option,
                'rate' => self::number((float) ($line['rate'] ?? 0), $locale, money: false),
                'area' => self::number((float) ($line['area'] ?? 0), $locale, money: false),
                'amount' => self::number((float) $line['amount'], $locale),
                'label' => Texts::pick(Engine::map($schema['discount'] ?? null)['label'] ?? null, $locale, $default)
                    ?: (string) trans('webx-configurator::site.discount'),
            ], $locale);
        }

        $array['selection'] = [];

        foreach (Engine::chosenOptions($schema, $quote->state) as [$step, $option]) {
            $array['selection'][] = [
                'step' => $step,
                'label' => $labels['steps'][$step] ?? $step,
                'value' => (string) $option['value'],
                'option' => $labels['options'][$step][(string) $option['value']] ?? (string) $option['value'],
            ];
        }

        return $array;
    }

    /**
     * A number as a person reads it in a language. Money has no fraction when it is whole and two
     * digits when it is not — «12.5» reads as a typo; a rate or an area has up to two, as needed.
     */
    public static function number(float $value, string $locale, bool $money = true): string
    {
        $digits = abs($value - round($value)) < 0.005 ? 0 : 2;

        if (extension_loaded('intl')) {
            $formatted = $money
                ? Number::format($value, precision: $digits, locale: $locale)
                : Number::format($value, maxPrecision: 2, locale: $locale);

            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return number_format($value, $digits, '.', "\u{00A0}");
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array{steps: array<string, string>, options: array<string, array<string, string>>}
     */
    private static function labels(array $schema, string $locale, string $default): array
    {
        $labels = ['steps' => [], 'options' => []];

        foreach (Engine::steps($schema) as $step) {
            $key = (string) $step['key'];
            $label = Texts::pick($step['label'] ?? null, $locale, $default);
            $labels['steps'][$key] = $label !== '' ? $label : Texts::pick($step['title'] ?? null, $locale, $default);

            foreach (Engine::options($step) as $option) {
                $labels['options'][$key][(string) $option['value']] = Texts::pick($option['label'] ?? null, $locale, $default);
            }
        }

        return $labels;
    }
}
