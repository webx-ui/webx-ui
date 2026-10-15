<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Notifications;

/**
 * One message about one event, already in words: the text is made when the event happens, so
 * what the person reads is the state they were told about, not whatever it is by the time a
 * queue worker gets to it.
 */
final readonly class Notice
{
    public function __construct(
        /** `stage.review_human`, `task.done`, … — the keys of §6a. */
        public string $event,
        public int $taskId,
        public ?int $stageId,
        public string $text,
    ) {}

    /**
     * @return array{event: string, task_id: int, stage_id: int|null, text: string}
     */
    public function toArray(): array
    {
        return ['event' => $this->event, 'task_id' => $this->taskId, 'stage_id' => $this->stageId, 'text' => $this->text];
    }

    /**
     * @param  array{event: string, task_id: int, stage_id: int|null, text: string}  $values
     */
    public static function fromArray(array $values): self
    {
        return new self($values['event'], $values['task_id'], $values['stage_id'], $values['text']);
    }
}
