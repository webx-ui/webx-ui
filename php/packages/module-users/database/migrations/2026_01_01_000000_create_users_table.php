<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebxUi\Users\Schema\UsersTable;

return new class extends Migration
{
    public function up(): void
    {
        // A site from the skeleton of before has Laravel's `users`: empty — it goes; with rows —
        // a refusal that says what to do (spec §3, decision 13).
        $found = new UsersTable(DB::connection($this->getConnection()));

        if ($found->state() === UsersTable::OURS) {
            return;
        }

        $found->prepare();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            // The login, normalised by the model: trimmed, lower case.
            $table->string('email', 191)->unique();
            $table->timestamp('email_verified_at')->nullable();
            // Null — an account without a password: signed in through a provider, or made in the panel.
            $table->string('password')->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            // The panel and the agent look a customer up by phone.
            $table->string('phone', 32)->nullable()->index();
            $table->string('locale', 12)->nullable();
            $table->json('extra')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->rememberToken();
            // The panel's list sorts by both.
            $table->timestamp('last_sign_in_at')->nullable()->index();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        // Laravel's table is not brought back: nothing was in it.
        Schema::dropIfExists('users');
    }
};
