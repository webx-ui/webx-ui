<?php

declare(strict_types=1);

namespace WebxUi\Admin\Setup;

/**
 * What `webx:setup` does about the site's look (spec WEBX_UI_THEMES.md §14.1), decided before
 * anything is installed so that a run with a typo stops before `composer require`.
 *
 * Three outcomes. A new site gets a local `theme/` over a packaged theme — `theme-default`
 * unless `--theme` names another. A site that already stands on a theme keeps it, and the run
 * only republishes its files. A site with no theme stays that way: asked for by `--no-theme`,
 * or implied by a layout of its own in `resources/views`, which sits above every theme layer
 * and would hide whatever a theme brought — the site that has been live for months and runs
 * setup again to add a module is that site, and changing its look is not what it asked for.
 */
final readonly class ThemeChoice
{
    public const string DEFAULT = 'webx-ui/theme-default';

    /** Where the local theme goes, and therefore what `WEBX_THEME` says. */
    public const string DIRECTORY = 'theme';

    private function __construct(
        /** The packaged theme to install and stand `theme/` on; null when nothing is created. */
        public ?string $package,
        /** Whether the site ends up with a theme — `WEBX_THEME` written, files synced. */
        public bool $themed,
        public string $why,
    ) {}

    public static function decide(?string $option, bool $none, ?string $configured, bool $ownLayout): self
    {
        $option = $option === null || trim($option) === '' ? null : trim($option);
        $configured = $configured === null || trim($configured) === '' ? null : trim($configured);

        if ($none) {
            return new self(null, false, 'none — --no-theme: the layout and the styles are the site\'s own');
        }

        if ($configured !== null) {
            return new self(null, true, "WEBX_THEME={$configured} — kept as it is");
        }

        if ($option === null && $ownLayout) {
            return new self(null, false, 'none — the site has a layout of its own; --theme=… gives it one');
        }

        if ($option !== null && preg_match('~^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$~', $option) !== 1) {
            throw SetupFailed::badTheme($option);
        }

        $package = $option ?? self::DEFAULT;

        return new self($package, true, self::DIRECTORY."/ over {$package}");
    }

    /** What to hand `composer require`: our own packages share one version, a stranger's picks its own. */
    public function requirement(string $constraint): ?string
    {
        if ($this->package === null) {
            return null;
        }

        return str_starts_with($this->package, 'webx-ui/') ? $this->package.':'.$constraint : $this->package;
    }
}
