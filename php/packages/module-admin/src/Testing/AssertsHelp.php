<?php

declare(strict_types=1);

namespace WebxUi\Admin\Testing;

use WebxUi\Admin\Help\HelpAudit;

/**
 * The test every package with a screen carries: a screen without help, or a field without a
 * description, is red (WEBX_UI_PANEL_HELP.md §6).
 *
 * The test boots the providers of its own `TestCase` — the core and the package — so a field of a
 * satellite that nobody described fails the satellite's test, and nobody has to keep count of who
 * owns what.
 *
 *     $this->assertScreensHaveHelp();
 */
trait AssertsHelp
{
    protected function assertScreensHaveHelp(): void
    {
        // In the monorepo only: the mirror of a package has no guides.
        $root = dirname(__DIR__, 5);
        $guides = $root.'/apps/docs/guide';

        $problems = $this->app->make(HelpAudit::class)->problems(is_dir($guides) ? $guides : null, strict: true);

        $this->assertSame([], $problems, "The help does not match the screens. See apps/docs/guide/panel-help.md:\n".implode("\n", $problems));
    }
}
