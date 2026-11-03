<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

/**
 * Why a run failed, in the base's words (§7.6): the exception's own text goes to the log, never
 * to the panel or an agent — it may carry a request, a key or a stack.
 */
enum SyncFailure: string
{
    /** The carrier did not answer, or answered with an error of its own. */
    case NoAnswer = 'no_answer';

    /** The API key was refused. */
    case KeyRejected = 'key_rejected';

    /** The answer was not what the satellite expected, or a record broke the contract. */
    case BadResponse = 'bad_response';

    /** Anything else — the site's side: the database, a bug. */
    case Failed = 'failed';

    /** The run stopped without a word — a worker killed mid-page — and a new one took over. */
    case Interrupted = 'interrupted';

    public function words(?string $locale = null): string
    {
        return (string) __('webx-delivery::delivery.sync.errors.'.$this->value, [], $locale);
    }
}
