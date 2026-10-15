<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Events;

use WebxUi\Tasks\Workflow\StageStatus;

/**
 * A stage changed status — fired inside the transaction that changed it, so a listener that
 * queues work does it `afterCommit`: a move that is rolled back, a dry run included, sends nothing.
 */
final readonly class StageMoved
{
    public function __construct(
        public int $stageId,
        public StageStatus $from,
        public StageStatus $to,
        /** What was said with the move: the question, the remarks. */
        public ?string $body = null,
    ) {}
}
