<?php

declare(strict_types=1);

namespace WebxUi\Tariffs;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * The currencies a tariff may be priced in (§4.3 of the tariffs spec): `webx-tariffs.currencies`,
 * ISO code → the symbol the site prints.
 *
 * A key that is not three capital letters is skipped: the column holds three. The first currency
 * is what a new tariff starts with. Read on every call rather than kept: the list is the site's
 * config, and a test or a site that sets it after boot must be heard.
 */
final class Currencies
{
    public function __construct(private readonly Config $config) {}

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $currencies = [];

        foreach ((array) $this->config->get('webx-tariffs.currencies', []) as $code => $symbol) {
            if (is_string($code) && preg_match('/^[A-Z]{3}$/', $code) === 1 && is_string($symbol) && trim($symbol) !== '') {
                $currencies[$code] = trim($symbol);
            }
        }

        return $currencies;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function has(string $code): bool
    {
        return array_key_exists($code, $this->all());
    }

    /** What a new tariff starts with: the first currency, or none when the site lists none. */
    public function first(): ?string
    {
        return $this->keys()[0] ?? null;
    }

    /**
     * What the site prints for a currency: its symbol, or the code itself for one taken out of
     * the list (decision 15) — "750 CHF" is still a price. '' for no currency at all.
     */
    public function symbol(?string $code): string
    {
        if ($code === null || $code === '') {
            return '';
        }

        return $this->all()[$code] ?? $code;
    }

    /**
     * The currencies as the options of a `wx-select`: "USD — $". Built from the config and not
     * translated — a code and a symbol read the same in every language.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->all() as $code => $symbol) {
            $options[] = ['value' => $code, 'label' => $code === $symbol ? $code : "{$code} — {$symbol}"];
        }

        return $options;
    }
}
