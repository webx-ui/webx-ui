<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A vacancy (§3): a page of fixed structure, so every part of it is a column rather than a block.
 *
 * `valid_through` and `posted_at` are days, not moments — a vacancy is open the whole of its last
 * day in the application's timezone, and a day has no offset to get wrong. The three lists hold
 * one row per line with the languages inside it. The application form is a `cms_relations` row,
 * not a column: nothing here points at another package's table, and every migration of the
 * module stays on the first day (CLAUDE.md §4 on how migrations are sorted together).
 *
 * No unique index on the slug: an address is unique in `routes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancies', function (Blueprint $table): void {
            $table->id();

            $table->json('title')->nullable();
            $table->json('slug')->nullable();

            // On a card and the fallback description: text, no markup.
            $table->json('lead')->nullable();

            // The longest value is six characters; a default longer than its column is refused by
            // MariaDB at CREATE TABLE and by nobody else (CLAUDE.md §4).
            $table->string('workplace', 8)->default('onsite');
            $table->json('city')->nullable();
            $table->json('address')->nullable();
            $table->string('country', 2)->nullable();

            $table->json('employment_types')->nullable();

            // Words for people, and numbers only the markup and the cards read (decision 5).
            $table->json('salary')->nullable();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->string('salary_unit', 8)->nullable();
            $table->string('salary_currency', 3)->nullable();

            $table->json('description')->nullable();
            $table->json('duties')->nullable();
            $table->json('requirements')->nullable();
            $table->json('benefits')->nullable();

            // `is_closed`, not `closed`-anything that Eloquent already has a property for.
            $table->boolean('is_closed')->default(false);
            $table->date('valid_through')->nullable();
            $table->date('posted_at')->nullable();

            $table->integer('position')->default(0);

            // The fields a project patched onto the editor (`HasExtra`).
            $table->json('extra')->nullable();

            $table->draft();

            $table->softDeletes();
            $table->timestamps();

            $table->index('position');
            $table->index('valid_through');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancies');
    }
};
