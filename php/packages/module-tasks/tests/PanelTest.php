<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Tasks\Models\JournalEntry;
use WebxUi\Tasks\Models\Task;
use WebxUi\Tasks\Workflow\StageStatus;
use WebxUi\Tasks\Workflow\TaskStatus;

/**
 * The queue by the panel's door (§6): the manifest and its badge, the list and its views, the
 * form, the buttons of a stage, and who may press which.
 */
final class PanelTest extends TestCase
{
    #[Test]
    public function the_section_is_in_the_manifest_with_what_waits_for_a_person(): void
    {
        $this->task(stages: ['Design'], human: ['Design']);
        $stage = $this->claim('worker-1');
        $this->tasks()->report($stage, 'Done.', null, $this->agent('worker-1'));

        $reviewer = $this->member(['tasks.view', 'tasks.review']);
        $section = $this->section($reviewer);

        $this->assertSame(['tasks.view', 'tasks.manage', 'tasks.work', 'tasks.review'], $section['permissions']);
        $this->assertSame(1, $section['meta']['badge'] ?? null);

        // Somebody who may only look is not counted at: there is nothing for them to do about it.
        $this->assertSame([], $this->section($this->member(['tasks.view']))['meta']);
    }

    #[Test]
    public function the_list_has_its_views_and_their_counts(): void
    {
        $project = $this->project();
        $this->task($project, ['Draft'], ['title' => 'A draft'], ready: false);
        $this->task($project, ['Work'], ['title' => 'In work']);
        $blocked = $this->task($project, ['Ask'], ['title' => 'Asking', 'priority' => 'high']);
        $stage = $this->claim('worker-1');
        $this->assertSame($blocked->id, $stage?->task_id);
        $this->tasks()->block($stage, 'Which?', $this->agent('worker-1'));

        $viewer = $this->member(['tasks.view']);

        $all = $this->actingAs($viewer, 'cms')->getJson('/api/cms/tasks/tasks')->assertOk();
        $this->assertSame(['all' => 3, 'waiting' => 1, 'active' => 2, 'draft' => 1, 'closed' => 0], $all->json('counts'));
        $this->assertSame(['shop#3', 'shop#2', 'shop#1'], array_column((array) $all->json('data'), 'ref'));
        $this->assertSame(3, $all->json('total'));

        $waiting = $this->actingAs($viewer, 'cms')->getJson('/api/cms/tasks/tasks?view=waiting')->assertOk();
        $this->assertSame(['Asking'], array_column((array) $waiting->json('data'), 'title'));
        $this->assertSame('blocked', $waiting->json('data.0.current_stage.status'));

        $search = $this->actingAs($viewer, 'cms')->getJson('/api/cms/tasks/tasks?q=draft')->assertOk();
        $this->assertSame(['A draft'], array_column((array) $search->json('data'), 'title'));
    }

    #[Test]
    public function the_form_writes_a_draft_and_the_rules_hold_after_ready(): void
    {
        $project = $this->project();
        $planner = $this->member(['tasks.view', 'tasks.manage']);

        $created = $this->actingAs($planner, 'cms')->postJson('/api/cms/tasks/tasks', [
            'project_id' => $project->id,
            'title' => 'Checkout',
            'priority' => 'high',
            'stages' => [['title' => 'One', 'review_mode' => 'human'], ['title' => 'Two']],
        ])->assertCreated();

        $this->assertSame('draft', $created->json('data.status'));
        $this->assertSame(['One', 'Two'], array_column((array) $created->json('data.stages'), 'title'));
        $id = (int) $created->json('data.id');

        $this->actingAs($planner, 'cms')->postJson("/api/cms/tasks/tasks/{$id}/ready")->assertOk()
            ->assertJsonPath('data.status', 'ready');

        $this->actingAs($planner, 'cms')->putJson("/api/cms/tasks/tasks/{$id}", ['spec' => 'Changed.'])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'task-started');

