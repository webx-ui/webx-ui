<?php

declare(strict_types=1);

namespace WebxUi\Banners\Panel;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Models\Place;
use WebxUi\Banners\Places;
use WebxUi\Localization\Locales;

/**
 * The places as the panel and an agent change them (§5.6): the list, and a place of somebody's own
 * made, renamed and deleted.
 *
 * A declared place is refused everything here with a 422 under `place` — the panel's controller
 * answers 403 before it asks, and an agent reads the words. Everything addresses a place by its key:
 * for part of its life a declared place has no id.
 */
final class PlaceEditor
{
    /** The longest name of a place, per language. */
    private const TITLE_MAX = 120;

    public function __construct(
        private readonly Places $places,
        private readonly Locales $locales,
    ) {}

    /**
     * Every place, in the order of {@see Places::keys()}.
     *
     * @return list<array{id: int|null, key: string, title: string, declared: bool, layout: string, count: int, titles?: array<string, string>}>
     */
    public function all(): array
    {
        /** @var Collection<string, Place> $rows */
        $rows = Place::query()->withCount('banners')->get()->keyBy('key');
        $list = [];

        foreach ($this->places->keys() as $key) {
            $list[] = $this->describe($key, $rows->get($key));
        }

        return $list;
    }

    /**
     * @return array{id: int|null, key: string, title: string, declared: bool, layout: string, count: int, titles?: array<string, string>}
     */
    public function one(string $key): array
    {
        return $this->describe($key, Place::query()->withCount('banners')->where('key', $key)->first());
    }

    /**
     * A place of somebody's own: made straight away, unlike a declared one — nobody asked for this
     * key in a template, so the row is the only thing that says it exists at all.
     *
     * @throws ValidationException
     */
    public function create(mixed $key, mixed $title): Place
    {
        $key = is_string($key) ? trim($key) : '';

        if (preg_match(Place::KEY, $key) !== 1) {
            throw ValidationException::withMessages(['key' => (string) __('webx-banners::errors.place-key')]);
        }

        if ($this->places->exists($key)) {
            throw ValidationException::withMessages(['key' => (string) __('webx-banners::errors.place-taken')]);
        }

        $place = new Place(['key' => $key]);
        $this->title($place, $title);
        $place->save();

        return $place;
    }

    /** @throws ValidationException */
    public function rename(Place $place, mixed $title): Place
    {
        $this->refuseDeclared($place->key);
        $this->title($place, $title);
        $place->save();

        return $place;
    }

    /**
     * Only an empty place of somebody's own. The bin counts: the cascade would take it along, and
     * a banner somebody meant to bring back would be gone.
     *
     * @throws ValidationException
     */
    public function delete(Place $place): void
    {
        $this->refuseDeclared($place->key);

        $count = $this->bannersIn($place);

        if ($count > 0) {
            throw ValidationException::withMessages(['place' => $this->notEmpty($count)]);
        }

        $place->delete();
    }

    /**
     * The order inside a place, first to last, as the editor drags it and as an agent names it.
     * The banners left out keep their positions. All of it or none: an id that is not in this
     * place — or one in the bin — moves nothing.
     *
     * @param  list<int>  $ids
     *
     * @throws ValidationException
     */
    public function reorder(string $key, array $ids): void
    {
        $ids = array_values(array_unique($ids));
        $place = Place::query()->where('key', $key)->first();
        $own = $place === null ? [] : Banner::query()->where('place_id', $place->getKey())->pluck('id')->map(intval(...))->all();

        if (array_diff($ids, $own) !== []) {
            throw ValidationException::withMessages(['ids' => (string) __('webx-banners::errors.ids-foreign')]);
        }

        Banner::query()->getConnection()->transaction(static function () use ($ids): void {
            foreach ($ids as $position => $id) {
                Banner::query()->whereKey($id)->update(['position' => $position]);
            }
        });
    }

    /** Every banner of the place, the bin included. */
    public function bannersIn(Place $place): int
    {
        return Banner::query()->withTrashed()->where('place_id', $place->getKey())->count();
    }

    public function notEmpty(int $count): string
    {
        return (string) __('webx-banners::errors.place-not-empty', ['count' => $count]);
    }

    /**
     * @return array{id: int|null, key: string, title: string, declared: bool, layout: string, count: int, titles?: array<string, string>}
     */
    private function describe(string $key, ?Place $row): array
    {
        $declared = $this->places->isDeclared($key);
        $place = [
            'id' => $row === null ? null : (int) $row->getKey(),
            'key' => $key,
            'title' => $this->places->title($key, $row),
            'declared' => $declared,
            'layout' => $this->places->layout($key),
            'count' => $row === null ? 0 : (int) ($row->getAttribute('banners_count') ?? 0),
        ];

        // Every language of a name somebody wrote, so that renaming it sends them all back rather
        // than the one the list shows. A declared place's name is the config's, never written.
        if (! $declared && $row !== null) {
            $place['titles'] = $row->getTranslations('title');
        }

        return $place;
    }

    /** @throws ValidationException */
    private function refuseDeclared(string $key): void
    {
        if ($this->places->isDeclared($key)) {
            throw ValidationException::withMessages(['place' => (string) __('webx-banners::errors.place-declared')]);
        }
    }

    /**
     * A map of languages from the form, or one string — the default language, which is the one
     * the name is required in. Merged language by language, like every translated field.
     *
     * @throws ValidationException
     */
    private function title(Place $place, mixed $title): void
    {
        $default = $this->locales->defaultCode();
        $map = is_array($title) ? $title : [$default => $title];
        $translations = [...$place->getTranslations('title'), ...$map];
        $clean = [];

        foreach ($translations as $code => $words) {
            if (! is_string($words) || trim($words) === '') {
                continue;
            }

            if (mb_strlen(trim($words)) > self::TITLE_MAX) {
                throw ValidationException::withMessages([
                    "title.{$code}" => (string) __('webx-banners::errors.place-title-length', ['max' => self::TITLE_MAX]),
                ]);
            }

            $clean[(string) $code] = trim($words);
        }

        if (($clean[$default] ?? '') === '') {
            throw ValidationException::withMessages(["title.{$default}" => (string) __('webx-banners::errors.place-title')]);
        }

        $place->setTranslations('title', $clean);
    }
}
