<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_keys', function (Blueprint $table): void {
            $table->id();

            // The administrator the key acts as and the one who issued it — without constraints,
            // the way `mcp_grants` does it: this package owns no `cms_users`, and the row has to
            // outlive both, because the call log still points at the key.
            $table->unsignedBigInteger('cms_user_id');
            $table->unsignedBigInteger('created_by')->nullable();

            // Passport's own token row. The secret is not here and never was: Passport signs a
            // token and keeps only its id, and the key is shown once, when it is issued.
            $table->string('token_id', 100)->unique();

            $table->string('name');

            // As issued, so that the list says what the key may do without asking Passport.
            $table->json('scopes');

            $table->timestamp('created_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->index('cms_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_keys');
    }
};
