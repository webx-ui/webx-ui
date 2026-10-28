<?php

declare(strict_types=1);

namespace WebxUi\Users\Social;

/**
 * The keys of one provider as the module reads them (spec §9.3): what the panel holds, and where
 * a field there is empty, what the site wrote in `.env` — the panel wins.
 */
final readonly class ProviderConfig
{
    /**
     * @param  array<string, mixed>  $options  the open settings of the panel
     * @param  array<string, mixed>  $credentials  the secrets of the panel, decrypted
     * @param  array<string, mixed>  $fallback  `.env`: `WEBX_USERS_<KEY>_<FIELD>`
     */
    public function __construct(
        public array $options = [],
        public array $credentials = [],
        public array $fallback = [],
    ) {}

    /** Options, then credentials, then the `.env` fallback; an empty string is no value. */
    public function get(string $name): mixed
    {
        foreach ([$this->options, $this->credentials, $this->fallback] as $source) {
            $value = $source[$name] ?? null;

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    public function string(string $name): string
    {
        $value = $this->get($name);

        return is_scalar($value) ? (string) $value : '';
    }

    public function has(string $name): bool
    {
        return $this->get($name) !== null;
    }
}
