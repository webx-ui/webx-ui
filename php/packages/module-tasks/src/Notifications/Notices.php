<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Notifications;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Models\Task;

/**
 * The words of a notice (§6a): short, with a link, no walls of text — the head line with the
 * task, one line of what happened, the beginning of the report's "Done", the result and the way
 * to the task in the panel. In the site's language, which is the one its people share.
 */
final class Notices
{
    /** What each event starts with, so a glance at the chat says which kind it is. */
    private const ICONS = [
        'stage.review_human' => '⏳',
        'stage.review_auto' => '🤖',
        'stage.blocked' => '❓',
        'stage.rework' => '↩️',
        'stage.stale' => '🕒',
        'task.done' => '✅',
    ];

    /** How much of the report's «Done» goes into the message. */
    private const EXCERPT = 300;

    public function __construct(private readonly Config $config) {}

    public function forStage(string $event, Stage $stage, ?string $body = null): Notice
    {
        $task = $stage->task;
        $line = match ($event) {
            'stage.review_human' => 'review-human',
            'stage.review_auto' => 'review-auto',
            'stage.blocked' => 'blocked',
            'stage.rework' => 'rework',
            default => 'stale',
        };

        $lines = [
            $this->head($event, $task),
            $this->say("notify.{$line}", [
                'position' => $stage->position,
                'count' => $task->stages()->count(),
                'stage' => $stage->title,
                'attempt' => $stage->attempt,
                'agent' => (string) $stage->assignee,
                'hours' => $stage->status_changed_at === null ? 0 : (int) $stage->status_changed_at->diffInHours(now(), true),
            ]),
        ];

        if (in_array($event, ['stage.review_human', 'stage.review_auto'], true)) {
            $lines[] = $this->excerpt((string) $stage->report);

            if ($stage->result_ref !== null) {
                $lines[] = $this->say('notify.result', ['result' => $stage->result_ref]);
            }
        } elseif ($body !== null) {
            $lines[] = $this->cut($body);
        }

        $lines[] = $this->link($task);

        return new Notice($event, $task->id, $stage->id, implode("\n", array_filter($lines, static fn (string $line): bool => trim($line) !== '')));
    }

    public function forTask(string $event, Task $task): Notice
    {
        $lines = [
            $this->head($event, $task),
            $this->say('notify.done', ['count' => $task->stages()->where('status', 'done')->count()]),
            $this->link($task),
        ];

        return new Notice($event, $task->id, null, implode("\n", $lines));
    }

    public function test(string $site): Notice
    {
        return new Notice('test', 0, null, $this->say('notify.test', ['site' => $site]));
    }

    private function head(string $event, Task $task): string
    {
        return (self::ICONS[$event] ?? '•')." {$task->project->slug}#{$task->number} · {$task->project->title} — {$task->title}";
    }

    /**
     * The first section of the report — "Done" in the format the rules ask for — or its beginning
     * when it has no sections, as plain text.
     */
    private function excerpt(string $report): string
    {
        $sections = preg_split('/^#{1,6}\s.*$/m', $report) ?: [];
        $first = '';

        foreach ($sections as $section) {
            if (trim($section) !== '') {
                $first = $section;

                break;
            }
        }

        return $this->cut($first);
    }

    private function cut(string $text): string
    {
        $plain = trim((string) preg_replace(['/[*_`>#]+/', '/[ \t]+/', '/\n{3,}/'], ['', ' ', "\n\n"], $text));

        return mb_strlen($plain) > self::EXCERPT ? rtrim(mb_substr($plain, 0, self::EXCERPT)).'…' : $plain;
    }

    private function link(Task $task): string
    {
        // The site's own address rather than the request's: the same link from the panel, a queue
        // worker and the scheduler, which have no request to take a host from.
        return rtrim((string) $this->config->get('app.url'), '/').'/'.trim((string) $this->config->get('webx-admin.path', 'cms'), '/').'/tasks/'.$task->id;
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private function say(string $key, array $replace): string
    {
        return (string) __("webx-tasks::{$key}", $replace, (string) $this->config->get('app.locale'));
    }
}
