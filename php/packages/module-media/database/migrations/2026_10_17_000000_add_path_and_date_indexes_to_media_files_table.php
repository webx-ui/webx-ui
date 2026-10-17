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
            // Every public page with a picture asks `where path in (...)` (`MediaFiles::load()`),
            // and `path` had no index at all: a full scan of the library per page. The column
            // is 2048 wide, more than MySQL puts in one key (3072 bytes, 768 utf8mb4 characters),
            // so there the first 255 characters are indexed — a prefix is enough to find a row
            // by its whole value. Drivers without prefix keys index the column itself.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->rawIndex('path(255)', 'media_files_path_index');
            } else {
                $table->index('path', 'media_files_path_index');
            }

            // The panel lists a folder newest first and counts it: `directory_id = ? order by
            // created_at desc`, which `(directory_id, name)` and `(directory_id, hash)` cannot serve.
            $table->index(['directory_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table): void {
            $table->dropIndex('media_files_path_index');
            $table->dropIndex(['directory_id', 'created_at']);
        });
    }
};
