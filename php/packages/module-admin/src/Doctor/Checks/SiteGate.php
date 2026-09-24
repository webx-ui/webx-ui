<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Support\Env;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Gate\Credentials;

/**
 * Whether the password over the site is what the person deploying thinks it is.
 *
 * Both ways it goes wrong are silent. A `config/webx-admin.php` published before the gate has no
 * `gate` key, so `WEBX_SITE_GATE=true` closes nothing and the site is open to everyone; and a gate
 * switched on with no pairs lets nobody in, which looks like a broken site rather than a locked
 * one. Said at every deploy while it is on, because a site left closed after launch is the third.
 */
final class SiteGate implements Check
{
    public function __construct(private readonly Credentials $credentials) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        if (! $this->credentials->configured()) {
            // Most sites that published the config before the gate never want one, and a warning
            // on each of their deploys would stop every `--strict` one. Only the site that asked
            // for it and did not get it is told — and told loudly, since it believes it is closed.
            // The environment is read directly because the config is exactly what is missing.
            return filter_var(Env::get('WEBX_SITE_GATE') ?? false, FILTER_VALIDATE_BOOLEAN)
                ? [Diagnosis::fail(
                    'Site password',
                    'WEBX_SITE_GATE is on, but the published config/webx-admin.php has no `gate` block, so the site is open to everyone — copy the block from the package\'s config.',
                )]
                : [];
        }

        if (! $this->credentials->enabled()) {
            return [];
        }

        $count = count($this->credentials->pairs());

        if ($count === 0) {
            return [Diagnosis::fail(
                'Site password',
                'WEBX_SITE_GATE is on and WEBX_SITE_GATE_USERS names nobody, so the site lets no one in — add `name:password` pairs or switch it off.',
            )];
        }

        // A line and not a warning: `--strict` stops a deploy on warnings, and a site in testing is
        // deployed closed on purpose.
        return [Diagnosis::ok(
            'Site password',
            'the site is closed to everyone but '.$count.' '.($count === 1 ? 'pair' : 'pairs').' from WEBX_SITE_GATE_USERS — switch it off when the site opens.',
        )];
    }
}
