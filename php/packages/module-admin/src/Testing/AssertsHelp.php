<?php

declare(strict_types=1);

namespace WebxUi\Admin\Testing;

use WebxUi\Admin\Help\HelpAudit;
use WebxUi\Admin\Screens\ScreenRegistry;

/**
 * The test every package with a screen carries: a screen without help, or a field without a
 * description, is red (WEBX_UI_PANEL_HELP.md §6).
 *
 * The test boots the providers of its own `TestCase` — the core and the package — so a field of a
 * satellite that nobody described fails the satellite's test, and nobody has to keep count of who
 * owns what.
 *
 *     $this->assertScreensHaveHelp();
 *
 * A module of the site runs its tests on the site, with every installed package loaded, so it
 * asks about its own folder only (WEBX_UI_SITE_MODULES.md §10): the screens registered from a
 * file inside it, its `resources/help`, English and the languages of the site's panel.
 *
 *     $this->assertModuleScreensHaveHelp(dirname(__DIR__));
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

    /**
     * @param  string  $module  The folder of the module, `modules/<name>`.
     * @param  list<string>|null  $languages  Besides English; the site's `webx-localization.panel` when not given.
     */
    protected function assertModuleScreensHaveHelp(string $module, ?array $languages = null): void
    {
        if ($languages === null) {
            /** @var list<string> $languages */
            $languages = (array) $this->app->make('config')->get('webx-localization.panel', []);
        }

        $screens = $this->app->make(ScreenRegistry::class)->registeredFrom($module);
        $problems = $this->app->make(HelpAudit::class)->problemsOf($module.'/resources/help', $screens, $languages);

        $this->assertSame([], $problems, "The help of the module does not match its screens. See apps/docs/guide/panel-help.md:\n".implode("\n", $problems));
    }
}
