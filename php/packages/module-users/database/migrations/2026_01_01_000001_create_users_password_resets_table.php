<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keyed by the visitor, not by the address as Laravel's broker is: the address changes
        // (spec §3). One live link per visitor — a new one replaces it.
        Schema::create('users_password_resets', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            // sha256 of the token in the link — the page of the link finds the row by it; the token
            // itself is never stored.
            $table->char('token_hash', 64)->index();
            // `reset` — «Forgot your password?»; `invite` — an account made in the panel.
            $table->string('purpose', 16);
            // `webx:users:prune` deletes by it.
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_password_resets');
    }
};
