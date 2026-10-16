<?php

declare(strict_types=1);

namespace WebxUi\Snippets;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Admin\Screens\Tree;

/**
 * The places a snippet may stand in (§4): the three built-in ones and whatever the config adds.
 *
 * Read from the config on every call, without a cache of its own: `config:cache` already is one,
 * and a test or a site that changes the list at runtime sees the change.
 */
final class Places
{
    /** The places theme-default prints, always there: the config may only change their words. */
    public const BUILT_IN = ['head', 'body_start', 'body_end'];

    /** A key of a place, the shape of a banner place's key. */
    public const KEY = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    public function __construct(private readonly Config $config) {}

    /**
     * Key → the words for it, built-in first, then the config's own in the order written.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $places = [];

        foreach (self::BUILT_IN as $key) {
            $places[$key] = 'webx-snippets::module.places.'.$key;
        }

        foreach ((array) $this->config->get('webx-snippets.places', []) as $key => $label) {
            if (! is_string($key) || preg_match(self::KEY, $key) !== 1) {
                continue;
            }

            $places[$key] = is_string($label) && trim($label) !== '' ? $label : $key;
        }

        return $places;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function builtIn(string $key): bool
    {
        return in_array($key, self::BUILT_IN, true);
    }

    /** The words for a place in the language of whoever reads them; the key of an undeclared one. */
    public function label(string $key): string
    {
        $label = $this->all()[$key] ?? $key;
        $translated = __($label);

        return is_string($translated) ? $translated : $label;
    }

    /**
     * The options of the place select on the form: a translation key goes through the screen's
     * `trans::`, plain words as they are (the way banners lay their variants over a select).
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->all() as $value => $label) {
            $translatable = str_contains($label, '::') || __($label) !== $label;
            $options[] = ['value' => $value, 'label' => $translatable ? Tree::TRANS.$label : $label];
        }

        return $options;
    }
}
