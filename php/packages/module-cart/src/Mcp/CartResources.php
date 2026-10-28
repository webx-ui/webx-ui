<?php

declare(strict_types=1);

namespace WebxUi\Cart\Mcp;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\Translator;
use WebxUi\Cart\Contracts\ChecksOptions;
use WebxUi\Cart\Contracts\CurrencySource;
use WebxUi\Cart\Contracts\ResolvesWithOptions;
use WebxUi\Cart\Purchasables;
use WebxUi\Cart\Totals\AdjustmentSources;
use WebxUi\Cart\Totals\BenefitSources;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\HintSources;
use WebxUi\Cart\Totals\LineSources;
use WebxUi\Cart\Totals\Sources;
use WebxUi\Cart\Totals\TenderSources;
use WebxUi\Cart\Totals\Warning;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;

/**
 * What an agent reads before it reads a cart (§10): what may go into one, who counts it, the
 * limits, and what an answer is made of.
 *
 * Every part is read from the registries, the config and the constants, never written by hand:
 * a satellite that registers a source shows up here the moment it is installed, and a field the
 * state grows is listed without anybody remembering to.
 */
final class CartResources
{
    public function __construct(
        private readonly Purchasables $purchasables,
        private readonly LineSources $lines,
        private readonly AdjustmentSources $adjustments,
        private readonly TenderSources $tenders,
        private readonly BenefitSources $benefits,
        private readonly HintSources $hints,
        private readonly CurrencySource $currencies,
        private readonly Calculator $calculator,
        private readonly Locales $locales,
        private readonly Repository $config,
        private readonly Translator $translator,
    ) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'cart://catalog',
                'Cart catalog',
                'What can go into a cart (the purchasable types and whether each takes options), the sources of every '
                .'link of the totals chain in the order they run, the currency, the limits and lifetimes, the fields of '
                .'a cart\'s state and of a line, and the warning codes with their phrases. Read it before cart_quote.',
                fn (): array => $this->catalog(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $locale = $this->locales->defaultCode();

        return [
            'types' => array_map(function (string $type): array {
                $provider = $this->purchasables->get($type);

                return [
                    'type' => $type,
                    'takes_options' => $provider instanceof ChecksOptions,
                    'price_by_options' => $provider instanceof ResolvesWithOptions,
                ];
            }, $this->purchasables->types()),
            'chain' => [
                'lines' => $this->sources($this->lines),
                'adjustments' => $this->sources($this->adjustments),
                'tenders' => $this->sources($this->tenders),
                'benefits' => $this->sources($this->benefits),
                'hints' => $this->sources($this->hints),
            ],
            'settings' => [
                'currency' => $this->currencies->currency(),
                'cookie_days' => $this->config->get('webx-cart.cookie.days'),
                'prune_after_days' => $this->config->get('webx-cart.prune_after_days'),
                'batch_max' => $this->config->get('webx-cart.batch_max'),
                'qty_max' => $this->config->get('webx-cart.qty_max'),
                'lines_max' => $this->config->get('webx-cart.lines_max'),
            ],
            'state_fields' => array_keys($this->calculator->empty($locale)->toArray()),
            'line_fields' => CartState::LINE_FIELDS,
            'warnings' => array_map(fn (string $code): array => [
                'code' => $code,
                'phrase' => (string) $this->translator->get('webx-cart::warnings.'.$code, [], $locale),
            ], Warning::CODES),
        ];
    }

    /**
     * @param  Sources<*>  $sources
     * @return list<array{source: class-string, priority: int}>
     */
    private function sources(Sources $sources): array
    {
        return array_map(static fn (array $entry): array => [
            'source' => $entry['source']::class,
            'priority' => $entry['priority'],
        ], $sources->describe());
    }
}
