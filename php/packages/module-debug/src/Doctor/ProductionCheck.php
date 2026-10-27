<?php

declare(strict_types=1);

namespace WebxUi\Debug\Doctor;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Debug\DebugServiceProvider;

/**
 * `debug_page` open in production (§3.3 of the debug bar spec): said at every deploy while it is.
 *
 * A note and not a warning: a closed stand is measured on purpose, and `--strict` stopping its
 * every deploy would only teach people to drop the flag. Silent everywhere else — outside
 * production the switch means nothing, and in production without it there is nothing to say.
 */
final class ProductionCheck implements Check
{
    private const string SUBJECT = 'Debug';

    public function __construct(
        private readonly Application $app,
        private readonly Config $config,
    ) {}

    public function run(): array
    {
        if (! $this->app->environment('production') || ! DebugServiceProvider::openInProduction($this->config->get('webx-debug.mcp.production'))) {
            return [];
        }

        if (! (bool) $this->config->get('webx-debug.enabled', true)) {
            return [Diagnosis::note(self::SUBJECT, 'WEBX_DEBUG_MCP_IN_PRODUCTION is on, but WEBX_DEBUGBAR=false keeps debug_page from collecting anything.')];
        }

        return [Diagnosis::note(
            self::SUBJECT,
            'WEBX_DEBUG_MCP_IN_PRODUCTION is on: an agent with debug.view and the scope debug:read can measure the pages of this production site with debug_page (values masked, no bar). Switch it off when the measuring is done.',
        )];
    }
}
