<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use WebxUi\Orders\Notifications\DefaultChannels;

/**
 * The two channels to the buyer the edits of an order brought (spec §40.2, question 4 of §44):
 * «Order changed» on `orders.items_changed` and «Payment due» on `orders.payment_requested`. A
 * site that set up its channels before has the first three already — these two are made all the
 * same; nothing on a site that chose its own for these events.
 *
 * Not taken back on rollback, as the first ones (2026_10_29_000000): the channels are the site's
 * from the moment they are made.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(DefaultChannels::class)->install();
    }

    public function down(): void {}
};
