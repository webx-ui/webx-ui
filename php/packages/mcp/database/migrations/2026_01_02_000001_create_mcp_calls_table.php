<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_calls', function (Blueprint $table): void {
            $table->id();

            // Who the call acted as and on which connection — without constraints, the way
            // `mcp_grants` does it: this package owns neither table, and a row here has to
            // outlive the administrator it is about. Both are null on the stdio server, where
            // there is no request and nobody to act as.
            $table->unsignedBigInteger('cms_user_id')->nullable();
            $table->unsignedBigInteger('grant_id')->nullable();

            // The full name as the agent called it, `pages_create`.
            $table->string('tool', 128);

            // What was asked, as JSON, with secrets blanked and the rest cut to a length. A
            // page of Markdown in a `body` argument is a call, not a record worth keeping whole.
            $table->text('arguments')->nullable();

            $table->boolean('dry_run')->default(false);

            // Whether the agent got an answer or a refusal, and the refusal's words. A refusal
            // is every "no" the door says — scope, connection, permission — and what the
            // handler threw, so a person can see an agent trying what it may not.
            $table->boolean('ok');
            $table->text('error')->nullable();

            $table->unsignedInteger('duration_ms')->default(0);

            $table->timestamp('created_at')->nullable();

            $table->index(['cms_user_id', 'created_at']);
            $table->index(['tool', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_calls');
    }
};
