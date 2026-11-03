<?php

declare(strict_types=1);

namespace WebxUi\Cart;

use Illuminate\Contracts\Translation\Translator;
use InvalidArgumentException;
use WebxUi\Localization\Locales;

/**
 * Benefit keys modules offer to cart rules (decision 29 of the delivery spec): delivery registers
 * `shipping.paid_by_store`, and the action «benefit» of a rule offers it from a list instead of a
 * key typed by hand. The cart itself never reads the labels — a benefit is still a string in the
 * state, and a key nobody registered works all the same; the rules' panel marks it as one nobody
 * understands.
 *
 * Registered from the owner's `boot()` through `callAfterResolving(BenefitKeys::class)`.
 */
final class BenefitKeys
{
    private const string PATTERN = '/^[a-z0-9_]+(\.[a-z0-9_]+)*$/';

    /** @var array<string, array<string, string>|string> key → label by locale, or a lang key */
    private array $keys = [];

    public function __construct(
        private readonly Translator $translator,
        private readonly Locales $locales,
    ) {}

    /**
     * @param  array<string, string>|string  $label  locale → label, or a lang key 'webx-delivery::…'
     *
     * @throws InvalidArgumentException a key not of `[a-z0-9_.]`, an empty label, or a key registered twice
     */
    public function register(string $key, array|string $label): void
    {
        if (preg_match(self::PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("A benefit key is lower-case letters, digits, '_' and '.', like 'shipping.paid_by_store': '{$key}' given.");
        }

        if (isset($this->keys[$key])) {
            throw new InvalidArgumentException("The benefit key '{$key}' is registered already: two modules would each think it is theirs.");
        }

        if ($label === '' || $label === []) {
            throw new InvalidArgumentException("The benefit key '{$key}' needs a label to be offered by.");
        }

        $this->keys[$key] = $label;
    }

    public function has(string $key): bool
    {
        return isset($this->keys[$key]);
    }

    /** @return list<string> in registration order */
    public function keys(): array
    {
        return array_keys($this->keys);
    }

    /**
     * Key → label in this locale, in registration order. A label missing in the locale falls back
     * to the site's default language, then to any it has.
     *
     * @return array<string, string>
     */
    public function options(string $locale): array
    {
        $options = [];

        foreach ($this->keys as $key => $label) {
            $options[$key] = $this->label($label, $locale) ?? $key;
        }

        return $options;
    }

    /** @param  array<string, string>|string  $label */
    private function label(array|string $label, string $locale): ?string
    {
        if (is_string($label)) {
            $line = $this->translator->get($label, [], $locale);

            return is_string($line) ? $line : null;
        }

        foreach ([$locale, $this->locales->defaultCode()] as $code) {
            if (isset($label[$code]) && $label[$code] !== '') {
                return $label[$code];
            }
        }

        foreach ($label as $line) {
            if ($line !== '') {
                return $line;
            }
        }

        return null;
    }
}
