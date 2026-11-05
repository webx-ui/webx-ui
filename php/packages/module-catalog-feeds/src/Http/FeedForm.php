<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Http;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebxUi\CatalogFeeds\Build\FeedProblem;
use WebxUi\CatalogFeeds\Build\FeedTrial;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\Localization\Locales;

/**
 * A feed written from the panel: the fields checked, the language one of the site's, the currency
 * the site's, and what they make tried on real products (§8.1 of the feeds spec). A feed that does
 * not make a valid file is not kept.
 *
 * The key, the build's columns and `driver` are not among the fields: nothing a form sends reaches
 * them.
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

    public function __construct(
        private readonly Locales $locales,
        private readonly FeedTrial $trial,
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

        Validator::make($input, [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'preset' => ['sometimes', 'nullable', 'string', 'max:32'],
            'format' => [$creating ? 'required' : 'sometimes', Rule::in(array_keys(Feed::FORMATS))],
            'locale' => ['sometimes', Rule::in($this->locales->codes())],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'head' => ['sometimes', 'nullable', 'string'],
            'item' => [$creating ? 'required' : 'sometimes', 'string'],
            'foot' => ['sometimes', 'nullable', 'string'],
            'separator' => ['sometimes', 'nullable', 'string', 'max:32'],
            'filter' => ['sometimes', 'array'],
            'stock_map' => ['sometimes', 'array'],
            'taxonomy' => ['sometimes', 'nullable', Rule::in(['google'])],
            'custom_labels' => ['sometimes', 'array'],
            'sources' => ['sometimes', 'array'],
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

        $feed->locale ??= $this->locales->defaultCode();
        $feed->currency = $this->currency($input);

        // Empty means the format's own.
        if (($creating || array_key_exists('separator', $input)) && (string) ($input['separator'] ?? '') === '') {
            $feed->separator = Feed::separatorOf($feed->format);
        }
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
