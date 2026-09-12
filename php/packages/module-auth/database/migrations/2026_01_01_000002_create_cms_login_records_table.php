<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_login_records', function (Blueprint $table): void {
            $table->id();

            // Null for an attempt against an address nobody owns — which is most of what an
            // attack looks like, and the reason the email is recorded separately.
            $table->foreignId('cms_user_id')->nullable()->constrained('cms_users')->nullOnDelete();

            $table->string('email');
            $table->boolean('successful');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['email', 'created_at']);
            $table->index(['successful', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_login_records');
    }
};
