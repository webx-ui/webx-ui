<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The social networks a visitor signs in with (spec §3). The flow that fills it is the
        // providers' (§9); the reset of a password already empties it for an unconfirmed address
        // (§7 item 5), so the table comes first. No tokens of the provider are kept.
        Schema::create('users_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_user_id', 191);
            $table->string('email', 191)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            // One Google account — one visitor; one link of each provider per visitor.
            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_identities');
    }
};
