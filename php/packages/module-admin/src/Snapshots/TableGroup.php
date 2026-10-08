<?php

declare(strict_types=1);

namespace WebxUi\Admin\Snapshots;

/**
 * What a table is to a snapshot: whether it moves between stands, and when.
 *
 * The split exists because a stand is two things at once — the site's content, which is the same
 * site wherever it runs, and the stand's own life: who signs in there, what visitors sent there,
 * what ran there last night. Moving the first and keeping the second is the whole command.
 */
enum TableGroup: string
{
    /** The site itself: pages, blocks, settings, media records. Always travels. */
    case Content = 'content';

    /** Who signs in to the panel, and as what. Travels with `--with-admins` or `--all`. */
    case Admins = 'admins';

    /**
     * The stand's own life: enquiries and their files, journals, audit results, tokens. Travels
     * only with `--all`, and a restore without it never touches these rows — dev's real enquiries
     * must not be wiped by a copy of local, and personal data must not land on local.
     */
    case Stand = 'stand';

    /**
     * Built from content and rebuilt on demand (glued bundles). Never carried; emptied when the
     * content they were built from is replaced, so nothing serves a bundle of the old content.
     */
    case Derived = 'derived';

    /** Sessions, cache, queues, `migrations`. Never carried, never touched. */
    case Transient = 'transient';

    public function travels(bool $all, bool $withAdmins): bool
    {
        return match ($this) {
            self::Content => true,
            self::Admins => $all || $withAdmins,
            self::Stand => $all,
            self::Derived, self::Transient => false,
        };
    }
}
