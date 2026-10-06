<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebxUi\Settings\Settings;

/**
 * The address parts the registry has always enforced — one slash between segments, lower case,
 * no slash at the end — written into the SEO tab, so the tab shows what the site does and a
 * switch turned off is obeyed (routing spec §8.2). Only where nobody has saved the part: a site
 * that chose «keep as it is» keeps it.
 */
return new class extends Migration
{
    private const DEFAULTS = [
        'seo.normalise-slashes' => true,
        'seo.normalise-case' => true,
        'seo.normalise-trailing' => 'strip',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('cms_settings')) {
            return;
        }

        $saved = DB::table('cms_settings')->whereIn('key', array_keys(self::DEFAULTS))->pluck('key')->all();
        $now = now();

        foreach (self::DEFAULTS as $key => $value) {
            if (! in_array($key, $saved, true)) {
                DB::table('cms_settings')->insert(['key' => $key, 'value' => json_encode($value), 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        // Written past the settings, so their cache — a day long — is told: otherwise the site
        // goes on reading the values from before this migration until it expires.
        if (app()->bound(Settings::class)) {
            app(Settings::class)->forget();
        }
    }

    public function down(): void
    {
        // The values may have been changed since; they are the site's now.
    }
};
