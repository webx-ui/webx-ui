<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests\Fixtures;

use WebxUi\Inbox\Contracts\SubmissionHandler;
use WebxUi\Inbox\Models\Submission;

/** Writes down whom it was given, with the answers it found on them. */
class RecordingHandler implements SubmissionHandler
{
    /** @var list<array{handler: string, id: int, email: string|null}> */
    public static array $seen = [];

    public function handle(Submission $submission): void
    {
        self::$seen[] = [
            'handler' => static::class,
            'id' => $submission->id,
            'email' => $submission->values->firstWhere('name', 'email')?->value,
        ];
    }
}
