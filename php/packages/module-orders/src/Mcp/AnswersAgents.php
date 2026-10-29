<?php

declare(strict_types=1);

namespace WebxUi\Orders\Mcp;

use Illuminate\Validation\ValidationException;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * What the tools of the orders share: whether a call is a dry run, and a refusal of the service's
 * checks turned into words the agent reads, field by field.
 */
trait AnswersAgents
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return ($arguments[Tool::DRY_RUN] ?? false) === true;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function attempt(callable $work): mixed
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        }
    }
}
