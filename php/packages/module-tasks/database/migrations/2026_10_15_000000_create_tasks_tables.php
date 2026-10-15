<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects, their tasks, the stages of a task and the journal of everything that happened to it
 * (§4 of the spec).
 *
 * Every long text is `text`: a specification or a report is pages of markdown, and `string`
 * would cut it short on MariaDB and only there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks_projects', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('title', 255);

            // Where an agent works: the folder it is started in, the repository it pushes to,
            // the branch a task branches from when it names none.
            $table->string('workdir', 1024)->nullable();
            $table->string('repo_url', 1024)->nullable();
            $table->string('default_branch', 255)->nullable();

            // The project's own rules for agents, free text — handed out with every stage.
            $table->text('rules')->nullable();

            // Archived rather than deleted: its tasks and their journals stay readable, and no
            // stage of it is handed out any more.
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('tasks_projects')->restrictOnDelete();

            // #12 — counted inside the project, so the number a person says out loud is short.
            $table->unsignedInteger('number');

            $table->string('title', 255);
            $table->string('status', 16)->default('draft');
            $table->string('priority', 8)->default('normal');
            $table->text('spec')->nullable();
            $table->text('acceptance')->nullable();
            $table->string('branch', 255)->nullable();

            // Null once that administrator is deleted: the task is still the site's.
            $table->foreignId('created_by')->nullable()->constrained('cms_users')->nullOnDelete();

            $table->timestamp('closed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
            $table->index(['status', 'priority']);
        });

        Schema::create('tasks_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks_tasks')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title', 255);
            $table->text('brief')->nullable();
            $table->text('acceptance')->nullable();
            $table->string('review_mode', 8)->default('auto');
            $table->string('status', 16)->default('pending');

            // The name of the agent's session, not a foreign key: an agent is not an
            // administrator, and the name is what its own logs call it.
            $table->string('assignee', 255)->nullable();

            // 1 on the first try, +1 on every rework.
            $table->unsignedInteger('attempt')->default(1);

            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('accepted_at')->nullable();

            // Not in §4: the dispatcher's queue shows how long a stage has stood where it is,
            // and a stage stuck in progress is found by it.
            $table->timestamp('status_changed_at')->nullable();

            // When it was reported as hung (§6a, `stage.stale`): once per stint, cleared when taken.
            $table->timestamp('stale_notified_at')->nullable();

            $table->text('report')->nullable();
            $table->text('result_ref')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'position']);
            $table->index('status');
        });

        Schema::create('tasks_journal', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks_tasks')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('tasks_stages')->nullOnDelete();

            // Who, as text: an agent has no row anywhere, and a renamed administrator must not
            // rewrite what they did last month.
            $table->string('actor_type', 8);
            $table->string('actor_name', 255);

            $table->string('kind', 16);
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['task_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks_journal');
        Schema::dropIfExists('tasks_stages');
        Schema::dropIfExists('tasks_tasks');
        Schema::dropIfExists('tasks_projects');
    }
};
