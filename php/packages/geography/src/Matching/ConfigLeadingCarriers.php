<?php

declare(strict_types=1);

namespace WebxUi\Geography\Matching;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * The leading carriers from `config('webx-geography.countries.<code>.source')`.
 *
 * A claim lives as long as the process: enough for the library on its own and for tests. A site
 * that syncs carriers has the delivery module, which binds {@see LeadingCarriers} to its
 * settings so that the first carrier's claim is remembered and can be changed in the panel.
 */
final class ConfigLeadingCarriers implements LeadingCarriers
{
    public function __construct(private readonly Config $config) {}

    public function leader(string $country): ?string
    {
        $source = $this->config->get($this->key($country));

        return is_string($source) && $source !== '' ? $source : null;
    }

    public function claim(string $country, string $carrier): string
    {
        $leader = $this->leader($country);

        if ($leader !== null) {
            return $leader;
        }

        $this->config->set($this->key($country), $carrier);

        return $carrier;
    }

    private function key(string $country): string
    {
        return 'webx-geography.countries.'.strtoupper($country).'.source';
    }
}
