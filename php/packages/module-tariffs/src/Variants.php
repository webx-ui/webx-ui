<?php

declare(strict_types=1);

namespace WebxUi\Tariffs;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Admin\Screens\Tree;

/**
 * How the button of a tariff may look: `webx-tariffs.variants`, key → name.
 *
 * A copy of the banners' class, not a dependency on them (decision 11 of the tariffs spec): a
 * price card must not need banners for its button, and two users are too few to move it into
 * `module-admin` — the third one is the reason to.
 *
 * The key is stored with the button and is what the site's template turns into a class; the name
 * is only the panel's, a plain string or a translation key. The first one is the fallback of a
 * button whose variant was taken out of the list. Read on every call rather than kept.
 */
final class Variants
{
    public function __construct(private readonly Config $config) {}

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $variants = [];

        foreach ((array) $this->config->get('webx-tariffs.variants', []) as $key => $label) {
            if (is_string($key) && $key !== '' && is_string($label) && trim($label) !== '') {
                $variants[$key] = trim($label);
            }
        }

        return $variants;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function has(string $variant): bool
    {
        return array_key_exists($variant, $this->all());
    }

    /** The fallback: the first variant, or none when the site lists none. */
    public function first(): ?string
    {
        return $this->keys()[0] ?? null;
    }

    /**
     * The variants as the options of a `wx-select`. A name that is a translation key travels as
     * `trans::…`, so the screen says it in the language of whoever opens it rather than in the
     * one that was current when the provider booted.
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
