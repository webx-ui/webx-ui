<?php

declare(strict_types=1);

namespace WebxUi\Audit\Contracts;

use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\FixPreview;

/**
 * A button that closes a finding (§6, decision 4). A finding never fixes itself: the fix is
 * pressed, or called by an agent with `dry_run` first, and it is done by the module that owns
 * what changes — the normalisation of addresses by `module-seo`, the aliases by `routing`.
 */
interface AuditFix
{
    /** `seo.normalise-host` */
    public function id(): string;

    /**
     * The checks it closes.
     *
     * @return list<string>
     */
    public function fixes(): array;

    public function available(Finding $finding): bool;

    /** What will change — what `dry_run` answers with. */
    public function preview(Finding $finding): FixPreview;

    public function apply(Finding $finding): void;
}
