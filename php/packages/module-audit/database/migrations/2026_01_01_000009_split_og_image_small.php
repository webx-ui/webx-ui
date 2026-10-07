<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A small Open Graph picture used to be reported as `og.image_broken` and is `og.image_small`
 * now. The findings already stored move to the new id with the fingerprint the new check gives
 * them, so the next run compares like with like; and every hiding rule of the old id is copied
 * to the new one, since a rule written then hid both.
 */
return new class extends Migration
{
    private const OLD = 'og.image_broken';

    private const NEW = 'og.image_small';

    public function up(): void
    {
        $copies = [];

        foreach (DB::table('audit_ignores')->where('check', self::OLD)->orderBy('id')->get() as $rule) {
            $copy = (array) $rule;
            unset($copy['id']);
            $copy['check'] = self::NEW;

            $copies[(int) $rule->id] = (int) DB::table('audit_ignores')->insertGetId($copy);
        }

        DB::table('audit_issues')->where('check', self::OLD)->where('key', 'small')->orderBy('id')
            ->select(['id', 'url', 'key', 'ignored_by'])
            ->chunkById(500, function ($issues) use ($copies): void {
                foreach ($issues as $issue) {
                    DB::table('audit_issues')->where('id', $issue->id)->update([
                        'check' => self::NEW,
                        'fingerprint' => sha1(self::NEW."\n".($issue->url ?? '')."\n".$issue->key),
                        'ignored_by' => $issue->ignored_by === null ? null : ($copies[(int) $issue->ignored_by] ?? $issue->ignored_by),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('audit_issues')->where('check', self::NEW)->orderBy('id')
            ->select(['id', 'url', 'key'])
            ->chunkById(500, function ($issues): void {
                foreach ($issues as $issue) {
                    DB::table('audit_issues')->where('id', $issue->id)->update([
                        'check' => self::OLD,
                        'fingerprint' => sha1(self::OLD."\n".($issue->url ?? '')."\n".$issue->key),
                        'ignored_by' => null,
                    ]);
                }
            });

        DB::table('audit_ignores')->where('check', self::NEW)->delete();
    }
};
