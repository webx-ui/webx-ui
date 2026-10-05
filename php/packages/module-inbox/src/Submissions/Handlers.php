<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Submissions;

use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Config\Repository as Config;
use Psr\Log\LoggerInterface;
use Throwable;
use WebxUi\Inbox\Contracts\SubmissionHandler;
use WebxUi\Inbox\Events\SubmissionStored;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;

/**
 * The module's own listener of `SubmissionStored`: queues the handlers the config names (§2.19).
 *
 * `'*'` first, then the form's slug, each class once. Only classes from the config, never from
 * the database — a form's options are edited in the panel and over MCP, and a class name typed
 * there would be code chosen by whoever holds `inbox.manage`.
 */
final class Handlers
{
    public function __construct(
        private readonly Config $config,
        private readonly Bus $bus,
        private readonly LoggerInterface $log,
    ) {}

    public function handle(SubmissionStored $event): void
    {
        if ($event->repeated) {
            // The first one already went to the CRM; a double click is not a second subscriber.
            return;
        }

        $submission = $event->submission;

        foreach ($this->for($submission->form) as $class) {
            if (! is_a($class, SubmissionHandler::class, true)) {
                $this->fail($submission, $class, 'Not a SubmissionHandler');

                continue;
            }

            try {
                $this->bus->dispatch(new RunHandler($submission, $class));
            } catch (Throwable $exception) {
                // A queue that is down must not reach the visitor either: the submission is saved.
                $this->fail($submission, $class, $exception->getMessage());
            }
        }
    }

    /**
     * @return list<string>
     */
    public function for(Form $form): array
    {
        $configured = $this->config->get('webx-inbox.handlers', []);

        if (! is_array($configured)) {
            return [];
        }

        $classes = [
            ...(array) ($configured['*'] ?? []),
            ...(array) ($configured[$form->slug] ?? []),
        ];

        return array_values(array_unique(array_filter($classes, 'is_string')));
    }

    private function fail(Submission $submission, string $class, string $error): void
    {
        $this->log->error('webx-inbox: handler {handler} could not be queued for submission {id}.', [
            'handler' => $class,
            'id' => $submission->getKey(),
            'error' => $error,
        ]);

        $submission->log(SubmissionEvent::HANDLER_ERROR, $class, mb_substr($error, 0, 255));
    }
}
