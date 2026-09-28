<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Translation\Translator;
use WebxUi\Vacancies\Models\Vacancy;

/**
 * The numbers of a salary (decision 5): what the markup reads, what a card hands a template, and
 * the line a page prints when the editor wrote numbers and no words.
 *
 * The currencies are the site's list (§4.9), code => symbol. A currency taken out of the list does
 * not lock the vacancies that have it: they keep it, and its symbol is the code.
 */
final class Salary
{
    public function __construct(
        private readonly Config $config,
        private readonly Translator $translator,
    ) {}

    /**
     * The site's currencies, code => symbol, codes upper-case.
     *
     * @return array<string, string>
     */
    public function currencies(): array
    {
        $list = [];

        foreach ((array) $this->config->get('webx-vacancies.currencies', []) as $code => $symbol) {
            $code = strtoupper(trim((string) $code));

            if (preg_match('/^[A-Z]{3}$/', $code) === 1) {
                $list[$code] = is_string($symbol) && trim($symbol) !== '' ? trim($symbol) : $code;
            }
        }

        return $list;
    }

    /** The currency of a new vacancy: the first one of the list. */
    public function defaultCurrency(): ?string
    {
        $codes = array_keys($this->currencies());

        return $codes[0] ?? null;
    }

    public function symbol(string $code): string
    {
        return $this->currencies()[$code] ?? $code;
    }

    /**
     * The numbers, when there are enough of them to mean something: a currency, a unit and at
     * least one number. Null otherwise.
     *
     * @return array{min: float|null, max: float|null, unit: string, currency: string, symbol: string}|null
     */
    public function range(Vacancy $vacancy): ?array
    {
        $currency = is_string($vacancy->salary_currency) ? $vacancy->salary_currency : '';
        $unit = is_string($vacancy->salary_unit) ? $vacancy->salary_unit : '';
        $min = $vacancy->salary_min;
        $max = $vacancy->salary_max;

        if ($currency === '' || $unit === '' || ($min === null && $max === null)) {
            return null;
        }

        return [
            'min' => $min,
            'max' => $max,
            'unit' => $unit,
            'currency' => $currency,
            'symbol' => $this->symbol($currency),
        ];
    }

    /**
     * "40 000–60 000 ₴ per month", "from 3 000 $ per hour" — the numbers as words for a page that
     * has no words of the editor's. '' when the numbers say nothing.
     */
    public function line(Vacancy $vacancy, string $locale): string
    {
        $range = $this->range($vacancy);

        if ($range === null) {
            return '';
        }

        [$min, $max] = [$range['min'], $range['max']];

        $amount = match (true) {
            $min !== null && $max !== null && $min !== $max => self::amount($min).'–'.self::amount($max),
            $min !== null && $max === null => $this->word('salary-from', $locale, ['amount' => self::amount($min)]),
            $min === null && $max !== null => $this->word('salary-up-to', $locale, ['amount' => self::amount($max)]),
            default => self::amount((float) ($min ?? $max)),
        };

        return $this->word('salary-line', $locale, [
            'amount' => $amount,
            'symbol' => $range['symbol'],
            'unit' => $this->word('unit.'.$range['unit'], $locale),
        ]);
    }

    /** `40 000`, `12.5` — thousands apart, no cents where there are none. */
    public static function amount(float $amount): string
    {
        $decimals = floor($amount) === $amount ? 0 : 2;

        return number_format($amount, $decimals, '.', "\u{00A0}");
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function word(string $key, string $locale, array $replace = []): string
    {
        return (string) $this->translator->get('webx-vacancies::vacancy.'.$key, $replace, $locale);
    }
}
