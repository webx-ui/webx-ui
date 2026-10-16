<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The delivery log (§9): one row for one recipient of one channel. The channel's name and
 * provider are a snapshot — a channel renamed or deleted still answers "where did it go".
 *
 * No message body: the values the event had are kept, and the text is rendered from them, so a
 * retry after a fixed template sends the fixed text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->nullable()->constrained('notifications_channels')->nullOnDelete();
            $table->string('channel_name', 160);
            $table->string('provider', 32);
            $table->string('event', 64)->index();
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('locale', 12)->nullable();
            $table->string('recipient', 255);
            $table->string('recipient_label', 255)->nullable();
            $table->string('status', 16)->index();
            $table->string('reason', 32)->nullable();
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->boolean('is_test')->default(false);
            $table->boolean('truncated')->default(false);
            $table->json('unknown_variables')->nullable();
            $table->json('variables')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_deliveries');
    }
};
