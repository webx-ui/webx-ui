<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');

            // A super administrator answers yes to every permission check. Without one the
            // first install would have no way to grant anybody anything.
            $table->boolean('is_super')->default(false);

            // Kept rather than deleted: a former administrator's name should still resolve in
            // the audit trail.
            $table->boolean('is_active')->default(true);

            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_users');
    }
};
