<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Panel;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Localization\Locales;
use WebxUi\Tariffs\Currencies;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Variants;

/**
 * The editor's screen on the server side: what its fields hold, and what a save writes (§5.4).
 *
 * The screen is `tariffs.form`, keyed by field name, so what a tariff is made of is decided by the
 * description: a project's field arrives as a patch and is saved here by being on the screen at
 * all. What this class knows is which names are the tariff's own; `categories` goes through the
 * shared category code, and `services` is a `wx-relations` field sorted out by {@see ScreenRecord}
 * itself — absent altogether from a site without services, where a value sent under that name is
 * dropped like any field the screen does not have.
 *
 * What the screen cannot say — which row of the list, a currency or a look the config no longer
 * has but this tariff does — is checked here before the screen is, so that a refusal lands under
 * the name the form looks for it by: `price`, `currency`, `button_variant`, `button_link`,
 * `features.<n>.text` with the row numbered as the editor sees them, empty ones included.
 *
 * No draft (decision 10): a save is what the site shows, at once. In one transaction: a refusal
 * leaves no row behind.
 */
final class TariffForm
{
    /**
     * The tariff's own fields.
     *
     * @var list<string>
     */
    private const OWN = [
        'name', 'badge', 'price', 'currency', 'period', 'price_text', 'features', 'description',
        'button_label', 'button_link', 'button_variant', 'featured', 'published',
    ];

    /**
     * The screen's fields stored beside the tariff rather than in it.
     *
     * @var list<string>
     */
    private const TAKEN = ['categories'];

    /** A price is below this: the column holds eight digits before the point. */
    public const PRICE_LIMIT = 100_000_000;

