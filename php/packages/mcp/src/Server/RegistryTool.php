<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Server;

use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool as McpTool;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Registry\BoundTool;
use WebxUi\Mcp\Scopes;

/**
 * A module's tool as `laravel/mcp` serves it.
 *
 * The module declared a name, a description, a JSON Schema and a closure; this is the class
 * the transport expects around them. The schema goes out as the module wrote it — the
 * builder `laravel/mcp` offers is for tools written as classes, and a module's schema is
 * already the array the protocol wants. The scope is checked here, once, before any handler
 * runs: a handler never has to ask who is calling.
 */
final class RegistryTool extends McpTool
{
    public function __construct(private readonly BoundTool $bound) {}

    public function name(): string
    {
        return $this->bound->fullName();
    }

    public function title(): string
    {
        return Str::headline($this->bound->tool->name);
    }

    public function description(): string
    {
        return $this->bound->tool->description;
    }

    /**
     * What the model is told about the tool's behaviour before calling it. `readOnlyHint` is
     * the one that matters: a client may skip its confirmation for a tool that only looks.
     *
     * @return array<string, bool>
     */
    public function annotations(): array
    {
        $mutating = $this->bound->tool->mutating;

        return [
            'readOnlyHint' => ! $mutating,
            'idempotentHint' => ! $mutating,
            'openWorldHint' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $schema = $this->bound->tool->inputSchema;

        if (($schema['properties'] ?? []) === []) {
            // An empty map encodes as `[]`, and the schema says an object.
            $schema['properties'] = (object) [];
        }

        return [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'inputSchema' => $schema,
            'annotations' => $this->annotations(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        $scope = $this->bound->scope();

        if (! Scopes::allows($user, $scope)) {
            return Response::error(
                "The token behind this call does not carry the [{$scope}] scope, which [{$this->name()}] needs."
            );
        }

        try {
            $result = ($this->bound->tool->handler)($request->all(), $user);
        } catch (ToolFailure $failure) {
            return Response::error($failure->getMessage());
        }

        return Results::toResponse($result);
    }
}
