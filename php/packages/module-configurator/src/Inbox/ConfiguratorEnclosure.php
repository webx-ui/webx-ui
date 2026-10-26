<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Inbox;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Configurator\Engine\Engine;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Pricing\Breakdown;
use WebxUi\Configurator\Pricing\Quoter;
use WebxUi\Configurator\Pricing\Texts;
use WebxUi\Configurator\Site\LiveConfigurators;
use WebxUi\Configurator\Site\Money;
use WebxUi\Inbox\Contracts\Enclosure;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Submissions\Enclosed;
use WebxUi\Localization\Locales;

/**
 * A configuration, enclosed in a request of the inbox (§11): the configurator by its key, the
 * values the visitor chose — and the price, counted here from the published schema. The request
 * carries no price, and one that did would not be read (decision 3).
 *
 * Only for the form the configurator names (`configurators.form`): a configuration fastened to
 * another form is refused, so nobody turns the contact form into an order form from the browser.
 */
final class ConfiguratorEnclosure implements Enclosure
{
    public const string NAME = 'configurator';

    public function __construct(
        private readonly LiveConfigurators $live,
        private readonly Quoter $quoter,
        private readonly Locales $locales,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    /** A form some configurator names — whether it is this request's configurator is `make()`'s. */
    public function accepts(Form $form, Request $request): bool
    {
        return Configurator::query()->where('form', $form->slug)->exists();
    }

    public function make(Form $form, Request $request): Enclosed
    {
        $key = $request->input(self::NAME.'.key');
        $live = is_string($key) ? $this->live->find($key) : null;

        if ($live === null || $live['form'] !== $form->slug) {
            throw ValidationException::withMessages([self::NAME.'.key' => (string) trans('webx-configurator::site.refused')]);
        }

        $values = $request->input(self::NAME.'.values');
        $values = is_array($values) ? array_filter($values, is_scalar(...)) : [];
        $locale = app()->getLocale();

        $quote = $this->quoter->quote($live['schema'], $values, locale: $locale);

        if (isset($quote['errors'])) {
            $errors = [];

            foreach ($quote['errors'] as $error) {
                $path = (string) ($error['path'] ?? '');

                // A size outside its limits is the visitor's to correct (§6.4); a schema that does
                // not price is the site's, and the visitor is told the configurator is unavailable.
                $errors[str_starts_with($path, 'values.') ? self::NAME.'.'.$path : self::NAME.'.key'][] = (string) ($error['message'] ?? trans('webx-configurator::site.refused'));
            }

            throw ValidationException::withMessages($errors);
        }

        return $this->enclose($live, $quote, $locale);
    }

    /**
     * The row of the request: the configuration in words — the letter's lines — and beside it the
     * structure the panel's card draws (§11.2), with the labels as they read today.
     *
     * @param  array{id: int, key: string, title: array<string, string>, schema: array<string, mixed>, form: string|null, version: int|null}  $live
     * @param  array<string, mixed>  $quote
     */
    private function enclose(array $live, array $quote, string $locale): Enclosed
    {
        $default = $this->locales->defaultCode();
        $schema = $live['schema'];
        $currency = (string) $quote['currency'];
        $title = Texts::pick($live['title'], $locale, $default) ?: $live['key'];
        $hidden = array_flip(array_map(strval(...), (array) $quote['hidden']));

        $dimensions = [];

        foreach (Engine::steps($schema) as $step) {
            foreach (Engine::fields($step) as $field) {
                $name = (string) $field['key'];

                if (isset($hidden[$name]) || ! array_key_exists($name, $quote['normalized'])) {
                    continue;
                }

                $dimensions[] = [
                    'key' => $name,
                    'label' => Texts::pick($field['label'] ?? null, $locale, $default) ?: $name,
                    'value' => $quote['normalized'][$name],
                    'unit' => Texts::pick($field['unit'] ?? null, $locale, $default),
                ];
            }
        }

        $discount = null;

        foreach ($quote['lines'] as $line) {
            if ($line['kind'] === 'discount') {
                $discount = [
                    'percent' => $line['rate'],
                    'amount' => $line['amount'],
                    'label' => Texts::pick(Engine::map($schema['discount'] ?? null)['label'] ?? null, $locale, $default),
                ];
            }
        }

        $payload = [
            'kind' => self::NAME,
            'configurator' => ['id' => $live['id'], 'key' => $live['key'], 'title' => $title],
            'version' => $live['version'],
            'values' => $quote['normalized'],
            'selection' => $quote['selection'],
            'dimensions' => $dimensions,
            'area' => $quote['area'],
            'currency' => $currency,
            'subtotal' => $quote['subtotal'],
            'total_before_discount' => $quote['total_before_discount'],
            'discount' => $discount,
            'total' => $quote['total'],
            'lines' => $quote['lines'],
        ];

        return new Enclosed($title, $this->text($payload, $locale), $payload);
    }

    /**
     * What people read — the letter, Telegram, the export, an agent (§11.2):
     *
     *     Конструкція: Кутова розсувна кабіна
     *     Висота: 2000 мм · Ширина: 900 мм
     *     Площа: 3,6 м²
     *     Знижка −10%: −2 920 ₴
     *     Разом: 26 280 ₴
     *
     * @param  array<string, mixed>  $payload
     */
    private function text(array $payload, string $locale): string
    {
        $currency = (string) $payload['currency'];
        $lines = [];

        foreach ($payload['selection'] as $one) {
            $lines[] = $one['label'].': '.$one['option'];
        }

        if ($payload['dimensions'] !== []) {
            $lines[] = implode(' · ', array_map(
                static fn (array $size): string => trim($size['label'].': '.Breakdown::number((float) $size['value'], $locale, money: false).' '.$size['unit']),
                $payload['dimensions'],
            ));
        }

        if ((float) $payload['area'] > 0) {
            $lines[] = trans('webx-configurator::site.area', [], $locale).': '.Breakdown::number((float) $payload['area'], $locale, money: false).' m²';
        }

        if (is_array($payload['discount'])) {
            $label = $payload['discount']['label'] !== '' ? $payload['discount']['label'] : trans('webx-configurator::site.discount', [], $locale);
            $lines[] = $label.' (−'.Breakdown::number((float) $payload['discount']['percent'], $locale, money: false).'%): '
                .Money::format((float) $payload['total_before_discount'], $currency, $locale).' → '.Money::format((float) $payload['total'], $currency, $locale);
        }

        $lines[] = trans('webx-configurator::site.total', [], $locale).': '.Money::format((float) $payload['total'], $currency, $locale);

        return implode("\n", $lines);
    }
}
