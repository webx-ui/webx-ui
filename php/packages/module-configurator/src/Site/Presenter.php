<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Site;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use WebxUi\Configurator\Engine\Engine;
use WebxUi\Configurator\Engine\State;
use WebxUi\Configurator\Pricing\Breakdown;
use WebxUi\Configurator\Pricing\Texts;
use WebxUi\Configurator\Schema\Validator;
use WebxUi\Inbox\Support\Forms;
use WebxUi\Localization\Locales;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Settings\Contacts\Channel;
use WebxUi\Settings\Contacts\Contacts;
use WebxUi\Widgets\Contacts\ContactsSource;

/**
 * Everything the view of a configurator prints (§12.2), worked out on the server: every step,
 * option and field with what is hidden at the start, the price and its breakdown, «from N» on the
 * cards, the picture, the request button and the messengers — and the widget's data.
 *
 * The server prints all of it and the script only switches (decision 9): the page without
 * JavaScript is the same page, with the price of where it started.
 */
final class Presenter
{
    public function __construct(
        private readonly Locales $locales,
        private readonly Container $app,
    ) {}

    /**
     * @param  array{id: int, key: string, title: array<string, string>, schema: array<string, mixed>, form: string|null, version: int|null}  $live
     * @param  array<string, mixed>  $preset  a block's choice over the schema's `initial`
     * @return array<string, mixed>
     */
    public function present(array $live, array $preset, ?string $title, string $id): array
    {
        $locale = app()->getLocale();
        $default = $this->locales->defaultCode();
        $schema = $live['schema'];
        $currency = is_string($schema['currency'] ?? null) ? $schema['currency'] : '';

        // Unknown keys of a preset are dropped quietly: the schema may have been rebuilt after
        // the block was saved (§12.1). Resolving does it — what is not a step or a field is not read.
        $initial = [...Engine::map($schema['initial'] ?? null), ...array_filter($preset, is_scalar(...))];
        $state = Engine::resolve($schema, [], $initial);
        $today = Carbon::today()->toDateString();
        $quote = Breakdown::describe($schema, Engine::price($schema, $state, $today), $locale, $default);
        $images = $this->images($schema, $locale);
        $form = $this->form($live['form']);
        $text = static fn (mixed $words): string => Texts::pick($words, $locale, $default);

        return [
            'id' => $id,
            'key' => $live['key'],
            'title' => $this->title($title, $live['title'], $locale, $default),
            'form' => $form,
            // The id the inbox gives the first form of this slug on the page — the one in the
            // dialog. The radios and the sizes belong to it by `form=`, so they are sent with the
            // visitor's contacts with and without JavaScript; the widget corrects it if the page
            // has another form of the same slug before the dialog.
            'formId' => $form === null ? null : 'webx-inbox-'.$form,
            'steps' => $this->steps($schema, $state, $initial, $images, $text, $today, $currency, $locale),
            'preview' => $this->preview($schema, $state, $images),
            'price' => $this->price($quote, $schema, $currency, $locale, $text),
            'messengers' => $this->messengers($locale),
            'data' => [
                'key' => $live['key'],
                'locale' => $locale,
                'currency' => $currency,
                'initial' => $initial,
                'schema' => $this->localized($schema, $text),
                'images' => $images,
                'words' => [
                    'line' => (array) trans('webx-configurator::site.line', [], $locale),
                    'discount' => (string) trans('webx-configurator::site.discount', [], $locale),
                ],
            ],
        ];
    }

    /**
     * The block's heading: its own, or the configurator's name; `-` is none (§20.8).
     *
     * @param  array<string, string>  $own
     */
    private function title(?string $title, array $own, string $locale, string $default): ?string
    {
        $title = is_string($title) ? trim($title) : '';

        if ($title === '-') {
            return null;
        }

        if ($title !== '') {
            return $title;
        }

        $name = Texts::pick($own, $locale, $default);

        return $name !== '' ? $name : null;
    }

