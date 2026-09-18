<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What arrived, what was in it, what was attached to it and what has been done about it.
 *
 * The one column worth arguing about is `value`: it is `text` and not `string`, because the
 * reference implementation's `varchar(255)` quietly truncates anything typed into a textarea —
 * and quietly only on SQLite. MariaDB says "Data too long" in the middle of accepting somebody's
 * enquiry (§2.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_submissions', function (Blueprint $table): void {
            $table->id();

            // Restricted, not cascaded: a form that has taken submissions is disabled rather
            // than deleted (§2.4), and this is the rule underneath that one — the database
            // refuses even if something forgets to ask.
            $table->foreignId('form_id')->constrained('inbox_forms')->restrictOnDelete();
            $table->foreignId('status_id')->constrained('inbox_statuses')->restrictOnDelete();

            // Whoever is dealing with it. Null after that person's account is deleted: the
            // submission is still the site's, only nobody's in particular any more.
            $table->foreignId('assignee_id')->nullable()->constrained('cms_users')->nullOnDelete();

            // md5 of the values, so the same thing sent twice inside the window is one row.
            $table->string('hash', 32)->index();

            $table->timestamp('read_at')->nullable();

            // `web` — through the public door; `panel` — typed in by an administrator, which
            // is how a call that came by telephone gets into the same list.
            $table->string('source', 8)->default('web');

            // Address, user agent, page, referrer, utm, language — whatever the intake was
            // able to see. Nothing here is ever shown to the visitor.
            $table->json('meta')->nullable();

            // An unsent notification is a mark on the submission and not a lost submission
            // (§2.10): the row is written before the mail is attempted.
            $table->timestamp('notified_at')->nullable();
            $table->text('notify_error')->nullable();

            $table->timestamps();

            // The list is always one form's, newest first.
            $table->index(['form_id', 'created_at']);
        });

        Schema::create('inbox_submission_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained('inbox_submissions')->cascadeOnDelete();

            // Denormalised so a column of the list can be a subquery on this table alone.
            $table->foreignId('form_id')->constrained('inbox_forms')->cascadeOnDelete();

            // Null when the field was finally destroyed rather than soft-deleted. The answer
            // survives it, which is what the three snapshot columns below are for (§2.2).
            $table->foreignId('field_id')->nullable()->constrained('inbox_form_fields')->nullOnDelete();

            $table->string('name', 64);
            $table->string('label', 255)->nullable();
            $table->string('type', 16);

            // What a person reads: the text, or the chosen options joined by commas. This is
            // what goes into the mail and into the CSV.
            $table->text('value')->nullable();

            // The same thing with its structure kept, for everything that is not one line.
            $table->json('payload')->nullable();

            $table->index(['form_id', 'name']);
            $table->index(['submission_id', 'field_id']);
        });

        Schema::create('inbox_submission_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained('inbox_submissions')->cascadeOnDelete();
            $table->foreignId('field_id')->nullable()->constrained('inbox_form_fields')->nullOnDelete();

            $table->string('disk', 32);
            $table->string('path', 255);

            // The name the visitor's own file had; the key on the disk is a uuid.
            $table->string('name', 255);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime', 128)->nullable();

            $table->timestamp('created_at')->nullable();
        });

        Schema::create('inbox_submission_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained('inbox_submissions')->cascadeOnDelete();

            // Null is the system: the submission arriving, the notification going out.
            $table->foreignId('admin_id')->nullable()->constrained('cms_users')->nullOnDelete();

            $table->string('type', 16);
            $table->string('from', 255)->nullable();
            $table->string('to', 255)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['submission_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_submission_events');
        Schema::dropIfExists('inbox_submission_files');
        Schema::dropIfExists('inbox_submission_values');
        Schema::dropIfExists('inbox_submissions');
    }
};
