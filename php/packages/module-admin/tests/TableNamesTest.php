<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * The frame's tables moving to `cms_*` on a site that already has rows in them.
 *
 * A fresh install never sees the old names for longer than one `migrate`; the case worth a test
 * is the site that has been running for a year — its journal must arrive under the new name
 * whole, and a second `migrate` after a half-finished one must not fail on what already moved.
 */
final class TableNamesTest extends TestCase
{
    private const MIGRATION = '2026_10_05_000000_rename_the_frame_tables_to_cms';

    private const MOVED = [
        'entity_versions' => 'cms_versions',
        'entity_notes' => 'cms_notes',
        'webx_relations' => 'cms_relations',
        'admin_history' => 'cms_history',
        'admin_uploads' => 'cms_uploads',
    ];

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    #[Test]
    public function a_fresh_install_has_only_the_new_names(): void
    {
        foreach (self::MOVED as $old => $new) {
            $this->assertTrue(Schema::hasTable($new), $new);
            $this->assertFalse(Schema::hasTable($old), $old);
        }
    }

    #[Test]
    public function a_running_site_keeps_its_rows_and_a_second_run_changes_nothing(): void
    {
        // The site as it was before the release: everything under the old names, with a row, and
        // the rename not run yet.
        foreach (self::MOVED as $old => $new) {
            Schema::rename($new, $old);
        }

        $this->forget();
        DB::table('admin_history')->insert([
            'subject_type' => 'page',
            'subject_id' => 7,
            'event' => 'updated',
            'source' => 'panel',
            'admin_name' => 'Anna',
            'created_at' => now(),
        ]);

        $this->artisan('migrate')->assertSuccessful();
        // A second run over tables that already moved — a site that ran half of it and failed.
        $this->forget();
        $this->artisan('migrate')->assertSuccessful();

        $this->assertSame(1, DB::table('cms_history')->where('subject_id', 7)->count());

        foreach (self::MOVED as $old => $new) {
            $this->assertFalse(Schema::hasTable($old), $old);
        }
    }

    private function forget(): void
    {
        DB::table('migrations')->where('migration', self::MIGRATION)->delete();
    }
}