    /**
     * The request form, while the inbox has it switched on — without one there is no button.
     */
    private function form(?string $slug): ?string
    {
        if ($slug === null || ! class_exists(Forms::class)) {
            return null;
        }

        return $this->app->make(Forms::class)->enabled($slug) !== null ? $slug : null;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $initial
     * @param  array<string, array<string, mixed>>  $images
     * @param  callable(mixed): string  $text
     * @return list<array<string, mixed>>
     */
    private function steps(array $schema, State $state, array $initial, array $images, callable $text, string $today, string $currency, string $locale): array
    {
        $steps = [];

        foreach (Engine::steps($schema) as $step) {
            $key = (string) $step['key'];
            $type = is_string($step['type'] ?? null) ? $step['type'] : '';
            $choice = in_array($type, Engine::CHOICE_TYPES, true);
            $hidden = $choice ? ! isset($state->options[$key]) : in_array($key, $state->hidden, true);

            $one = [
                'key' => $key,
                'type' => $type,
                'title' => $text($step['title'] ?? null) ?: $text($step['label'] ?? null),
                'hint' => $text($step['hint'] ?? null),
                'hidden' => $hidden,
                'options' => [],
                'fields' => [],
            ];

            foreach ($choice ? Engine::options($step) : [] as $option) {
                $value = (string) $option['value'];
                $visible = in_array($value, $state->options[$key] ?? [], true);

                $one['options'][] = [
                    'value' => $value,
                    'label' => $text($option['label'] ?? null),
                    'subtitle' => $text($option['subtitle'] ?? null),
                    'hidden' => ! $visible,
                    'selected' => $state->choice($key) === $value,
                    'image' => is_string($option['image'] ?? null) ? ($images[$option['image']] ?? null) : null,
                    'swatch' => is_string($option['swatch'] ?? null) && Validator::swatch($option['swatch']) ? $option['swatch'] : null,
                    'swatchImage' => is_string($option['swatchImage'] ?? null) ? ($images[$option['swatchImage']]['src'] ?? null) : null,
                    // «from N» (§7.6): the option at its defaults, every other step as it starts —
                    // from the start and not from now, so the card does not jump while somebody
                    // picks a glass. Only on cards, and only for what is offered at the start.
                    'from' => $type === 'cards' && $visible
                        ? Money::format(Engine::price($schema, Engine::select($schema, $state, $key, $value, $initial), $today)->total, $currency, $locale)
                        : null,
                ];
            }

            foreach (Engine::fields($step) as $field) {
                $name = (string) $field['key'];
                $limits = $state->limits[$name] ?? [Engine::float($field['min'] ?? null) ?? 0.0, Engine::float($field['max'] ?? null) ?? 0.0];

                $one['fields'][] = [
                    'key' => $name,
                    'label' => $text($field['label'] ?? null),
                    'unit' => $text($field['unit'] ?? null),
                    'min' => Engine::number($limits[0]),
                    'max' => Engine::number($limits[1]),
                    'step' => Engine::number(Engine::float($field['step'] ?? null) ?? 1.0),
                    'value' => Engine::number(Engine::float($state->values[$name] ?? null) ?? $limits[0]),
                    'hidden' => ! isset($state->limits[$name]),
                ];
            }

            $steps[] = $one;
        }

        return $steps;
    }

    /**
     * The picture of the state (§10): the first rule whose `when` holds, else the picture of the
     * chosen option of the main step, else of the first visible step of cards; else none.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, array<string, mixed>>  $images
     * @return array<string, mixed>|null
     */
    private function preview(array $schema, State $state, array $images): ?array
    {
        $key = self::previewKey($schema, $state);

        return $key === null ? null : ($images[$key] ?? null);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function previewKey(array $schema, State $state): ?string
    {
        foreach (is_array($schema['previews'] ?? null) ? $schema['previews'] : [] as $rule) {
            if (is_array($rule) && is_string($rule['image'] ?? null) && is_array($rule['when'] ?? null)
                && Engine::visible(['when' => $rule['when']], $state->values, $state->options)) {
                return $rule['image'];
            }
        }

        $main = Engine::mainStep($schema);
        $chosen = Engine::chosenOptions($schema, $state);

        foreach ($chosen as [$step, $option]) {
            if ($main !== null && $step === $main) {
                return is_string($option['image'] ?? null) ? $option['image'] : null;
            }
        }

        if ($main !== null) {
            return null;
        }

        $types = [];

        foreach (Engine::steps($schema) as $step) {
            $types[(string) $step['key']] = $step['type'] ?? null;
        }

        foreach ($chosen as [$step, $option]) {
            if (($types[$step] ?? null) === 'cards') {
                return is_string($option['image'] ?? null) ? $option['image'] : null;
            }
        }

        return null;
    }

    /**
     * The total, the struck-out total and the label of a discount in force, the lines of the
     * breakdown in words, the area and the rounding.
     *
     * @param  array<string, mixed>  $quote
     * @param  array<string, mixed>  $schema
     * @param  callable(mixed): string  $text
     * @return array<string, mixed>
     */
    private function price(array $quote, array $schema, string $currency, string $locale, callable $text): array
    {
        $discount = null;

        foreach ($quote['lines'] as $line) {
            if ($line['kind'] === 'discount') {
                $label = $text(Engine::map($schema['discount'] ?? null)['label'] ?? null);
                $discount = $label !== '' ? $label : '−'.Breakdown::number((float) $line['rate'], $locale, money: false).'%';
            }
        }

        $round = (float) $quote['round'];

        return [
            'total' => Money::format((float) $quote['total'], $currency, $locale),
            'before' => $discount !== null && (float) $quote['total_before_discount'] !== (float) $quote['total']
                ? Money::format((float) $quote['total_before_discount'], $currency, $locale)
                : null,
            'discount' => $discount,
            'lines' => array_map(static fn (array $line): array => [
                'kind' => $line['kind'],
                'text' => (string) $line['line'],
                'amount' => Money::format((float) $line['amount'], $currency, $locale),
            ], $quote['lines']),
            'area' => (float) $quote['area'] > 0 ? Breakdown::number((float) $quote['area'], $locale, money: false) : null,
            'rounded' => $round > 1 ? Money::format($round, $currency, $locale) : null,
        ];
    }

    /**
     * The chats of the site's contacts (`contacts.phones[].messengers`, `contacts.messengers`) —
     * a visitor who would rather write than fill in a form writes from beside the price. Nothing
     * without `module-settings`, or with no messenger in it.
     *
     * @return list<array{kind: string, url: string, label: string, icon: string|null}>
     */
    private function messengers(string $locale): array
    {
        if (! class_exists(Contacts::class) || ! $this->app->bound(Contacts::class)) {
            return [];
        }

        return array_map(static fn (Channel $channel): array => [
            'kind' => $channel->kind,
            'url' => $channel->url,
            'label' => class_exists(ContactsSource::class) ? ContactsSource::brand($channel->kind) : ucfirst($channel->kind),
            'icon' => view()->exists('webx-widgets::icons.'.$channel->kind) ? 'webx-widgets::icons.'.$channel->kind : null,
        ], $this->app->make(Contacts::class)->chats($locale));
    }

    /**
     * Every picture of the schema, by its key: the address, a smaller one for `srcset`, the size
     * and the library's alt — one query of the library for the whole configurator (§12.6).
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, array<string, mixed>>
     */
    private function images(array $schema, string $locale): array
    {
        $keys = Validator::images($schema);

        if ($keys === [] || ! class_exists(MediaValues::class)) {
            return [];
        }

        $media = $this->app->make(MediaValues::class);
        $stored = array_map(static fn (string $key): array => ['path' => $key], $keys);
        $media->preload($stored);
        $images = [];

        foreach ($stored as $one) {
            $picture = $media->resolve($one, $locale);
            $src = is_string($picture['url'] ?? null) ? $picture['url'] : null;

            if ($src === null) {
                // A picture gone from the library is an empty place on the site, not a 500 (§3).
                continue;
            }

            $thumb = is_string($picture['thumb'] ?? null) && $picture['thumb'] !== $src ? $picture['thumb'] : null;
            $width = is_int($picture['width'] ?? null) ? $picture['width'] : null;

            $images[$one['path']] = [
                'src' => $src,
                'srcset' => $thumb !== null && $width !== null && $width > 320 ? "{$thumb} 320w, {$src} {$width}w" : null,
                'width' => $width,
                'height' => is_int($picture['height'] ?? null) ? $picture['height'] : null,
                'alt' => is_string($picture['alt'] ?? null) ? $picture['alt'] : '',
            ];
        }

        return $images;
    }

    /**
     * The schema in the page's language, for the widget: every text one string, the rest as it is
     * — the engine needs the conditions, the prices and the formulas, the visitor sees them all
     * anyway in what the configurator does.
     *
     * @param  array<string, mixed>  $schema
     * @param  callable(mixed): string  $text
     * @return array<string, mixed>
     */
    private function localized(array $schema, callable $text): array
    {
        $words = static function (array $item, array $keys) use ($text): array {
            foreach ($keys as $key) {
                if (array_key_exists($key, $item)) {
                    $item[$key] = $text($item[$key]);
                }
            }

            return $item;
        };

        $steps = [];

        foreach (Engine::steps($schema) as $step) {
            $step = $words($step, ['label', 'title', 'hint']);

            if (is_array($step['options'] ?? null)) {
                $step['options'] = array_map(static fn (mixed $option): mixed => is_array($option) ? $words($option, ['label', 'subtitle']) : $option, $step['options']);
            }

            if (is_array($step['fields'] ?? null)) {
                $step['fields'] = array_map(static fn (mixed $field): mixed => is_array($field) ? $words($field, ['label', 'unit', 'hint']) : $field, $step['fields']);
            }

            $steps[] = $step;
        }

        $schema['steps'] = $steps;

        if (is_array($schema['discount'] ?? null)) {
            $schema['discount'] = $words($schema['discount'], ['label']);
        }

        return $schema;
    }
}
