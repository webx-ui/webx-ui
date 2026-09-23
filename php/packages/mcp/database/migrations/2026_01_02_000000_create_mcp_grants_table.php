<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_grants', function (Blueprint $table): void {
            $table->id();

            // The administrator and the client, without constraints to either table: this
            // package owns neither, and a panel without the auth module has no `cms_users`
            // to point at. One row per pair — consenting again to the same client updates
            // it rather than adding a second.
            $table->unsignedBigInteger('cms_user_id');
            $table->uuid('oauth_client_id');

            // What the client called itself and where the code was sent, as shown on the
            // consent screen: the name is the client's own choice, so the address is the
            // part worth keeping.
            $table->string('client_name');
            $table->string('redirect_host');

            $table->boolean('read_only')->default(false);

            // Which text the person agreed to. Together with `created_at` this is the record
            // of the consent — there is no checkbox, the fact of pressing Allow is written down.
            $table->string('consent_version', 32);

            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->unique(['cms_user_id', 'oauth_client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_grants');
    }
};
