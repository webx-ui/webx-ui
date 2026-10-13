<?php

declare(strict_types=1);

namespace WebxUi\Admin\SiteBar;

use Illuminate\Database\Eloquent\Model;

/**
 * What «Publish» and «Discard» in the head of the panel on the site do to a record (§9 of
 * WEBX_UI_SITE_BAR.md).
 *
 * Named by the section in its {@see EntityLink} rather than worked out here: a section may have
 * more to do on publishing than copy the draft — and the bar has to do exactly what the
 * section's own button does, or a page published from the site would differ from one published
 * in the panel. {@see DraftPublishing} is the plain case, a record with a draft.
 */
interface Publishing
{
    /** Put the draft on the site, by this administrator, through the panel. */
    public function publish(Model $entity, ?int $authorId): void;

    /** Throw the draft away: the record goes back to what the site shows. */
    public function discard(Model $entity): void;
}
