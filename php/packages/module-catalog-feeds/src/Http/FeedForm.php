<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebxUi\CatalogFeeds\Build\FeedLabels;
use WebxUi\CatalogFeeds\Build\FeedProblem;
use WebxUi\CatalogFeeds\Build\FeedTrial;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Presets\FeedPreset;
use WebxUi\CatalogFeeds\Presets\FeedPresets;
use WebxUi\CatalogFeeds\Taxonomy\Taxonomies;
use WebxUi\Localization\Locales;

/**
 * A feed written from the panel: the fields checked, the language one of the site's, the currency
 * the site's, and what they make tried on real products (§8.1 of the feeds spec). A feed that does
 * not make a valid file is not kept.
 *
 * The key, the build's columns and `driver` are not among the fields: nothing a form sends reaches
 * them. A `driver` comes with the preset of a registered format, once, when a feed is created.
 */
final class FeedForm
{
    /** The fields a write may carry; a partial update leaves the others as they are. */
    public const FIELDS = [
        'name', 'preset', 'format', 'locale', 'head', 'item', 'foot', 'separator', 'filter',
        'stock_map', 'taxonomy', 'custom_labels', 'sources', 'options', 'schedule', 'schedule_at',
        'rebuild_on_change', 'is_active',
    ];

    /**
     * What changes the file. A write of anything else is not tried: a feed gone bad with the
     * catalogue can still be renamed or switched off.
     */
    private const TRIED = [
        'format', 'locale', 'currency', 'head', 'item', 'foot', 'separator', 'filter', 'stock_map',
        'taxonomy', 'custom_labels', 'sources', 'options',
    ];

    /** Written to the byte: the rest of a form is cleaned here, see {@see cleaned()}. */
    private const AS_TYPED = ['head', 'item', 'foot', 'separator'];

    public function __construct(
        private readonly Locales $locales,
        private readonly FeedTrial $trial,
        private readonly FeedPresets $presets,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(Feed $feed, array $input): FeedSaved
    {
        $this->fill($feed, $input);

        $proof = $feed->exists && ! $feed->isDirty(self::TRIED) ? null : $this->trial->check($feed);

        if ($proof?->problem instanceof FeedProblem) {
            throw ValidationException::withMessages([$proof->problem->field => [$proof->problem->message]]);
        }

        $feed->save();

        return new FeedSaved($feed->refresh(), $proof?->warnings() ?? []);
    }

    /**
     * The form's values on a feed, kept or not, without writing it: what a preview looks at.
     *
     * @param  array<string, mixed>  $input
     */
    public function fill(Feed $feed, array $input): void
    {
        $creating = ! $feed->exists;
        $input = $this->cleaned($input);
        $input = $creating ? $this->fromPreset($input) : $input;
        $driver = $creating ? $this->driver($input) : $feed->driver;

        Validator::make($input, [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'preset' => ['sometimes', 'nullable', 'string', 'max:32'],
            'format' => [$creating ? 'required' : 'sometimes', Rule::in(array_keys(Feed::FORMATS))],
            'locale' => ['sometimes', Rule::in($this->locales->codes())],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'head' => ['sometimes', 'nullable', 'string'],
            'item' => [$creating && $driver === null ? 'required' : 'sometimes', 'string'],
            'foot' => ['sometimes', 'nullable', 'string'],
            'separator' => ['sometimes', 'nullable', 'string', 'max:32'],
            'filter' => ['sometimes', 'array'],
            'stock_map' => ['sometimes', 'array'],
            'stock_map.*' => ['nullable', 'string', 'max:64'],
            'taxonomy' => ['sometimes', 'nullable', Rule::in(Taxonomies::KEYS)],
            'custom_labels' => ['sometimes', 'array', self::labels(...)],
            'sources' => ['sometimes', 'array', self::sources(...)],
            'options' => ['sometimes', 'array'],
            'schedule' => ['sometimes', Rule::in(Feed::SCHEDULES)],
            'schedule_at' => ['sometimes', 'nullable', 'date_format:H:i'],
            'rebuild_on_change' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ])->validate();

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $feed->{$field} = $input[$field];
            }
        }

