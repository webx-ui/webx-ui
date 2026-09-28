<?php

declare(strict_types=1);

namespace WebxUi\Banners\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Links\Link;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Models\Place;
use WebxUi\Banners\Panel\BannerForm;
use WebxUi\Banners\Panel\BannerNames;
use WebxUi\Banners\Panel\PlaceEditor;
use WebxUi\Banners\Places;
use WebxUi\Banners\Variants;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Media\Models\MediaFile;

/**
 * What an agent can do with banners (§5.7 of the banners spec).
 *
 * The same doors the panel uses: the places are {@see PlaceEditor}, the values go through
 * {@see BannerForm} — the one save of the editor, checked against `banners.form`, so a field a
 * project patched on is refused where the panel would refuse it, and a refused create leaves
 * neither a banner nor the lazy row of a declared place behind.
 *
 * A place is named by its key — for part of its life a declared place has no id, and the key is
 * what a template asks for. A banner is named by its id: it has no page and no slug.
 *
 * What an agent types and the panel only offers is checked here, before the form, in words an
 * agent can act on: a library key that is not in the library, and a button look the site does not
 * have — with the list of those it has, by key.
 */
final class BannerTools
{
    /** The media fields of a banner: a library key each. */
    private const MEDIA = ['image', 'image_mobile', 'video'];

