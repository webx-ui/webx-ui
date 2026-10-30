<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use WebxUi\Orders\Notifications\DefaultChannels;

/**
 * The channels a site starts with (spec §10.1, decisions 43 and 81): two letters on a new order
 * and one on a payment. Made only where `webx-ui/module-notifications` and `module-settings` both
 * are; nothing on a site that chose its own already.
 *
 * Not taken back on rollback: from the moment they are made the channels are the site's — edited,
 * switched off, chosen elsewhere — and a rollback that deleted them would delete that work.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(DefaultChannels::class)->install();
    }

    public function down(): void {}
};
