<?php

declare(strict_types=1);

return [
    'review-human' => 'Stage :position/:count «:stage» waits for your review (attempt :attempt)',
    'review-auto' => 'Stage :position/:count «:stage» waits for an agent\'s review (attempt :attempt)',
    'blocked' => 'The agent on stage :position/:count «:stage» asks:',
    'rework' => 'Stage :position/:count «:stage» was sent back (attempt :attempt)',
    'stale' => 'Stage :position/:count «:stage» is in progress too long — :agent, hours: :hours',
    'done' => 'The task is done. Stages accepted: :count',
    'result' => 'Result: :result',
    'test' => 'A test message from :site: notifications of the task queue will come here.',
];
