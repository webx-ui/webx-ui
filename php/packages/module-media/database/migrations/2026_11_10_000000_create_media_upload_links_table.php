<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One-time upload links handed to an agent by `media_upload_link`. A row is the state a
        // signature alone cannot carry — whether the link was used — and, once used, the record of
        // who put the file in: the administrator and the connection that asked for the link.
        Schema::create('media_upload_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('directory_id')->constrained('media_directories')->cascadeOnDelete();
            // No foreign key: the administrators' table is another package's, and the row is
            // a record that outlives an account just as a journal row does.
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->unsignedBigInteger('grant_id')->nullable();
            $table->string('name')->nullable();
            $table->json('alt')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_upload_links');
    }
};