        $this->actingAs($planner, 'cms')->postJson('/api/cms/tasks/tasks', ['project_id' => $project->id, 'stages' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    #[Test]
    public function the_buttons_of_a_stage_belong_to_those_who_may_press_them(): void
    {
        $task = $this->task(stages: ['One', 'Two']);
        $stage = $this->claim('worker-1');
        $this->tasks()->report($stage, 'Done.', null, $this->agent('worker-1'));

        $planner = $this->member(['tasks.view', 'tasks.manage']);
        $reviewer = $this->member(['tasks.view', 'tasks.review']);

        $this->actingAs($planner, 'cms')->postJson("/api/cms/tasks/stages/{$stage?->id}/accept")->assertForbidden();
        $this->actingAs($reviewer, 'cms')->postJson("/api/cms/tasks/stages/{$stage?->id}/rework", ['remarks' => ''])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'text-required');

        $after = $this->actingAs($reviewer, 'cms')->postJson("/api/cms/tasks/stages/{$stage?->id}/accept")->assertOk();
        $this->assertSame(['done', 'ready'], array_column((array) $after->json('data.stages'), 'status'));

        // The person who pressed it is who the journal names.
        $this->assertSame(
            $reviewer->name,
            JournalEntry::query()->where('stage_id', $stage?->id)->where('meta->to', 'done')->sole()->actor_name,
        );

        $this->actingAs($reviewer, 'cms')->postJson("/api/cms/tasks/tasks/{$task->id}/cancel")->assertForbidden();
        $this->actingAs($planner, 'cms')->postJson("/api/cms/tasks/tasks/{$task->id}/cancel", ['reason' => 'Not needed.'])->assertOk();
        $this->assertSame(TaskStatus::Cancelled, $task->refresh()->status);
    }

    #[Test]
    public function a_person_answers_the_question_from_the_panel(): void
    {
        $this->task(stages: ['One']);
        $stage = $this->claim('worker-1');
        $this->tasks()->block($stage, 'Which provider?', $this->agent('worker-1'));

        $this->actingAs($this->member(['tasks.view', 'tasks.manage']), 'cms')
            ->postJson("/api/cms/tasks/stages/{$stage?->id}/unblock", ['answer' => 'The configured one.'])
            ->assertOk()
            ->assertJsonPath('data.stages.0.status', 'ready');

        $this->assertSame(StageStatus::Ready, $stage?->refresh()->status);
    }

    #[Test]
    public function projects_are_listed_and_written(): void
    {
        $planner = $this->member(['tasks.view', 'tasks.manage']);

        $this->actingAs($planner, 'cms')->postJson('/api/cms/tasks/projects', ['slug' => 'blog', 'title' => 'Blog'])->assertCreated();
        $this->actingAs($planner, 'cms')->postJson('/api/cms/tasks/projects', ['slug' => 'Not A Slug', 'title' => 'X'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);

        $this->task($this->project('shop'));
        $list = $this->actingAs($this->member(['tasks.view']), 'cms')->getJson('/api/cms/tasks/projects')->assertOk();

        $this->assertSame(['Blog' => 0, 'Shop' => 1], array_column((array) $list->json('data'), 'tasks_count', 'title'));
        $this->actingAs($this->member(['tasks.view']), 'cms')->postJson('/api/cms/tasks/projects', ['slug' => 'x', 'title' => 'X'])->assertForbidden();
    }

    #[Test]
    public function the_form_is_a_described_screen(): void
    {
        $this->assertTrue($this->app->make(ScreenRegistry::class)->has('tasks.form'));

        $screen = $this->actingAs($this->member(['tasks.view', 'tasks.manage']), 'cms')
            ->getJson('/api/cms/screens/tasks.form')
            ->assertOk();

        $this->assertStringContainsString('"name":"stages"', (string) json_encode($screen->json('data')));
        $this->assertSame(0, Task::query()->count());
    }

    /**
     * The section as the manifest describes it to this person.
     *
     * @return array<string, mixed>
     */
    private function section(CmsUser $user): array
    {
        $modules = (array) $this->actingAs($user, 'cms')->getJson('/api/cms/manifest')->assertOk()->json('data.modules');

        foreach ($modules as $module) {
            if (is_array($module) && ($module['id'] ?? null) === 'tasks') {
                return $module;
            }
        }

        $this->fail('The section is not in the manifest.');
    }
}
