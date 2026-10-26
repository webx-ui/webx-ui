<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Mcp;

use Illuminate\Contracts\Container\Container;
use WebxUi\Configurator\Engine\Engine;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Pricing\Texts;
use WebxUi\Configurator\Schema\Validator;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;

/**
 * What an agent reads before it builds a configurator (§14): what exists, and what a schema is.
 *
 * The reference is put together from the validator's constants rather than written by hand, so
 * the codes an agent is told about are the codes it will be refused with.
 */
final class ConfiguratorResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'configurators://catalog',
                'Configurators',
                'Every configurator with its key, title, status, request form and steps (key, type, how many '
                .'options or fields). Read it before creating one, so that you reuse rather than duplicate. A '
                .'configurator reaches a page of the site as the block «configurator» (blocks_*), by its key. The '
                .'block\'s values: configurator (the key), title (empty — the configurator\'s title; "-" — no '
                .'heading) and preset ({ step: value, field: number } the block starts with over initial; null — none).',
                fn (): array => $this->catalog(),
            ),
            new McpResource(
                'configurators://schema',
                'Configurator schema',
                'The schema document of a configurator: its keys, the step types, the conditions, the area '
                .'formula, how the price is counted, every problem code configurators_validate can answer, and '
                .'an example to start from. A picture is a media library key — the path media_get_file answers with, '
                .'"media/ab/cd/….webp" — not an id or an object; media_upload_from_url answers with the id.',
                fn (): array => $this->schema(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $locales = $this->container->make(Locales::class);

        return [
            'locales' => $locales->codes(),
            'default_locale' => $locales->defaultCode(),
            'configurators' => Configurator::query()->orderBy('id')->get()->map(static function (Configurator $configurator) use ($locales): array {
                $editing = $configurator->editing();

                return [
                    'id' => (int) $configurator->getKey(),
                    'key' => $configurator->key,
                    'title' => Texts::pick($editing['title'], $locales->content(), $locales->defaultCode()),
                    'status' => $configurator->status(),
                    'form' => $editing['form'],
                    'steps' => array_map(static fn (array $step): array => [
                        'key' => (string) $step['key'],
                        'type' => $step['type'] ?? null,
                        'options' => count(Engine::options($step)),
                        'fields' => count(Engine::fields($step)),
                    ], Engine::steps($editing['schema'] ?? [])),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'version' => Validator::VERSION,
            'top' => [
                'version' => 'required, '.Validator::VERSION,
                'currency' => 'required, an ISO 4217 code in capitals: "UAH", "EUR"',
                'pricing.round' => 'the step the total is rounded to, above 0; 1 when omitted',
                'initial' => '{ step key: option value, field key: number } — where a visitor starts; a size outside its range is pulled into it, as on the site',
                'discount' => '{ percent (0 < p < 100), until (YYYY-MM-DD, the last day; none — no end), label (text) }',
                'steps' => 'required, at least one; the order is the order shown and the order priced',
                'previews' => '[{ when, image }] — the first rule whose when holds gives the picture',
            ],
            'keys' => [
                'step and field key' => 'a-z, 0-9 and _, starting with a letter; steps and size fields share one namespace — a field key is a name in the area formula',
                'option value' => 'a slug, unique within its step ("none" in two steps is fine)',
                'configurator key' => 'a-z, 0-9 and -, fixed once published: blocks name the configurator by it',
            ],
            'texts' => 'label, title, hint, subtitle, unit, discount.label: a string (the default language) or { "en": "…", "uk": "…" }. Every label needs the default language.',
            'step_types' => Validator::TYPES,
            'step' => 'key, type, label (short, used in the breakdown; title when missing), title, hint, when, unless',
            'option' => 'value, label, subtitle, image (media key, "media/…"; required in cards), swatch (#rgb, #rrggbb or a linear-/radial-gradient of hex colours), swatchImage (media key; wins over swatch), price { fixed, perM2, percent } (numbers, negative allowed), area (formula), defaults { field: number }, limits { field: [min, max] }, when, unless',
            'field' => 'key, label, unit, min, max, step (> 0, the stepper\'s; not checked on the server), default, when, unless',
            'conditions' => 'when: { step key: [values] } — every named step visible and chosen one of the values; unless: hidden when the same when would hold. Only choice steps above the element may be named. A chosen option that becomes hidden moves to the first visible one; a hidden step is not priced and not in a request; a hidden field is 0 in the formula.',
            'formula' => 'area of an option: numbers (1e6 too), size field keys, + - * / ( ) and a unary minus. The options of exactly one choice step have it — the main step — and all of its options do. Dividing by 0 gives 0; an area below 0 is 0. No step with a formula — the area is 0 and perM2 is refused.',
            'price' => [
                '1. sums' => 'for each visible choice step in order: fixed as it is, perM2 × area — each line rounded to a cent',
                '2. percents' => 'for each visible choice step in order: percent of the sum of step 1 (simple, never of another percent)',
                '3. discount' => 'when in force on the day: percent of what steps 1 and 2 give, as a negative line',
                '4. total' => 'rounded to pricing.round, half away from zero; never below 0. total_before_discount is the same without step 3',
            ],
            'values' => 'quote takes { step: value, field: number }: an unknown key is dropped, a choice not offered moves to the first visible option, a size outside its limits is the error values.range — never clamped. values are resolved before actions: a value of a step hidden at that point drops out, so name the step it depends on in values too, or select it in actions after.',
            'errors' => Validator::CODES,
            'example' => [
                'version' => 1,
                'currency' => 'UAH',
                'pricing' => ['round' => 100],
                'initial' => ['construction' => 'corner'],
                'steps' => [
                    ['key' => 'construction', 'type' => 'cards', 'label' => 'Construction', 'options' => [
                        ['value' => 'corner', 'label' => 'Corner', 'image' => 'media/…', 'area' => '(w+d)*h/1e6', 'price' => ['fixed' => 8000, 'perM2' => 4420], 'defaults' => ['h' => 2000, 'w' => 900, 'd' => 900]],
                        ['value' => 'niche', 'label' => 'Niche door', 'image' => 'media/…', 'area' => 'w*h/1e6', 'price' => ['fixed' => 5000, 'perM2' => 4000], 'defaults' => ['h' => 2000, 'w' => 900]],
                    ]],
                    ['key' => 'size', 'type' => 'dimensions', 'title' => 'Sizes', 'hint' => 'mm', 'fields' => [
                        ['key' => 'h', 'label' => 'Height', 'unit' => 'mm', 'min' => 1300, 'max' => 2400, 'step' => 50],
                        ['key' => 'w', 'label' => 'Width', 'unit' => 'mm', 'min' => 600, 'max' => 1600, 'step' => 50],
                        ['key' => 'd', 'label' => 'Depth', 'unit' => 'mm', 'min' => 600, 'max' => 1600, 'step' => 50, 'when' => ['construction' => ['corner']]],
                    ]],
                    ['key' => 'glass', 'type' => 'swatches', 'label' => 'Glass', 'options' => [
                        ['value' => 'clear', 'label' => 'Clear', 'swatch' => '#e8eef2'],
                        ['value' => 'graphite', 'label' => 'Graphite', 'swatch' => '#59616a', 'price' => ['perM2' => 600]],
                    ]],
                    ['key' => 'hardware', 'type' => 'swatches', 'label' => 'Hardware', 'options' => [
                        ['value' => 'chrome', 'label' => 'Chrome', 'swatch' => '#c9ced3'],
                        ['value' => 'gold', 'label' => 'Gold', 'swatch' => 'linear-gradient(135deg, #b8862b, #f3d27a)', 'price' => ['percent' => 12]],
                    ]],
                ],
            ],
        ];
    }
}
