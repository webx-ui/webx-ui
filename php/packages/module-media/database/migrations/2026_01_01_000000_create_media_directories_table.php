<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebxUi\NestedSet\NestedSet;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_directories', function (Blueprint $table): void {
            $table->id();
            NestedSet::columns($table);
            $table->string('title');
            $table->timestamps();
        });

        // The library always has somewhere to put a file, so the root exists from the first
        // migration rather than being created by whoever opens the panel first. Written as a
        // plain insert: a migration that goes through the model would change meaning the day
        // the model does.
        DB::table('media_directories')->insert([
            'parent_id' => null,
            'lft' => 1,
            'rgt' => 2,
            'depth' => 0,
            'title' => 'Library',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('media_directories');
    }
};
