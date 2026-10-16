<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The channels an administrator sets up (§3.1 of the notifications spec). The provider and the
 * event are keys of registries in code, not foreign keys: a provider or an event that goes away
 * leaves its channels here, and the panel and webx:doctor say so.
 *
 * `credentials` holds only what is secret, encrypted with the application key; whatever is not
 * secret — the sender, the SMTP host — is in `options`, open to the form and to the journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_channels', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('provider', 32)->index();
            $table->string('event', 64)->nullable()->index();
            $table->text('credentials')->nullable();
            $table->json('options')->nullable();
            $table->json('recipients')->nullable();
            $table->json('subject')->nullable();
            $table->json('template')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('copied_from')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_channels');
    }
};
