<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\CategoryException;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Tariffs\Currencies;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Models\TariffCategory;
use WebxUi\Tariffs\Panel\TariffForm;
use WebxUi\Tariffs\Panel\TariffList;
use WebxUi\Tariffs\Panel\TariffNames;
use WebxUi\Tariffs\Variants;

/**
 * What an agent can do with the tariffs (§5.5).
 *
 * The same doors the panel uses. The list is {@see TariffList}, so the order is the one the editor
 * drags; the values go through {@see TariffForm}, which checks them against the described screen —
 * the price, the rows of the list, a label without a link and a field a project patched onto
 * `tariffs.form` are refused where the panel would refuse them; the order is {@see Ordering}, the
 * code behind the drag, whole or inside one group.
 *
 * An agent writes the tariff in its own shapes, and this class turns them into the screen's: the
 * rows of "what is included" as plain strings or maps of languages (never `{ text }`), the button
 * as one `{ label, link, variant }`, groups by title, services by address. A currency or a look
 * the site does not have is refused here, **before** the form, with the keys the site has: the
 * form's own refusal is written for a reader of the panel, and an agent needs the keys it can send
 * back.
 *
 * A tariff is named by its id: it has no page and no slug, and "Starter" is a name two sites'
 * worth of tariffs share.
 */
final class TariffTools
{
    /** The fields that take one language as a string or every language as a map. */
    private const TRANSLATED = ['name', 'badge', 'period', 'price_text', 'description', 'button_label'];

    /** The kind of record the `services` field links to. */
    private const SERVICE = 'service';

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $tariff = [
            'type' => ['integer', 'string'],
            'description' => 'The tariff\'s id — tariffs_list and tariffs://catalog have them.',
        ];
        $group = [
            'type' => ['integer', 'string'],
            'description' => 'A group: its id, or its title in any language. tariff_groups_list and tariffs://catalog have both.',
        ];
        $text = [
            'type' => ['string', 'object'],
            'description' => 'One language as a string, or every language as { "en": "…", "ru": "…" }.',
        ];
        $currencies = implode(', ', $this->currencies()->keys());
        $variants = implode(', ', $this->variants()->keys());

        $fields = [
            'name' => $text + ['description' => 'The tariff\'s name — "Combo Starter". Needed in the default language; a language without it shows that one.'],
            'badge' => $text + ['description' => 'A short line over the name — "30 HOURS / 25$". Shown in the default language when not translated.'],
            'price' => [
                'type' => ['number', 'null'],
                'description' => 'The price as a number, at most two digits after the point: 750, 19.99, 0. Null for none — then price_text is printed instead.',
            ],
            'currency' => [
                'type' => ['string', 'null'],
                'description' => "The currency, by its code — one of: {$currencies}. Where its symbol stands is the site's template's business. A new tariff without one gets the first.",
            ],
            'period' => $text + ['description' => 'Printed after the price — "/mo", "a year". Shown in the default language when not translated.'],
            'price_text' => $text + ['description' => 'The words printed when there is no number — "On request", "Free". Shown in the default language when not translated.'],
            'features' => [
                'type' => 'array',
                'items' => ['type' => ['string', 'object']],
                'description' => 'What the tariff includes, one line each, in order: a string (the default language) or { "en": "SEO", "ru": "SEO" }. '
                    .'A line not written in a language is left out of the list there. The whole list is replaced; an empty one clears it.',
            ],
            'description' => $text + ['description' => 'A paragraph under the list: plain text, line breaks kept, no HTML. Shown only in the languages it is written in.'],
            'button' => [
                'type' => ['object', 'null'],
                'properties' => [
                    'label' => $text + ['description' => 'What the button says. A language without it leaves the button out there; the tariff stays.'],
                    'link' => [
                        'type' => ['string', 'object'],
                        'description' => 'Where it goes: an address ("/contacts", or a full one elsewhere — a path without its language prefix), '
                            .'or an entity of this site in the form menu_add_link takes: { "target": "entity", "entity_type": "page", "entity_id": 3 }. '
                            .'The object also takes url, hash, new_tab and rel.',
                    ],
                    'variant' => ['type' => 'string', 'description' => "How it looks — one of: {$variants}. The site's first one when never set."],
                ],
                'description' => 'The one button of the card. A key left out keeps what the tariff has; null takes the button away. '
                    .'A label needs a link; a link without a label prints no button.',
            ],
            'featured' => ['type' => 'boolean', 'description' => 'Recommended: the card stands out among the others, with the label the block gives it.'],
            'categories' => ['type' => 'array', 'items' => $group, 'description' => 'Groups ("For business"): ids or titles. A tariff may be in several, or in none. The whole list is replaced.'],
        ];

