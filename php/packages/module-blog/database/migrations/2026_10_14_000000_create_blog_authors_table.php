<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The profile of an author: what the site may say about an administrator who writes (§19).
 *
 * One row per administrator, and gone with the account: a profile is a page about a person, and a
 * person who no longer has a login is not one the site goes on presenting as its author. The
 * articles keep pointing at `cms_users` (§19.2) — a profile is found by `user_id`, so switching
 * authors off touches nothing in them.
 *
 * `is_public` is off by default: an administrator who writes does not become a public figure
 * because the site turned the feature on (decision 2 of §19.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_authors', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->unique()->constrained('cms_users')->cascadeOnDelete();

            $table->json('title');
            $table->json('slug')->nullable();
            $table->json('role')->nullable();
            $table->json('bio')->nullable();

            $table->foreignId('photo_id')->nullable()->constrained('media_files')->nullOnDelete();

            $table->boolean('is_public')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_authors');
    }
};
