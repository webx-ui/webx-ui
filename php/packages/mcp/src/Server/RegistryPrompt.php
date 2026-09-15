<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Server;

use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt as McpPrompt;
use Laravel\Mcp\Server\Prompts\Argument;
use WebxUi\Mcp\Prompt;

/**
 * A module's prompt as `laravel/mcp` serves it. The handler returns the text of the request
 * the agent is to be given — one string, or several for several messages.
 */
final class RegistryPrompt extends McpPrompt
{
    public function __construct(private readonly Prompt $prompt) {}

    public function name(): string
    {
        return $this->prompt->name;
    }

    public function title(): string
    {
        return Str::headline($this->prompt->name);
    }

    public function description(): string
    {
        return $this->prompt->description;
    }

    /**
     * @return list<Argument>
     */
    public function arguments(): array
    {
        $arguments = [];

        foreach ($this->prompt->arguments as $name => $description) {
            $arguments[] = new Argument((string) $name, $description);
        }

        return $arguments;
    }

    /**
     * @return list<Response>
     */
    public function handle(Request $request): array
    {
        $result = ($this->prompt->handler)($request->all());

        $messages = is_array($result) ? array_values($result) : [$result];

        return array_map(
            static fn (mixed $message): Response => $message instanceof Response ? $message : Response::text(Results::toText($message)),
            $messages,
        );
    }
}
