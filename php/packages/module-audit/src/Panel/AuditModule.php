<?php

declare(strict_types=1);

namespace WebxUi\Audit\Panel;

use WebxUi\Admin\AbstractModule;

/**
 * «System → Audit»: the site checked the way an SEO, a front-end developer and an admin check
 * every new project by hand (§8). After SEO in the group — both are about being found, and the
 * audit is the one opened after a release rather than every week.
 */
final class AuditModule extends AbstractModule
{
    public const ID = 'audit';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-audit::module.title');
    }

    public function icon(): string
    {
        return 'check-circle';
    }

    public function order(): int
    {
        return 710;
    }

    public function group(): string
    {
        return 'system';
    }

    /**
     * Looking, starting a run, and — once there are fixes and hidden findings — changing things.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['audit.view', 'audit.run', 'audit.manage'];
    }
}
