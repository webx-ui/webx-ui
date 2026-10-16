<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Panel;

use WebxUi\Admin\AbstractModule;

/**
 * The section "Codes" (§7): the codes of outside services by place, dragged into order, edited
 * with highlighting, switched on and off.
 *
 * One permission, `snippets.manage` (§8): reading a code is reading somebody's service keys, and
 * writing one is running JavaScript on every page for every visitor — there is no harmless half
 * to give out. No role gets it when the module is installed, so only a super administrator sees
 * the section until somebody grants it on purpose.
 *
 * In the group `system`, beside the settings and SEO (decision 7): a rare, technical thing.
 */
final class SnippetsModule extends AbstractModule
{
    /** The id in the panel. */
    public const ID = 'snippets';

    public const PERMISSION = 'snippets.manage';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-snippets::module.title');
    }

    public function icon(): string
    {
        return 'file-html';
    }

    public function order(): int
    {
        return 850;
    }

    public function group(): string
    {
        return 'system';
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return [self::PERMISSION];
    }
}
