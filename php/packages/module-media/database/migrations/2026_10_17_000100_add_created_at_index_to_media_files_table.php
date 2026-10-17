<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            // «All files», the library's first screen and its pages: `order by created_at desc
            // limit 24` over every folder, which `(directory_id, created_at)` cannot serve. On
            // thirty thousand files the page was a sort of the whole table (4 ms on MariaDB) and
            // is a walk down this index (0.05 ms).
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
        });
    }
};
