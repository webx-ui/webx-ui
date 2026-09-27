<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An event (§3): a page of fixed structure, so every part of it is a column rather than a block.
 *
 * The moments are in the application's timezone, and an event without a start is one whose date
 * is still to be settled — it never becomes a past one (decision 3). `highlights` is one list with
 * the languages inside each row, not a list per language: the cards are the same in every
 * language, only their words differ. The services are `webx_relations` rows, not a table here.
 *
 * No unique index on the slug: an address is unique in `routes`, which also knows the categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();

            $table->json('title')->nullable();
            $table->json('slug')->nullable();

            // On a card and the fallback description: text, no markup.
            $table->json('lead')->nullable();

            $table->json('gallery')->nullable();

            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);

            // "Every Saturday", "dates to be announced": printed instead of the date.
            $table->json('date_note')->nullable();

            // The longest value is seven characters; a default longer than its column is refused
            // by MariaDB at CREATE TABLE and by nobody else (CLAUDE.md §4).
            $table->string('attendance', 8)->default('offline');
            $table->json('venue')->nullable();
            $table->json('address')->nullable();
            $table->string('map_url', 2000)->nullable();

            $table->json('description')->nullable();
            $table->json('highlights')->nullable();

            // Words for people, and a number only the markup reads (decision 8).
            $table->json('price')->nullable();
            $table->decimal('price_amount', 10, 2)->nullable();
            $table->string('booking_url', 2000)->nullable();

            // The fields a project patched onto the editor (`HasExtra`).
            $table->json('extra')->nullable();

            $table->draft();

            $table->softDeletes();
            $table->timestamps();

            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
