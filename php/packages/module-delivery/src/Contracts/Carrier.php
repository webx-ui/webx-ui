<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Contracts;

use WebxUi\Delivery\ConnectionResult;

/** A delivery carrier on the site. 'store' is the shop itself and is built in (§13.1). */
interface Carrier
{
    /** 'carrier-a': `[a-z0-9-]+`, at most 32 characters. */
    public function key(): string;

    public function title(string $locale): string;

    /** URL of the carrier's mark, shown beside its methods that have no icon of their own. */
    public function icon(): ?string;

    /**
     * ISO 3166-1 codes the carrier works in; an empty list — wherever the site's geography goes.
     *
     * @return list<string>
     */
    public function countries(): array;

    /**
     * Settings the panel draws and stores in delivery_carriers.settings (sender, defaults).
     *
     * @return list<array<string, mixed>>
     */
    public function settings(): array;

    /**
     * Secret fields stored encrypted in delivery_carriers.credentials (API key). Give each one a
     * `label` (words, or locale → words): without it the panel can only say «Access key».
     *
     * @return list<array<string, mixed>>
     */
    public function credentials(): array;

    /**
     * Calls the cheapest API method with these credentials; words for the panel on failure.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function testConnection(array $credentials): ConnectionResult;
}
