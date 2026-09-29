<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A file on its way in, a piece at a time (WEBX_UI_CATALOG_VIDEO.md §4).
 *
 * The bytes are not here: they are `storage/app/uploads/{id}.part` on the local disk, appended
 * to as the pieces arrive. The row is what the server knows about them — whose they are, what
 * they are for and how far they have got — so that a dropped connection resumes from `offset`
 * rather than from nothing.
 *
 * `fingerprint` is a hash of what the browser can say about a file without reading it (name,
 * size, date of change): the same file chosen again after a reload finds its session by it.
 * `admin_id` carries no foreign key, as everywhere in this package: it does not know which
 * table administrators live in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->unsignedBigInteger('admin_id');
            $table->string('purpose', 64);

            $table->string('name');
            $table->unsignedBigInteger('size');
            $table->string('type', 128)->default('');
            $table->string('fingerprint', 40);

            $table->unsignedBigInteger('offset')->default(0);
            $table->timestamp('expires_at');

            $table->timestamps();

            $table->index(['admin_id', 'purpose', 'fingerprint'], 'admin_uploads_resume');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_uploads');
    }
};
