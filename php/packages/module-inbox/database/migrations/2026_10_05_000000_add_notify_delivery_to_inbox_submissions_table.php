<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the letters actually left, and not only whether they were handed to the queue (§9).
 *
 * On a site with a queue, sending a letter is pushing a job: the mailer answers at once and the
 * SMTP server is only asked later, by a worker. Without these two columns a job that failed in
 * the worker left the submission saying "notified, no error" for ever.
 *
 * `notify_queued_at` is set while at least one letter is still waiting for a worker, and
 * cleared once every one of them has been sent or has failed — so a value older than a few
 * minutes means no worker is running. `notify_recipients` is who was written to and how each
 * letter went. Both are null on every submission written before today, which reads as before:
 * `notified_at` and `notify_error` keep their meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_submissions', function (Blueprint $table): void {
            $table->timestamp('notify_queued_at')->nullable()->after('notify_error')->index();
            $table->json('notify_recipients')->nullable()->after('notify_queued_at');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_submissions', function (Blueprint $table): void {
            $table->dropIndex(['notify_queued_at']);
            $table->dropColumn(['notify_queued_at', 'notify_recipients']);
        });
    }
};
