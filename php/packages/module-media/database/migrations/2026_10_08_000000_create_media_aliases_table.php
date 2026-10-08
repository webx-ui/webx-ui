<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The keys files had before a conversion changed them, so an old address still finds
        // its file. Gone with the file: a deleted file has nothing to redirect to.
        Schema::create('media_aliases', function (Blueprint $table): void {
            $table->id();
            $table->string('path')->unique();
            $table->foreignId('file_id')->constrained('media_files')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_aliases');
    }
};
