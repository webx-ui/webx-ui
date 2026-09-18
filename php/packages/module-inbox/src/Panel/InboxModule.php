<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Panel;

use WebxUi\Admin\AbstractModule;

/**
 * The section where submissions are dealt with.
 *
 * At the top level and first in it (§2.18): a panel with this module installed is a panel
 * somebody opens in the morning to see what came in overnight, and everything else — the
 * pages, the blocks, the settings — is what they do afterwards.
 *
 * Three permissions rather than the usual two, because reading and answering are not the same
 * job as building the form: `view` opens the section and the attachments, `update` moves a
 * submission along, `manage` changes what the forms ask (§13).
 */
final class InboxModule extends AbstractModule
{
    public function id(): string
    {
        return 'inbox';
    }

    public function title(): string
    {
        return (string) __('webx-inbox::module.title');
    }

    public function icon(): string
    {
        return 'mail';
    }

    public function order(): int
    {
        return 100;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['inbox.view', 'inbox.update', 'inbox.manage'];
    }
}
