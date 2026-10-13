<?php

declare(strict_types=1);

namespace WebxUi\Admin\SiteBar;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * What the panel on the site needs to know about one kind of record: where the panel edits it,
 * who may, and what to call it (§6 of WEBX_UI_SITE_BAR.md).
 *
 * Registered by the section that owns the record, under the type its addresses carry in the
 * registry — not under a model class, because the address is what the bar starts from and the
 * type is what `Resolution` knows.
 */
final readonly class EntityLink
{
    /**
     * @param  Closure(Model): ?string  $edit  Where the panel edits the record, relative to the panel: `/pages/12`.
     * @param  string  $permission  What the administrator needs for the bar to offer this record at all.
     * @param  string  $kind  A translation key for what the record is, in the singular: «Page», «Article».
     * @param  class-string|null  $publish  What publishes the record from the bar (SB3); none, no «Publish» button.
     * @param  (Closure(Model, ?string): string)|null  $label  What the record is called; its title or name when not given.
     */
    public function __construct(
        public Closure $edit,
        public string $permission,
        public string $kind,
        public ?string $publish = null,
        public ?Closure $label = null,
    ) {}
}
