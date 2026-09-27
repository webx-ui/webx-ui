<?php

declare(strict_types=1);

namespace WebxUi\Team;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * The social networks a link may point at (§5.4 of the team spec): `webx-team.networks`, key → name.
 *
 * Read on every call rather than kept: the list is the site's config, and a test or a site that
 * sets it after boot must be heard. What is not a pair of two non-empty strings is not a network.
 */
final class Networks
{
    public function __construct(private readonly Config $config) {}

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $networks = [];

        foreach ((array) $this->config->get('webx-team.networks', []) as $key => $label) {
            if (is_string($key) && $key !== '' && is_string($label) && trim($label) !== '') {
                $networks[$key] = trim($label);
            }
        }

        return $networks;
    }

    /**
     * The networks as the options of a `wx-select`.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->all() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    public function has(string $network): bool
    {
        return array_key_exists($network, $this->all());
    }
}
