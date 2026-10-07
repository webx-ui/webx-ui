<?php

declare(strict_types=1);

namespace WebxUi\Banners;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Banners\Models\Place;
use WebxUi\Localization\Locales;

/**
 * The places of this site (§5.1, §5.3 of the banners spec): the ones the configuration declares,
 * the ones somebody made in the panel, and how each is laid out.
 *
 * A declared place is one the templates ask for by key. It is in the panel from the first day,
 * empty, and its row appears with its first banner ({@see row()}) — no write on boot and no
 * synchronise command to forget to run, as with the menus. A declared place taken out of the
 * configuration while its row lives on becomes an ordinary place of somebody's own: seen,
 * renamed, deleted when empty.
 *
 * Read on every call rather than kept: the places are the site's config, and a test or a site
 * that sets it after boot must be heard.
 */
final class Places
{
    /** The layouts a place may have. */
    public const LAYOUTS = ['single', 'random', 'slider'];

    /**
     * What `banners_layout()` answers when nothing overrides it. Every key is read against these
     * rather than out of the merged config: `mergeConfigFrom` merges one level, so a published
     * config that sets one option replaces the whole list (CLAUDE.md §4).
     */
    public const OPTIONS = [
        'interval' => 6000,
        'autoplay' => true,
        'loop' => true,
        'arrows' => true,
        'dots' => true,
        'pause_on_hover' => true,
        'ratio' => '16/6',
        'ratio_mobile' => '4/5',
        'breakpoint' => 768,
        'video_on_mobile' => false,
    ];

    public function __construct(
        private readonly Config $config,
        private readonly Locales $locales,
    ) {}

    /**
     * The places named in `webx-banners.places`, as they are written there — what is not a valid
     * key is not a place.
     *
     * @return array<string, array<string, mixed>>
     */
    public function declared(): array
    {
        $declared = [];

        foreach ((array) $this->config->get('webx-banners.places', []) as $key => $entry) {
            if (is_string($key) && preg_match(Place::KEY, $key) === 1) {
                $declared[$key] = is_array($entry) ? $entry : ['title' => (string) $entry];
            }
        }

        return $declared;
    }

    public function isDeclared(string $key): bool
    {
        return array_key_exists($key, $this->declared());
    }

    /** Whether a template may ask for this key: declared, or somebody made it. */
    public function exists(string $key): bool
    {
        return $this->isDeclared($key) || Place::query()->where('key', $key)->exists();
    }

    /**
     * Every place there is: the declared ones in the order they are written, then the rows of
     * somebody's own by their name in the panel's language.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = array_keys($this->declared());
        $own = [];

        foreach (Place::query()->get() as $place) {
            if (! in_array($place->key, $keys, true)) {
                $own[$place->key] = mb_strtolower($this->title($place->key, $place));
            }
        }

        asort($own, SORT_STRING);

        return [...$keys, ...array_keys($own)];
    }

    /**
     * The row behind a key, made now if this is the first time anybody needed one. Only for a
     * declared place: one of somebody's own is its row, and a key that is neither is nothing to
     * make a row for.
     */
    public function row(string $key): ?Place
    {
        $place = Place::query()->where('key', $key)->first();

        if ($place instanceof Place || ! $this->isDeclared($key)) {
            return $place;
        }

        return Place::query()->create(['key' => $key]);
    }

    /**
     * What a place is called in the panel: a declared one by the configuration (a string, or a
     * translation key read in the panel's language); one of somebody's own by its translation,
     * else the default language's, else its key.
     */
    public function title(string $key, ?Place $row = null): string
    {
        if ($this->isDeclared($key)) {
            $title = $this->declared()[$key]['title'] ?? null;

            return is_string($title) && trim($title) !== '' ? (string) __($title) : $key;
        }

        foreach (array_unique([$this->locales->content(), $this->locales->defaultCode()]) as $code) {
            $title = $row?->getTranslation('title', $code, fallback: false);

            if (is_string($title) && trim($title) !== '') {
                return trim($title);
            }
        }

        return $key;
    }

    /**
     * The layout of a place: its own when the configuration gives it a known one, else the
     * site's, else a slider.
     */
    public function layout(?string $key): string
    {
        $own = $key === null ? null : ($this->declared()[$key]['layout'] ?? null);

        foreach ([$own, $this->config->get('webx-banners.default_layout')] as $layout) {
            if (is_string($layout) && in_array($layout, self::LAYOUTS, true)) {
                return $layout;
            }
        }

        return 'slider';
    }

    /**
     * What `banners_layout()` answers: the package's options, laid over by the site's, then the
     * place's, plus the layout — the one asked for when it is a known one, else the place's.
     *
     * @return array<string, mixed>
     */
    public function options(?string $key, ?string $layout = null): array
    {
        $options = self::OPTIONS;
        $site = $this->config->get('webx-banners.options', []);
        $place = $key === null ? [] : ($this->declared()[$key]['options'] ?? []);

        foreach ([$site, $place] as $layer) {
            if (is_array($layer)) {
                foreach ($layer as $name => $value) {
                    $options[(string) $name] = $value;
                }
            }
        }

        $options['layout'] = $layout !== null && in_array($layout, self::LAYOUTS, true) ? $layout : $this->layout($key);

        return $options;
    }
}
