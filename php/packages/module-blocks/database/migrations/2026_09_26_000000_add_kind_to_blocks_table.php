<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebxUi\Blocks\Rendering\Calls;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table): void {
            // `block` stands in content and is offered by the picker; `component` is only ever
            // called by a tag. On the type, not the version: it is where the type shows, not
            // what it prints. Sixteen, so that the default fits with room on MySQL.
            $table->string('kind', 16)->default('block')->after('slug');
        });

        Schema::table('block_versions', function (Blueprint $table): void {
            // The types this version's template calls by tag, sorted. Nullable rather than a
            // JSON default: MySQL before 8.0.13 refuses a default on a JSON column at CREATE
            // time, and the model reads null as an empty list anyway.
            $table->json('uses')->nullable()->after('sample');
        });

        // Every version written before today, read by the same parser the saves use — so the
        // graph is complete the moment the column exists, not after each type is saved again.
        DB::table('block_versions')->select(['id', 'template'])->orderBy('id')->chunkById(200, static function ($rows): void {
            foreach ($rows as $row) {
                DB::table('block_versions')
                    ->where('id', $row->id)
                    ->update(['uses' => json_encode(Calls::of((string) $row->template), JSON_THROW_ON_ERROR)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('block_versions', function (Blueprint $table): void {
            $table->dropColumn('uses');
        });

        Schema::table('blocks', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
