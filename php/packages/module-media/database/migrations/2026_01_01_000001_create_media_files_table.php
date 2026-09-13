<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('directory_id')->constrained('media_directories')->cascadeOnDelete();

            // The disk is remembered per file: a library that moves to S3 keeps serving what was
            // uploaded before the move from where it actually is.
            $table->string('disk', 32);
            $table->string('path', 2048);
            $table->string('original_path', 2048)->nullable();

            $table->char('hash', 32);
            $table->string('name');
            $table->string('file_name');
            $table->string('extension', 16);
            $table->string('mime', 127);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->timestamps();

            // Uploading the same bytes into the same folder twice is one file, not two.
            $table->index(['directory_id', 'hash']);
            // The listing, which is by folder and sorted by name or by date.
            $table->index(['directory_id', 'name']);
            $table->index('mime');
            // Duplicates across the library — nothing reads it yet, and backfilling an index
            // over a table of tens of thousands of rows later is the expensive way to get it.
            $table->index('hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