        // Decision 4: without services there is no field to write them into, so no argument either.
        if ($this->serviceTarget() instanceof RelationTarget) {
            $fields[Tariff::SERVICES] = [
                'type' => 'array',
                'items' => ['type' => ['integer', 'string']],
                'description' => 'The services this tariff is for: ids or addresses ("/services/seo"), as services_list gives them. '
                    .'A tariffs block on a service\'s page can show only its own. The whole list is replaced; an empty one clears it.',
            ];
        }

        return [
            Tool::read(
                'list',
                'The tariffs in the order the site shows them: name, the price in one line, whether each is published '
                .'or recommended, its groups. Narrowed to a group, the list is in that group\'s own order, which is not '
                .'the order of the whole list. Read this (or tariffs://catalog) first: the same tariff typed in twice '
                .'is a duplicate.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'search' => ['type' => 'string', 'description' => 'Tariffs whose name, badge or description contains this, in any language the site has.'],
                    'group' => $group + ['description' => 'Only the tariffs in this group, in its order: its id or its title.'],
                    'trashed' => ['type' => 'boolean', 'description' => 'What is in the bin, newest first.'],
                ]],
                permission: ['tariffs.view', 'tariffs.manage'],
            ),

            Tool::read(
                'get',
                'One tariff in full: the values of its editor — the words in every language, the price and currency, '
                .'the rows of the list, the button, the groups, the services, whether it is published and any field '
                .'the project added.',
                fn (array $arguments): array => $this->get($arguments),
                ['properties' => ['tariff' => $tariff], 'required' => ['tariff']],
                permission: ['tariffs.view', 'tariffs.manage'],
            ),

            Tool::mutating(
                'create',
                'Add a tariff at the end of the list. It is not on the site until published — pass published: true only '
                .'when a person asked for that. Once published, it is in every tariffs block that shows its groups.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->create($arguments, $user)),
                ['properties' => [
                    ...$fields,
                    'published' => ['type' => 'boolean', 'description' => 'On the site at once. False when omitted.'],
                    'values' => ['type' => 'object', 'description' => 'The fields the project added to the editor, as tariffs_get returns them.'],
                ], 'required' => ['name']],
                permission: 'tariffs.manage',
            ),

            Tool::mutating(
                'update',
                'Change a tariff — any of name, badge, price, currency, period, price_text, features, description, button, '
                .'featured, categories, published, '
                .($this->serviceTarget() instanceof RelationTarget ? 'services, ' : '')
                .'and the fields the project added. A field left out keeps what it had, and so does a language left out '
                .'of a translated field: send "" for a language to take it away. It is on the site at once: tariffs have '
                .'no draft.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    'tariff' => $tariff,
                    'values' => ['type' => 'object', 'description' => 'Field name → value, in the shapes tariffs_create takes: '
                        .'translated fields a string (the default language) or { "en": "…" }; features a list of strings or maps; '
                        .'button { label, link, variant } or null; categories ids or titles.'],
                ], 'required' => ['tariff', 'values']],
                permission: 'tariffs.manage',
            ),

            Tool::mutating(
                'delete',
                'Put a tariff in the bin. It leaves every tariffs block at once; the bin in the panel brings it back to its places.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['tariff' => $tariff], 'required' => ['tariff']],
                permission: 'tariffs.manage',
            ),

            Tool::mutating(
                'reorder',
                'Put tariffs in a new order. Without a group it is the order of the whole list; with one it is the order '
                .'inside that group only, and every other group keeps its own. Name the tariffs in the order they should '
                .'stand in: they trade the places they hold among themselves, and the ones you leave out stay where they are.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorder($arguments)),
                ['properties' => [
                    'tariffs' => ['type' => 'array', 'items' => $tariff, 'description' => 'The tariffs, first to last.'],
                    'group' => $group + ['description' => 'The group whose own order this is. The whole list when omitted.'],
                ], 'required' => ['tariffs']],
                permission: 'tariffs.manage',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $query = [
            'search' => (string) ($arguments['search'] ?? ''),
            'trashed' => ($arguments['trashed'] ?? false) === true ? '1' : '0',
        ];

        $group = $this->group($arguments['group'] ?? null);

        if ($group !== null) {
            $query['category'] = (string) $group->getKey();
        }

        // The panel's own query: no pages, because the order is only an order when the whole of
        // it is in view.
        $tariffs = $this->container->make(TariffList::class)->build(Request::create('/', 'GET', $query))->get();

        return [
            'locales' => $this->locales()->codes(),
            'default_locale' => $this->locales()->defaultCode(),
            'order' => $group === null ? 'the whole list' : 'the order of this group',
            'count' => $tariffs->count(),
            'tariffs' => $tariffs->map(fn (Tariff $tariff): array => $this->summary($tariff))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments): array
    {
        $tariff = $this->tariff($arguments['tariff'] ?? null);

        return [
            'tariff' => $this->summary($tariff),
            'values' => $this->form()->values($tariff),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
        $values = $arguments['values'] ?? [];

        if (! is_array($values)) {
            throw new ToolFailure('`values` must be an object of field name → value.');
        }

        // The named arguments win over the same names in `values`: they are what the tool says it
        // takes, and an agent that sent both meant the one it could see.
        $values = [
            ...$values,
            'name' => $this->text($arguments['name'] ?? null, 'name'),
            'published' => ($arguments['published'] ?? false) === true,
        ];

        foreach (['badge', 'period', 'price_text', 'description'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $values[$field] = $this->text($arguments[$field], $field, empty: true);
            }
        }

        foreach (['price', 'currency', 'features', 'button', 'featured', 'categories', Tariff::SERVICES] as $field) {
            if (array_key_exists($field, $arguments)) {
                $values[$field] = $arguments[$field];
            }
        }

        $values = $this->prepare($values);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_create' => [
                    'name' => $values['name'],
                    'price' => $values['price'] ?? null,
                    'currency' => $values['currency'] ?? $this->currencies()->first(),
                    'features' => count($values['features'] ?? []),
                    'published' => $values['published'],
                    'categories' => $values['categories'] ?? [],
                    'services' => $values[Tariff::SERVICES] ?? [],
                ],
            ];
        }

        $tariff = $this->form()->save(new Tariff, $values, $this->can($user));

        return $this->get(['tariff' => $tariff->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $tariff = $this->tariff($arguments['tariff'] ?? null);
        $values = $arguments['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new ToolFailure('`values` must be a non-empty object of field name → value. tariffs_get says what the fields are.');
        }

        if ($tariff->trashed()) {
            throw new ToolFailure("Tariff #{$tariff->getKey()} is in the bin. Bring it back in the panel before editing it.");
        }

        foreach (self::TRANSLATED as $field) {
            // A plain string is the default language, as in tariffs_create — not the language of
            // a request that never chose one.
            if (is_string($values[$field] ?? null)) {
                $values[$field] = [$this->locales()->defaultCode() => $values[$field]];
            }
        }

        $values = $this->prepare($values, $tariff);

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'fields' => array_keys($values), 'tariff' => $this->reference($tariff)];
        }

        $this->form()->save($tariff, $values, $this->can($user));

        return $this->get(['tariff' => $tariff->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $tariff = $this->tariff($arguments['tariff'] ?? null);

        if ($tariff->trashed()) {
            return ['trashed' => true, 'id' => (int) $tariff->getKey(), 'already' => true];
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_trash' => $this->reference($tariff), 'published' => $tariff->published];
        }

        $tariff->delete();

        return ['trashed' => true, 'id' => (int) $tariff->getKey()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $given = $arguments['tariffs'] ?? null;

        if (! is_array($given) || $given === []) {
            throw new ToolFailure('`tariffs` is the list of tariff ids in their new order.');
        }

        $ids = array_values(array_unique(array_map(fn (mixed $one): int => (int) $this->tariff($one)->getKey(), $given)));
        $group = $this->group($arguments['group'] ?? null);

        if ($group !== null) {
            // Only the tariffs the group holds: `item_position` lives on the link, and a tariff
            // that is not in the group has no place in its order to be given.
            $inside = $group->tariffs()->pluck('tariffs.id')->map(intval(...))->all();
            $outside = array_values(array_diff($ids, $inside));

            if ($outside !== []) {
                throw new ToolFailure(sprintf(
                    'Not in this group: #%s. File them into it with tariffs_update first, or leave them out.',
                    implode(', #', $outside),
                ));
            }
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_order' => $ids, 'group' => $group?->getKey()];
        }

        Ordering::move(Tariff::class, $ids, $group === null ? null : (int) $group->getKey());

        return $this->list($group === null ? [] : ['group' => $group->getKey()]);
    }

    /**
     * What the screen takes, from what an agent is likely to send: the rows of the list become
     * `{ text }`, the button becomes its three fields, group titles and service addresses become
     * ids, and a currency or a look the site does not have is refused with the keys it has.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function prepare(array $values, ?Tariff $tariff = null): array
    {
        if (array_key_exists('button', $values)) {
            $values = [...$values, ...$this->button($values['button'], $tariff)];
            unset($values['button']);
        }

        if (array_key_exists('currency', $values)) {
            $values['currency'] = $this->currency($values['currency'], $tariff);
        }

        if (array_key_exists('button_variant', $values)) {
            $values['button_variant'] = $this->variant($values['button_variant'], $tariff);
        }

        if (array_key_exists('price', $values) && $values['price'] !== null && ! is_int($values['price']) && ! is_float($values['price'])
            && ! (is_string($values['price']) && is_numeric($values['price']))) {
            throw new ToolFailure('`price` is a number (750, 19.99), or null for none.');
        }

        if (array_key_exists('features', $values)) {
            $values['features'] = $this->features($values['features']);
        }

        if (array_key_exists('categories', $values)) {
            $values['categories'] = $this->groupIds($values['categories']);
        }

        if (array_key_exists(Tariff::SERVICES, $values)) {
            $given = $values[Tariff::SERVICES];

            if (! is_array($given)) {
                throw new ToolFailure('`services` is a list of service ids or addresses. An empty list clears it.');
            }

            $values[Tariff::SERVICES] = array_values(array_unique(array_map(fn (mixed $one): int => $this->service($one), $given)));
        }

        return $values;
    }

    /**
     * The button as the screen's three fields. A key the agent left out is left out here too, so
     * the tariff keeps what it has; null takes every language of the label away with the link.
     *
     * @return array<string, mixed>
     */
    private function button(mixed $given, ?Tariff $tariff): array
    {
        if ($given === null) {
            $labels = array_keys($tariff?->getTranslations('button_label') ?? []);

            return [
                'button_label' => array_fill_keys($labels, ''),
                'button_link' => null,
                'button_variant' => null,
            ];
        }

        if (! is_array($given) || array_is_list($given)) {
            throw new ToolFailure('`button` is { "label": …, "link": …, "variant": … }, or null to take the button away.');
        }

        $fields = [];

        if (array_key_exists('label', $given)) {
            $fields['button_label'] = $this->text($given['label'], 'button.label', empty: true);
        }

        if (array_key_exists('link', $given)) {
            $fields['button_link'] = $this->link($given['link']);
        }

        if (array_key_exists('variant', $given)) {
            $fields['button_variant'] = $given['variant'];
        }

        return $fields;
    }

    /**
     * A link as the link field takes it. An address is the shorthand; an object is the form
     * menu_add_link takes, and its target may be left for the fields it carries to say.
     *
     * @return array<string, mixed>|null
     */
    private function link(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return Link::fromArray(['target' => 'url', 'url' => trim($value)])->toArray();
        }

        if (! is_array($value) || $value === []) {
            throw new ToolFailure('`button.link` is an address, or { "target": "entity", "entity_type": "page", "entity_id": 3 }.');
        }

        $value['target'] ??= isset($value['entity_type']) || isset($value['entity_id']) ? 'entity' : 'url';

        return $value;
    }

    /**
     * A currency by its key. One this tariff already has, taken out of the config since, goes back
     * as it came — tariffs_get hands it out, and sending back what it gave must not be a refusal
     * (decision 15).
     */
    private function currency(mixed $value, ?Tariff $tariff): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $code = is_string($value) ? strtoupper(trim($value)) : null;

        if ($code === null || (! $this->currencies()->has($code) && $code !== $tariff?->currency)) {
            throw new ToolFailure(sprintf(
                'This site has no currency [%s]. It has: %s. A site adds one in its config (webx-tariffs.currencies).',
                is_scalar($value) ? (string) $value : gettype($value),
                implode(', ', $this->currencies()->keys()),
            ));
        }

        return $code;
    }

    /** A look by its key — the same rule as the currency (decision 11). */
    private function variant(mixed $value, ?Tariff $tariff): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $key = is_string($value) ? trim($value) : null;

        if ($key === null || (! $this->variants()->has($key) && $key !== $tariff?->button_variant)) {
            throw new ToolFailure(sprintf(
                'This site has no button look [%s]. It has: %s. A site adds one in its config (webx-tariffs.variants).',
                is_scalar($value) ? (string) $value : gettype($value),
                implode(', ', $this->variants()->keys()),
            ));
        }

        return $key;
    }

    /**
     * The rows of "what is included" as the repeater stores them. A string is the default
     * language; a map is every language of one line; `{ text }` — what tariffs_get hands out — goes
     * back as it came.
     *
     * @return list<array{text: array<string, string>}>
     */
    private function features(mixed $given): array
    {
        if ($given === null) {
            return [];
        }

        if (! is_array($given) || ! array_is_list($given)) {
            throw new ToolFailure('`features` is a list of lines: strings, or { "en": "…", "ru": "…" }.');
        }

        $rows = [];

        foreach ($given as $index => $line) {
            if (is_array($line) && array_key_exists('text', $line)) {
                $line = $line['text'];
            }

            $words = $this->text($line, sprintf('features[%d]', $index), empty: true);

            // An empty line is dropped, as the form drops it — but counted, so that the form's
            // refusal of a line still names the one the agent sent.
            $rows[] = ['text' => $words];
        }

        return $rows;
    }

    /**
     * One tariff as an agent needs it: every language of the name at once, the price in one line,
     * and where the words are — a tariff is shown in every language (decision 12), its description
     * only where it is written.
     *
     * @return array<string, mixed>
     */
    private function summary(Tariff $tariff): array
    {
        $locales = $this->locales();
        $tariff->loadMissing('categories');

        $summary = [
            'id' => (int) $tariff->getKey(),
            'name' => $tariff->getTranslations('name'),
            'badge' => $tariff->getTranslations('badge'),
            'price' => $tariff->price === null ? null : (float) $tariff->price,
            'currency' => $tariff->currency,
            'price_line' => PriceLine::of($tariff, $this->currencies(), $locales->current(), $locales->defaultCode()),
            'featured' => $tariff->featured,
            'published' => $tariff->published,
            'written_in' => self::writtenIn($tariff, $locales->codes()),
            'groups' => $tariff->categories
                ->map(static fn (TariffCategory $group): array => [
                    'id' => (int) $group->getKey(),
                    'title' => $group->getTranslations('title'),
                ])
                ->values()
                ->all(),
            'position' => (int) $tariff->position,
            'updated_at' => $tariff->updated_at?->toAtomString(),
        ];

        if ($this->serviceTarget() instanceof RelationTarget) {
            $summary[Tariff::SERVICES] = $tariff->relatedIds(Tariff::SERVICES);
        }

        if ($tariff->trashed()) {
            $summary['deleted_at'] = $tariff->deleted_at?->toAtomString();
        }

        return $summary;
    }

    /**
     * The languages the name and the description are written in: the name falls back to the
     * default language, the description does not (decision 12) — the second list is where a page
     * in another language comes out shorter.
     *
     * @param  list<string>  $codes
     * @return array{name: list<string>, description: list<string>}
     */
    public static function writtenIn(Tariff $tariff, array $codes): array
    {
        $in = static fn (string $field): array => array_values(array_filter(
            $codes,
            static fn (string $code): bool => $tariff->textIn($field, $code) !== '',
        ));

        return ['name' => $in('name'), 'description' => $in('description')];
    }

    private function reference(Tariff $tariff): string
    {
        return sprintf('#%s (%s)', $tariff->getKey(), TariffNames::of($tariff, $this->locales()));
    }

    private function tariff(mixed $reference): Tariff
    {
        if (! is_int($reference) && ! (is_string($reference) && ctype_digit(ltrim(trim($reference), '#')))) {
            throw new ToolFailure('A tariff is its id — tariffs_list has them.');
        }

        $id = (int) ltrim(trim((string) $reference), '#');
        $tariff = Tariff::withTrashed()->find($id);

        return $tariff instanceof Tariff
            ? $tariff
            : throw new ToolFailure("No tariff has the id [{$id}].");
    }

    /**
     * A group by id or by its title in any language — groups have no slug to go by.
     */
    private function group(mixed $reference): ?TariffCategory
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $group = TariffCategory::query()->find((int) $reference);
        } elseif (is_string($reference)) {
            $group = TariffCategory::query()->whereTranslationLikeAny('title', trim($reference))->first();
        } else {
            throw new ToolFailure('A group is an id or a title.');
        }

        return $group instanceof TariffCategory
            ? $group
            : throw new ToolFailure('No such group. tariff_groups_list says what there is.');
    }

    /**
     * Ids, checked before the save rather than left to it: `wx-categories` would drop an id it
     * does not know without a word, and an agent would think the tariff was filed.
     *
     * @return list<int>
     */
    private function groupIds(mixed $given): array
    {
        if (! is_array($given)) {
            throw new ToolFailure('`categories` is a list of group ids or titles.');
        }

        return array_values(array_unique(array_map(
            fn (mixed $one): int => (int) ($this->group($one) ?? throw new ToolFailure('`categories` holds an empty value.'))->getKey(),
            $given,
        )));
    }

    /**
     * A service by id or by the address it answers at — the address is what an agent reads off
     * `services://catalog`, as for the team.
     */
    private function service(mixed $reference): int
    {
        $target = $this->serviceTarget();

        if (! $target instanceof RelationTarget) {
            throw new ToolFailure('This site has no services module, so a tariff cannot be linked to a service.');
        }

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $exists = ($target->model)::query()->whereKey((int) $reference)->exists();

            return $exists ? (int) $reference : throw new ToolFailure("No service has the id [{$reference}].");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure('A service in `services` is an id, or the address it answers at.');
        }

        $id = Route::query()
            ->where('path', UrlNormaliser::key($reference))
            ->where('kind', Route::CANONICAL)
            ->where('entity_type', (new ($target->model))->getMorphClass())
            ->value('entity_id');

        return is_numeric($id)
            ? (int) $id
            : throw new ToolFailure('No service answers at [/'.UrlNormaliser::key($reference).'].');
    }

    private function serviceTarget(): ?RelationTarget
    {
        return $this->container->make(RelationTargets::class)->find(self::SERVICE);
    }

    /**
     * Run a change, and turn a refusal into something the agent can read.
     *
     * @param  callable(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(callable $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        } catch (CategoryException $refused) {
            throw new ToolFailure($refused->getMessage());
        }
    }

    /**
     * A translated field as the form takes it: a map of languages. A plain string is the default
     * language — the form would read it as the language of the request, and an agent's request
     * has none it chose.
     *
     * @return array<string, string>
     */
    private function text(mixed $value, string $field, bool $empty = false): array
    {
        if (is_string($value)) {
            if (trim($value) !== '') {
                return [$this->locales()->defaultCode() => trim($value)];
            }

            return $empty ? [] : throw new ToolFailure("`{$field}` cannot be empty.");
        }

        if ($value === null && $empty) {
            return [];
        }

        if (! is_array($value) || array_is_list($value) && $value !== []) {
            throw new ToolFailure("`{$field}` is a string, or an object keyed by language code.");
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            if (is_string($text)) {
                // An empty language is kept as '': in an update it is how an agent takes one away.
                $texts[(string) $locale] = trim($text);
            }
        }

        if (array_filter($texts, static fn (string $text): bool => $text !== '') === [] && ! $empty) {
            throw new ToolFailure("`{$field}` cannot be empty.");
        }

        return $texts;
    }

    /**
     * The permission check the screen asks for a field behind one — the same question the panel
     * asks of its editor, so an agent cannot write a field its administrator could not.
     *
     * @return (callable(string): bool)|null
     */
    private function can(?Authenticatable $user): ?callable
    {
        return $user instanceof HasPermissions
            ? static fn (string $permission): bool => $user->hasPermission($permission)
            : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function form(): TariffForm
    {
        return $this->container->make(TariffForm::class);
    }

    private function currencies(): Currencies
    {
        return $this->container->make(Currencies::class);
    }

    private function variants(): Variants
    {
        return $this->container->make(Variants::class);
    }

    private function locales(): Locales
    {
        return $this->container->make(Locales::class);
    }
}
