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
        // In the monorepo only: the mirror of a package has neither the list nor the guides.
        $root = dirname(__DIR__, 5);
        $guides = $root.'/apps/docs/guide';

        $problems = $this->app->make(HelpAudit::class)->problems(
            self::helpStillPending(),
            is_dir($guides) ? $guides : null,
            strict: true,
        );

        $this->assertSame([], $problems, "The help does not match the screens. See apps/docs/guide/panel-help.md:\n".implode("\n", $problems));
    }

    /**
     * What may go without a text for now: key, or `<key>#<id>` for one field, and why (§6.5). One
     * file for both halves: `scripts/help-coverage.test.mjs` reads it too. A wave strikes its keys
     * out in the commit that brings their texts, and the last one deletes the file.
     *
     * @return array<string, string>
     */
    private static function helpStillPending(): array
    {
        $path = dirname(__DIR__, 2).'/tests/help-pending.json';

        if (! is_file($path)) {
            return [];
        }

        /** @var array<string, string> */
        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