    /** The words of a banner: one language as a string or every language as a map. */
    private const WORDS = ['title', 'text'];

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $place = [
            'type' => 'string',
            'description' => 'The key of the place — "hero", as a template asks for it with banners(\'hero\'). banners_places lists them.',
        ];
        $banner = [
            'type' => ['integer', 'string'],
            'description' => 'The banner\'s id — banners_list and banners://catalog have them.',
        ];
        $text = [
            'type' => ['string', 'object'],
            'description' => 'One language as a string (the default one), or every language as { "en": "…", "ru": "…" }.',
        ];
        $variants = implode(', ', $this->variants()->keys());
        $media = static fn (string $what): array => [
            'type' => ['string', 'object', 'null'],
            'description' => "{$what} from the library: its key (\"media/ab/cd/spring.jpg\", as media_list_files gives it), "
                .'or { "path": "…", "alt": "…" }. Null takes it away.',
        ];
        $fields = [
            'image' => $media('The picture — required, the one field a banner cannot do without: the layouts stand on it, and it is the poster of the video. A picture'),
            'image_mobile' => $media('A narrower crop for phones, shown below the breakpoint instead of the picture. A picture'),
            'video' => $media('A silent video played over the picture on wide screens. A video'),
            'title' => $text + ['description' => 'The title. A banner with words is shown only in the languages its title or text is written in; one without words everywhere. There is no fallback language.'],
            'text' => $text + ['description' => 'A line or two under the title: plain text, line breaks kept, no HTML.'],
            'buttons' => [
                'type' => 'array',
                'maxItems' => BannerForm::MAX_BUTTONS,
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'label' => $text + ['description' => 'What the button says. A language without it leaves the button out there.'],
                        'link' => [
                            'type' => ['string', 'object'],
                            'description' => 'Where it goes: an address ("/booking", or a full one elsewhere — a path without its language prefix), '
                                .'or an entity of this site in the form menu_add_link takes: { "target": "entity", "entity_type": "page", "entity_id": 3 }. '
                                .'The object also takes url, hash, new_tab and rel.',
                        ],
                        'variant' => ['type' => 'string', 'description' => "How it looks — one of: {$variants}. The first one when omitted."],
                    ],
                    'required' => ['label', 'link'],
                ],
                'description' => 'Up to '.BannerForm::MAX_BUTTONS.' buttons, in the order the site prints them. The whole list is replaced: send every button the banner keeps; an empty list takes them all away.',
            ],
        ];

        return [
            Tool::read(
                'places',
                'The places banners stand in: the ones the site\'s config declares, which its templates ask for, and '
                .'the ones an administrator made. Each with its layout (single, random or slider) and how many banners '
                .'it holds. Banners reach the site only where a template asks for their place with banners(\'<key>\') — '
                .'there is no block to put on a page.',
                fn (): array => $this->places(),
                permission: ['banners.view', 'banners.manage'],
            ),

            Tool::mutating(
                'place_create',
                'Make a place of your own. A template has to ask for it by its key before anything in it is seen, so '
                .'this is for a site whose templates already do — the declared places need no making.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->placeCreate($arguments)),
                ['properties' => [
                    'key' => ['type' => 'string', 'description' => 'Lowercase Latin letters, digits and hyphens, starting with a letter; up to 64. Cannot be changed later.'],
                    'title' => $text + ['description' => 'Its name in the panel. Needed in the default language.'],
                ], 'required' => ['key', 'title']],
                permission: 'banners.manage',
            ),

            Tool::mutating(
                'place_delete',
                'Delete a place of your own. Only an empty one — the bin counts — and never a declared one: a template asks for that by its key.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->placeDelete($arguments)),
                ['properties' => ['place' => $place], 'required' => ['place']],
                permission: 'banners.manage',
            ),

            Tool::read(
                'list',
                'The banners of a place in the order the site shows them — or of every place, place by place. Each '
                .'with its title, whether it is on, the languages its words are written in and whether it has a '
                .'video. Read this (or banners://catalog) first: the same banner typed in twice is a duplicate.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'place' => $place + ['description' => 'Only this place. Every place when omitted.'],
                    'trashed' => ['type' => 'boolean', 'description' => 'What is in the bin instead, newest first.'],
                ]],
                permission: ['banners.view', 'banners.manage'],
            ),

            Tool::read(
                'get',
                'One banner in full: the values of its editor — the picture, the phone picture and the video by '
                .'library key, the title and text in every language, the buttons, whether it is on and any field the '
                .'project added.',
                fn (array $arguments): array => $this->get($arguments),
                ['properties' => ['banner' => $banner], 'required' => ['banner']],
                permission: ['banners.view', 'banners.manage'],
            ),

            Tool::mutating(
                'create',
                'Add a banner at the end of a place. It is off until turned on — pass enabled: true only when a person '
                .'asked for that: once on, it is on every page whose template asks for the place.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->create($arguments, $user)),
                ['properties' => [
                    'place' => $place,
                    ...$fields,
                    'enabled' => ['type' => 'boolean', 'description' => 'On the site at once. False when omitted.'],
                    'values' => ['type' => 'object', 'description' => 'The fields the project added to the editor, as banners_get returns them.'],
                ], 'required' => ['place', 'image']],
                permission: 'banners.manage',
            ),

            Tool::mutating(
                'update',
                'Change a banner — any of image, image_mobile, video, title, text, buttons, enabled and the fields the '
                .'project added — or move it to another place, where it stands last. A field left out keeps what it '
                .'had, and so does a language left out of title or text: send "" for a language to take it away. It is '
                .'on the site at once: banners have no draft.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->update($arguments, $user)),
                ['properties' => [
                    'banner' => $banner,
                    'values' => ['type' => 'object', 'description' => 'Field name → value, as banners_get returns them. Media take a library key; buttons a list of { label, link, variant }.'],
                    'place' => $place + ['description' => 'Move it to this place, at the end. Where it is when omitted.'],
                ], 'required' => ['banner']],
                permission: 'banners.manage',
            ),

            Tool::mutating(
                'delete',
                'Put a banner in the bin. It leaves the site at once; the bin in the panel brings it back to its place.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['banner' => $banner], 'required' => ['banner']],
                permission: 'banners.manage',
            ),

            Tool::mutating(
                'reorder',
                'Put the banners of a place in a new order — the one a slider shows them in, and the first of which a '
                .'single layout shows. Name them first to last; the ones you leave out keep their positions.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->reorder($arguments)),
                ['properties' => [
                    'place' => $place,
                    'banners' => ['type' => 'array', 'items' => $banner, 'description' => 'The banners of that place, first to last.'],
                ], 'required' => ['place', 'banners']],
                permission: 'banners.manage',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function places(): array
    {
        return [
            'layouts' => Places::LAYOUTS,
            'variants' => $this->variants()->keys(),
            'places' => array_map(
                static fn (array $place): array => array_diff_key($place, ['titles' => true]),
                $this->editor()->all(),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function placeCreate(array $arguments): array
    {
        $key = is_string($arguments['key'] ?? null) ? trim($arguments['key']) : '';
        $title = $this->text($arguments['title'] ?? null, 'title');

        if ($this->dryRun($arguments)) {
            if ($this->placesService()->exists($key)) {
                throw new ToolFailure("The place [{$key}] is already there.");
            }

            return ['dry_run' => true, 'would_create' => ['key' => $key, 'title' => $title]];
        }

        $place = $this->editor()->create($key, $title);

        return ['created' => true, 'place' => $this->editor()->one($place->key)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function placeDelete(array $arguments): array
    {
        $key = $this->place($arguments['place'] ?? null);

        if ($this->placesService()->isDeclared($key)) {
            throw new ToolFailure("The place [{$key}] is declared in the site's config: a template asks for it by its key, so it is not deleted here.");
        }

        $row = Place::query()->where('key', $key)->firstOrFail();
        $count = $this->editor()->bannersIn($row);

        if ($count > 0) {
            throw new ToolFailure(sprintf(
                'The place [%s] holds %d %s, the bin included. Move them to another place first with banners_update; one in the bin has to be restored in the panel before it can move.',
                $key,
                $count,
                $count === 1 ? 'banner' : 'banners',
            ));
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_delete' => $key];
        }

        $this->editor()->delete($row);

        return ['deleted' => true, 'place' => $key];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $trashed = ($arguments['trashed'] ?? false) === true;
        $keys = array_key_exists('place', $arguments) && $arguments['place'] !== null
            ? [$this->place($arguments['place'])]
            : $this->placesService()->keys();

        $rows = Place::query()->whereIn('key', $keys)->pluck('id', 'key');
        $banners = [];

        // Place by place in the order the places are listed, and inside a place in the order of
        // the site — the bin newest first, as the panel shows it.
        foreach ($keys as $key) {
            if (! $rows->has($key)) {
                continue;
            }

            $query = Banner::query()->with('place')->where('place_id', $rows->get($key));
            $query = $trashed
                ? $query->onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id')
                : $query->orderBy('position')->orderBy('id');

            foreach ($query->get() as $banner) {
                $banners[] = $this->summary($banner);
            }
        }

        return [
            'locales' => $this->locales()->codes(),
            'default_locale' => $this->locales()->defaultCode(),
            'count' => count($banners),
            'banners' => $banners,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments): array
    {
        $banner = $this->banner($arguments['banner'] ?? null);

        return [
            'banner' => $this->summary($banner),
            'values' => $this->form()->values($banner),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $user): array
    {
        $key = $this->place($arguments['place'] ?? null);
        $values = $arguments['values'] ?? [];

        if (! is_array($values)) {
            throw new ToolFailure('`values` must be an object of field name → value.');
        }

        // The named arguments win over the same names in `values`: they are what the tool says it
        // takes, and an agent that sent both meant the one it could see.
        $values = [...$values, 'enabled' => ($arguments['enabled'] ?? false) === true];

        foreach ([...self::MEDIA, ...self::WORDS, 'buttons'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $values[$field] = $arguments[$field];
            }
        }

        $values = $this->prepare($values);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_create' => [
                    'place' => $key,
                    'image' => $values['image']['path'] ?? null,
                    'title' => $values['title'] ?? [],
                    'buttons' => count($values['buttons'] ?? []),
                    'enabled' => $values['enabled'],
                ],
            ];
        }

        $banner = $this->form()->save(new Banner, $values, $key, $this->can($user));

        return $this->get(['banner' => $banner->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $user): array
    {
        $banner = $this->banner($arguments['banner'] ?? null);
        $values = $arguments['values'] ?? [];
        $key = array_key_exists('place', $arguments) && $arguments['place'] !== null
            ? $this->place($arguments['place'])
            : null;

        if (! is_array($values) || ($values === [] && $key === null)) {
            throw new ToolFailure('Send `values` — field name → value, as banners_get returns them — or a `place` to move the banner to.');
        }

        if ($banner->trashed()) {
            throw new ToolFailure("Banner #{$banner->getKey()} is in the bin. Bring it back in the panel before editing it.");
        }

        $values = $this->prepare($values, $banner);

        if ($this->dryRun($arguments)) {
            return array_filter([
                'dry_run' => true,
                'fields' => array_keys($values),
                'would_move_to' => $key !== null && $key !== $banner->place?->key ? $key : null,
                'banner' => $this->reference($banner),
            ], static fn (mixed $value): bool => $value !== null);
        }

        $this->form()->save($banner, $values, $key, $this->can($user));

        return $this->get(['banner' => $banner->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $banner = $this->banner($arguments['banner'] ?? null);

        if ($banner->trashed()) {
            return ['trashed' => true, 'id' => (int) $banner->getKey(), 'already' => true];
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_trash' => $this->reference($banner), 'enabled' => $banner->enabled];
        }

        $banner->delete();

        return ['trashed' => true, 'id' => (int) $banner->getKey()];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reorder(array $arguments): array
    {
        $key = $this->place($arguments['place'] ?? null);
        $given = $arguments['banners'] ?? null;

        if (! is_array($given) || $given === []) {
            throw new ToolFailure('`banners` is the list of ids of that place in their new order.');
        }

        $ids = [];

        foreach ($given as $one) {
            $banner = $this->banner($one);

            if ($banner->trashed() || $banner->place?->key !== $key) {
                throw new ToolFailure(sprintf(
                    'Banner #%d is not in the place [%s]%s. banners_list with that place says which are.',
                    $banner->getKey(),
                    $key,
                    $banner->trashed() ? ' (it is in the bin)' : '',
                ));
            }

            $ids[] = (int) $banner->getKey();
        }

        $ids = array_values(array_unique($ids));

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_order' => $ids];
        }

        $this->editor()->reorder($key, $ids);

        return $this->list(['place' => $key]);
    }

    /**
     * What the screen takes, from what an agent is likely to send: media named by their keys
     * become the values `wx-media` stores, a translated field sent as one string becomes the
     * default language, and the buttons become the rows of the repeater.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function prepare(array $values, ?Banner $banner = null): array
    {
        foreach (self::MEDIA as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = $this->media($values[$field]);
            }
        }

        foreach (self::WORDS as $field) {
            // A plain string is the default language — the form would read it as the language of
            // a request that never chose one.
            if (is_string($values[$field] ?? null)) {
                $values[$field] = [$this->locales()->defaultCode() => $values[$field]];
            }
        }

        if (array_key_exists('buttons', $values)) {
            $values['buttons'] = $this->buttons($values['buttons'], $banner);
        }

        return $values;
    }

    /**
     * A library key as `wx-media` stores it. `wx-media` lets a key the library does not have
     * through — the panel only offers keys it has; an agent types them, and a typo would be a
     * banner that never shows, without a word.
     *
     * @return array<string, mixed>|null
     */
    private function media(mixed $value): ?array
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $value = is_string($value) ? ['path' => trim($value)] : $value;
        $path = is_array($value) ? ($value['path'] ?? null) : null;

        if (! is_array($value) || ! is_string($path) || trim($path) === '') {
            throw new ToolFailure('A picture or a video is its library key — "media/ab/cd/spring.jpg" — or { "path": "…" }.');
        }

        if (! MediaFile::query()->where('path', $path)->exists()) {
            throw new ToolFailure("The library has no file [{$path}]. media_search_files finds one by name.");
        }

        return $value;
    }

    /**
     * The buttons as the repeater sends them. A look the site does not have is refused here with
     * the keys it has — the form names them the way a reader of the panel sees them. One this
     * banner already has, taken out of the config since, goes back as it came (decision 11):
     * banners_get hands it out, and sending back what it gave must not be a refusal.
     *
     * @return list<array<string, mixed>>
     */
    private function buttons(mixed $given, ?Banner $banner): array
    {
        if ($given === null) {
            return [];
        }

        if (! is_array($given) || ! array_is_list($given)) {
            throw new ToolFailure('`buttons` is a list of { "label": …, "link": …, "variant": … }, in the order the site prints them.');
        }

        if (count($given) > BannerForm::MAX_BUTTONS) {
            throw new ToolFailure(sprintf('A banner has at most %d buttons; %d were sent.', BannerForm::MAX_BUTTONS, count($given)));
        }

        $stored = array_filter(array_map(
            static fn (array $row): mixed => $row['variant'] ?? null,
            $banner?->buttonRows() ?? [],
        ), is_string(...));
        $rows = [];

        foreach ($given as $index => $row) {
            $number = $index + 1;

            if (! is_array($row)) {
                throw new ToolFailure("Button {$number} is an object: { \"label\": …, \"link\": …, \"variant\": … }.");
            }

            $variant = $row['variant'] ?? null;

            if ($variant !== null && (! is_string($variant) || (! $this->variants()->has($variant) && ! in_array($variant, $stored, true)))) {
                throw new ToolFailure(sprintf(
                    'Button %d: this site has no look [%s]. It has: %s. A site adds one in its config (webx-banners.variants).',
                    $number,
                    is_scalar($variant) ? (string) $variant : gettype($variant),
                    implode(', ', $this->variants()->keys()),
                ));
            }

            $rows[] = [
                'label' => $this->text($row['label'] ?? null, "buttons[{$index}].label"),
                'link' => $this->link($row['link'] ?? null, $number),
                'variant' => $variant,
            ];
        }

        return $rows;
    }

    /**
     * A link as the link field takes it. An address is the shorthand; an object is the form
     * menu_add_link takes, and its target may be left for the fields it carries to say.
     *
     * @return array<string, mixed>
     */
    private function link(mixed $value, int $number): array
    {
        if (is_string($value) && trim($value) !== '') {
            return Link::fromArray(['target' => 'url', 'url' => trim($value)])->toArray();
        }

        if (! is_array($value) || $value === []) {
            throw new ToolFailure("Button {$number} needs a link: an address, or { \"target\": \"entity\", \"entity_type\": \"page\", \"entity_id\": 3 }.");
        }

        $value['target'] ??= isset($value['entity_type']) || isset($value['entity_id']) ? 'entity' : 'url';

        return $value;
    }

    /**
     * One banner as an agent needs it: every language of the title at once, and where the words
     * are — a banner with words is shown only there (decision 12).
     *
     * @return array<string, mixed>
     */
    private function summary(Banner $banner): array
    {
        $summary = [
            'id' => (int) $banner->getKey(),
            'place' => $banner->place?->key,
            'title' => $banner->getTranslations('title'),
            'enabled' => $banner->enabled,
            'written_in' => $banner->wordLanguages(),
            'image' => $banner->mediaPath('image'),
            'has_video' => $banner->mediaPath('video') !== null,
            'buttons' => count($banner->buttonRows()),
            'position' => (int) $banner->position,
            'updated_at' => $banner->updated_at?->toAtomString(),
        ];

        if ($banner->trashed()) {
            $summary['deleted_at'] = $banner->deleted_at?->toAtomString();
        }

        return $summary;
    }

    private function reference(Banner $banner): string
    {
        return sprintf('#%s (%s)', $banner->getKey(), BannerNames::of($banner, $this->locales()));
    }

    private function banner(mixed $reference): Banner
    {
        if (! is_int($reference) && ! (is_string($reference) && ctype_digit(ltrim(trim($reference), '#')))) {
            throw new ToolFailure('A banner is its id — banners_list has them.');
        }

        $id = (int) ltrim(trim((string) $reference), '#');
        $banner = Banner::withTrashed()->with('place')->find($id);

        return $banner instanceof Banner
            ? $banner
            : throw new ToolFailure("No banner has the id [{$id}].");
    }

    /** A place's key, known to the config or the table. */
    private function place(mixed $key): string
    {
        $key = is_string($key) ? trim($key) : '';

        if ($key === '' || ! $this->placesService()->exists($key)) {
            throw new ToolFailure(sprintf(
                'There is no place [%s]. The site has: %s. banners_place_create makes one of your own.',
                $key,
                implode(', ', $this->placesService()->keys()),
            ));
        }

        return $key;
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
        }
    }

    /**
     * A translated value as the form takes it: a map of languages. A plain string is the default
     * language.
     *
     * @return array<string, string>
     */
    private function text(mixed $value, string $field): array
    {
        if (is_string($value)) {
            return trim($value) !== ''
                ? [$this->locales()->defaultCode() => trim($value)]
                : throw new ToolFailure("`{$field}` cannot be empty.");
        }

        if (! is_array($value)) {
            throw new ToolFailure("`{$field}` is a string, or an object keyed by language code.");
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            if (is_string($text) && trim($text) !== '') {
                $texts[(string) $locale] = trim($text);
            }
        }

        return $texts !== [] ? $texts : throw new ToolFailure("`{$field}` cannot be empty.");
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

    private function form(): BannerForm
    {
        return $this->container->make(BannerForm::class);
    }

    private function editor(): PlaceEditor
    {
        return $this->container->make(PlaceEditor::class);
    }

    private function placesService(): Places
    {
        return $this->container->make(Places::class);
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