        $feed->driver = $driver;
        $feed->locale ??= $this->locales->defaultCode();
        $feed->currency = $this->currency($input);

        // Empty means the format's own.
        if (($creating || array_key_exists('separator', $input)) && (string) ($input['separator'] ?? '') === '') {
            $feed->separator = Feed::separatorOf($feed->format);
        }
    }

    /**
     * What `TrimStrings` and `ConvertEmptyStringsToNull` do everywhere else. They are off for these
     * routes because they cannot spare the templates, and without them an empty string passes every
     * rule but `required`: Laravel does not run the others on it.
     *
     * The delimiter of a csv stays too: a tab is a delimiter, and trimmed it would turn into a comma.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    public function cleaned(array $input): array
    {
        $delimiter = $input['options']['delimiter'] ?? null;

        foreach ($input as $field => $value) {
            if (! in_array($field, self::AS_TYPED, true)) {
                $input[$field] = self::clean($value);
            }
        }

        if (is_string($delimiter) && $delimiter !== '') {
            $input['options']['delimiter'] = $delimiter;
        }

        return $input;
    }

    /**
     * A new feed takes a copy of its preset, and what the form sent wins (§9 of the feeds spec). Only
     * here does `preset` mean anything: on a feed it is a word about where the feed came from.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function fromPreset(array $input): array
    {
        $key = $input['preset'] ?? null;

        if ($key === null) {
            return $input;
        }

        $preset = is_string($key) ? $this->presets->find($key) : null;

        if (! $preset instanceof FeedPreset) {
            throw ValidationException::withMessages(['preset' => [(string) __('validation.in', ['attribute' => 'preset'])]]);
        }

        return [...$preset->values(), 'name' => $preset->name(), ...$input];
    }

    /**
     * From the preset itself: the values of a form are merged over it, and may say anything.
     *
     * @param  array<string, mixed>  $input
     */
    private function driver(array $input): ?string
    {
        $key = $input['preset'] ?? null;
        $driver = is_string($key) ? ($this->presets->find($key)?->values()['driver'] ?? null) : null;

        return is_string($driver) ? $driver : null;
    }

    /**
     * @param  Closure(string): mixed  $fail
     */
    private static function labels(string $attribute, mixed $value, Closure $fail): void
    {
        $problem = is_array($value) ? FeedLabels::problem($value) : null;

        if ($problem !== null) {
            $fail($problem);
        }
    }

    /**
     * `mpn` is the field of a product, or a property named by its code (§3.4).
     *
     * @param  Closure(string): mixed  $fail
     */
    private static function sources(string $attribute, mixed $value, Closure $fail): void
    {
        $mpn = is_array($value) ? ($value['mpn'] ?? ['type' => 'field']) : [];
        $type = is_array($mpn) ? ($mpn['type'] ?? null) : null;

        if ($type !== 'field' && ($type !== 'property' || ! is_string($mpn['property'] ?? null))) {
            $fail((string) __('webx-catalog-feeds::errors.source-type'));
        }
    }

    private static function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::clean(...), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        $value = Str::trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * The site's currency, whatever the feed held: prices are already in it, there is no rate to
     * convert by (§15, decision 1). Saving a feed is how it catches up after the site's changed.
     *
     * @param  array<string, mixed>  $input
     */
    private function currency(array $input): string
    {
        $site = Feed::siteCurrency();
        $asked = strtoupper(trim((string) ($input['currency'] ?? '')));

        if ($asked !== '' && $asked !== $site) {
            throw ValidationException::withMessages(['currency' => [
                $site === ''
                    ? (string) __('webx-catalog-feeds::errors.currency-unset')
                    : (string) __('webx-catalog-feeds::errors.currency', ['currency' => $site]),
            ]]);
        }

        return $site;
    }
}