    /** The longest line of "what is included". */
    public const FEATURE_MAX = 255;

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly Locales $locales,
        private readonly ConnectionInterface $db,
        private readonly Currencies $currencies,
        private readonly Variants $variants,
    ) {}

    /**
     * A tariff and the values of its screen — what `GET`, `POST` and `PUT` answer.
     *
     * @return array{tariff: array<string, mixed>, values: array<string, mixed>}
     */
    public function describe(Tariff $tariff): array
    {
        return [
            'tariff' => [
                'id' => (int) $tariff->getKey(),
                'name' => TariffNames::of($tariff, $this->locales),
                'published' => $tariff->published,
                'deleted_at' => $tariff->deleted_at?->toAtomString(),
            ],
            'values' => $this->values($tariff),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(Tariff $tariff): array
    {
        return [
            // The project's fields first, so that none of them can stand in for one of the
            // tariff's own.
            ...($tariff->extraRaw() ?? []),
            'name' => $tariff->getTranslations('name'),
            'badge' => $tariff->getTranslations('badge'),
            'price' => $tariff->price === null ? null : (float) $tariff->price,
            'currency' => $tariff->currency,
            'period' => $tariff->getTranslations('period'),
            'price_text' => $tariff->getTranslations('price_text'),
            'features' => $tariff->featureRows(),
            'description' => $tariff->getTranslations('description'),
            'button_label' => $tariff->getTranslations('button_label'),
            'button_link' => $tariff->button_link,
            'button_variant' => $tariff->button_variant,
            'featured' => $tariff->featured,
            'published' => $tariff->published,
            'categories' => $tariff->categories()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all(),
            // Only when the screen draws it: on a site without services there is no such field.
            ...$this->record->relationValues(Tariff::SCREEN, $tariff),
        ];
    }

    /**
     * Check what came in against the screen and write it — a new tariff or an existing one, the
     * panel's door and an agent's alike.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Tariff $tariff, array $input, ?callable $can = null): Tariff
    {
        $input = $this->check($tariff, $input);

        $split = $this->record->split(Tariff::SCREEN, $input, self::OWN, self::TAKEN, $can);

        return $this->db->transaction(function () use ($tariff, $split): Tariff {
            foreach ($split->own as $field => $value) {
                $this->write($tariff, $field, $value);
            }

            // A new tariff starts in the site's first currency (decision 15): the form opens
            // with nothing chosen, and a price without a currency prints as a bare number.
            if (! $tariff->exists && $tariff->currency === null) {
                $tariff->currency = $this->currencies->first();
            }

            $this->checkName($tariff);

            if ($split->extra !== []) {
                $tariff->setAttribute('extra', $this->record->merge(Tariff::SCREEN, $tariff->extraRaw(), $split->extra));
            }

            $tariff->save();

            if (array_key_exists('categories', $split->taken)) {
                $categories = $split->taken['categories'];

                // Through the shared code, which keeps the tariff's place inside a group it was
                // already in and gives it one at the end of a group it was not.
                $tariff->syncCategories(is_array($categories) ? array_values(array_map(intval(...), $categories)) : []);
            }

            $this->record->saveRelations($tariff, $split);

            return $tariff->refresh();
        });
    }

    /**
     * What the screen cannot check, checked first — and the input tidied for the screen: a
     * currency or a look this tariff already has, taken out of the config since, is kept as it is
     * and never shown to the check of the select (decisions 11 and 15); empty rows of the list are
     * dropped.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function check(Tariff $tariff, array $input): array
    {
        $errors = [];

        if (array_key_exists('price', $input)) {
            $problem = $this->priceProblem($input['price']);

            if ($problem !== null) {
                $errors['price'] = [$problem];
            }
        }

        foreach ([
            'currency' => [$this->currencies->has(...), $tariff->currency, 'webx-tariffs::errors.currency', $this->currencies->keys()],
            'button_variant' => [$this->variants->has(...), $tariff->button_variant, 'webx-tariffs::errors.variant', $this->variants->keys()],
        ] as $field => [$known, $stored, $message, $keys]) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $value = is_string($input[$field]) ? trim($input[$field]) : $input[$field];

            if ($value === null || $value === '') {
                $input[$field] = null;
            } elseif (is_string($value) && ! $known($value) && $value === $stored) {
                unset($input[$field]);
            } elseif (! is_string($value) || ! $known($value)) {
                $errors[$field] = [(string) __($message, ['keys' => implode(', ', $keys)])];
            } else {
                $input[$field] = $value;
            }
        }

        if (array_key_exists('features', $input) && is_array($input['features'])) {
            [$input['features'], $rowErrors] = $this->features($input['features']);
            $errors = [...$errors, ...$rowErrors];
        }

        if ($this->labelled($tariff, $input) && ! $this->linked($tariff, $input)) {
            $errors['button_link'] = [(string) __('webx-tariffs::errors.button-link')];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $input;
    }

    /**
     * A price is a number from zero up to the column's limit with at most two digits after the
     * point — 19.99, never 19.995, which the column would round behind the editor's back. Nothing
     * is no price: the words instead of one are printed.
     */
    private function priceProblem(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return (string) __('webx-tariffs::errors.price');
        }

        $price = (float) $value;
        $cents = $price * 100;

        if ($price < 0 || $price >= self::PRICE_LIMIT || abs($cents - round($cents)) > 1e-6) {
            return (string) __('webx-tariffs::errors.price');
        }

        return null;
    }

    /**
     * The rows of "what is included", tidied: a row written in no language is an empty row, not a
     * mistake, and is dropped; a line longer than a line is a 422 under the row, numbered as the
     * editor sees the rows, empty ones included.
     *
     * @param  array<array-key, mixed>  $rows
     * @return array{0: list<mixed>, 1: array<string, list<string>>}
     */
    private function features(array $rows): array
    {
        $kept = [];
        $errors = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                $kept[] = $row;

                continue;
            }

            $text = $row['text'] ?? null;
            $map = is_array($text) ? $text : [$this->locales->current() => $text];
            $words = [];

            foreach ($map as $locale => $line) {
                if (is_string($line) && trim($line) !== '') {
                    $words[(string) $locale] = trim($line);
                }
            }

            if ($words === []) {
                continue;
            }

            foreach ($words as $line) {
                if (mb_strlen($line) > self::FEATURE_MAX) {
                    $errors["features.{$index}.text"] = [(string) __('webx-tariffs::errors.feature-length', ['max' => self::FEATURE_MAX])];
                }
            }

            $kept[] = [...$row, 'text' => $words];
        }

        return [$kept, $errors];
    }

    /**
     * Whether the button will have a label in some language after this save: what came in laid
     * over what the tariff has, the way {@see translate()} writes it.
     *
     * @param  array<string, mixed>  $input
     */
    private function labelled(Tariff $tariff, array $input): bool
    {
        $labels = $tariff->getTranslations('button_label');

        if (array_key_exists('button_label', $input)) {
            $value = $input['button_label'];
            $labels = [...$labels, ...(is_array($value) ? $value : [$this->locales->current() => $value])];
        }

        foreach ($labels as $label) {
            if (is_string($label) && trim($label) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the button will lead somewhere after this save. A link without a label passes — the
     * site prints no button then — but a label without a link is a button to nowhere.
     *
     * @param  array<string, mixed>  $input
     */
    private function linked(Tariff $tariff, array $input): bool
    {
        $link = array_key_exists('button_link', $input) ? $input['button_link'] : $tariff->button_link;

        return is_array($link) && ! Link::fromArray($link)->isEmpty();
    }

    private function write(Tariff $tariff, string $field, mixed $value): void
    {
        switch ($field) {
            case 'published':
            case 'featured':
                $tariff->setAttribute($field, (bool) $value);
                break;
            case 'price':
                $tariff->price = is_numeric($value) ? round((float) $value, 2) : null;
                break;
            case 'currency':
            case 'button_variant':
                $tariff->setAttribute($field, is_string($value) && $value !== '' ? $value : null);
                break;
            case 'button_link':
                $tariff->button_link = is_array($value) && $value !== [] ? $value : null;
                break;
            case 'features':
                $tariff->features = $this->storedFeatures($value);
                break;
            default:
                $this->translate($tariff, $field, $value);
        }
    }

    /**
     * The rows as the repeater stored them, each with only the languages that have words.
     *
     * @return list<array<string, mixed>>|null
     */
    private function storedFeatures(mixed $value): ?array
    {
        $rows = [];

        foreach (is_array($value) ? $value : [] as $row) {
            $text = is_array($row) && is_array($row['text'] ?? null) ? $row['text'] : [];
            $words = array_filter(
                array_map(static fn (mixed $line): string => is_string($line) ? trim($line) : '', $text),
                static fn (string $line): bool => $line !== '',
            );

            if ($words !== []) {
                $rows[] = [...$row, 'text' => $words];
            }
        }

        return $rows === [] ? null : $rows;
    }

    /**
     * The one required field: a name in the default language — what the tariff is called on a
     * page nobody translated, and in the panel's list. Under the language, so the form shows it
     * under the field and on the chip of that language.
     *
     * @throws ValidationException
     */
    private function checkName(Tariff $tariff): void
    {
        $default = $this->locales->defaultCode();

        if ($tariff->textIn('name', $default) !== '') {
            return;
        }

        throw ValidationException::withMessages([
            "name.{$default}" => (string) __('webx-tariffs::errors.name-required'),
        ]);
    }

    /**
     * A translated field travels as its whole map from the form and as one string from an agent
     * speaking one language; neither is ever written over the whole field. A language the editor
     * emptied comes back as `''` or null and is taken away; a language nobody mentioned is a
     * language nobody meant to delete.
     */
    private function translate(Tariff $tariff, string $field, mixed $value): void
    {
        if (! in_array($field, Tariff::WORDS, true)) {
            return;
        }

        $map = is_array($value) ? $value : [$this->locales->current() => $value];
        $translations = [...$tariff->getTranslations($field), ...$map];

        $translations = array_map(
            static fn (mixed $text): mixed => is_string($text) ? trim($text) : $text,
            array_filter($translations, static fn (mixed $text): bool => is_string($text) && trim($text) !== ''),
        );

        $tariff->setTranslations($field, $translations);
    }
}
