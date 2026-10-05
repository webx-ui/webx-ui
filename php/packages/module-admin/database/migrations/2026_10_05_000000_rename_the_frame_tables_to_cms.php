<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The frame's tables under the frame's prefix.
 *
 * `cms_*` is what the frame of the panel calls its tables (`cms_users`, `cms_roles`,
 * `cms_settings`), and a section names its own after itself (`media_*`, `seo_*`). These five
 * belong to the frame and grew up under three other prefixes; a site's database read top to
 * bottom should say whose a table is.
 *
 * A rename rather than new tables, because sites have rows in them — the journal, the notes, the
 * relations. Keys pointing at a renamed table follow it on MySQL, Postgres and SQLite alike. Each
 * one is skipped when it has already moved, so a site that ran half of this and failed finishes
 * it on the next `migrate`.
 */
return new class extends Migration
{
    private const NAMES = [
        'entity_versions' => 'cms_versions',
        'entity_notes' => 'cms_notes',
        'webx_relations' => 'cms_relations',
        'admin_history' => 'cms_history',
        'admin_uploads' => 'cms_uploads',
    ];

    public function up(): void
    {
        foreach (self::NAMES as $from => $to) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }
    }

    public function down(): void
    {
        foreach (self::NAMES as $from => $to) {
            if (Schema::hasTable($to) && ! Schema::hasTable($from)) {
                Schema::rename($to, $from);
            }
        }
    }
};
