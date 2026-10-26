<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Submissions;

/**
 * One enclosure as it is written into a submission (§2.20): a row of answers with no field.
 *
 * `value` is the text people read — the letter, Telegram, the export and an agent all take it, as
 * they take any answer; `payload` is the structure a screen of the panel draws, with its `kind`
 * naming who draws it. `label` is the snapshot of the row's heading in the submission's language.
 */
final readonly class Enclosed
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $label,
        public string $value,
        public array $payload,
    ) {}
}
