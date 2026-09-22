<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool as McpTool;
use WebxUi\Mcp\Calls\Recorder;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Grants\Grants;
use WebxUi\Mcp\Permissions;
use WebxUi\Mcp\Registry\BoundTool;
use WebxUi\Mcp\Scopes;

/**
 * A module's tool as `laravel/mcp` serves it.
 *
 * The module declared a name, a description, a JSON Schema and a closure; this is the class
 * the transport expects around them. The schema goes out as the module wrote it — the
 * builder `laravel/mcp` offers is for tools written as classes, and a module's schema is
 * already the array the protocol wants. The scope, the grant and the permission are checked
 * here, once, before any handler runs: a handler never has to ask who is calling.
 */
final class RegistryTool extends McpTool
{
    public function __construct(private readonly BoundTool $bound) {}

    /**
     * Whether the caller is shown this tool at all.
     *
     * An agent shown forty tools and refused on half of them spends its attempts and tells
     * the person it cannot do what it was never going to be allowed to. So a tool the caller
     * may not use — one behind a permission they do not hold, or one that writes on a
     * connection made read-only — is left out of the list rather than listed and refused.
     * `laravel/mcp` asks this through the container, and on the local stdio server the
     * request it resolves is an empty one with no user, which is right: there is nobody
     * there to refuse, so everything is shown.
     */
    public function shouldRegister(HttpRequest $request, Grants $grants): bool
    {
        $user = $request->user();

        return Permissions::allows($user, $this->bound)
            && $grants->refusal($user, $this->bound->tool) === null;
    }

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

    /**
     * The call, written down whichever way it goes: the log is around the whole of it, so
     * that a refusal at the door is a row a person can read, not only an answer the agent got.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        $arguments = $request->all();

        return $this->recorder()->record(
            $this->bound,
            $user,
            $arguments,
            fn (): Response|ResponseFactory => $this->attempt($user, $arguments),
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function attempt(?Authenticatable $user, array $arguments): Response|ResponseFactory
    {
        $scope = $this->bound->scope();

        if (! Scopes::allows($user, $scope)) {
            return Response::error(
                "The token behind this call does not carry the [{$scope}] scope, which [{$this->name()}] needs."
            );
        }

        // The terms the person set when they let the agent in, which are stronger than the
        // permissions they hold: hidden from the list too, but a client that remembers a
        // tool from before the terms changed still has to be told no.
        $refusal = $this->grants()->refusal($user, $this->bound->tool);

        if ($refusal !== null) {
            return Response::error("[{$this->name()}] is not allowed on this connection. {$refusal}");
        }

        // The administrator's own permissions, the ones the panel asks for the same work. Hidden
        // from the list too; checked again here for a client that remembers the tool from a
        // listing made by somebody else, or before a role was taken away.
        if (! Permissions::allows($user, $this->bound)) {
            $needed = implode('] or [', $this->bound->permissions());

            return Response::error(
                "The administrator this call acts as does not hold the [{$needed}] permission, which [{$this->name()}] needs."
            );
        }

        try {
            $result = ($this->bound->tool->handler)($arguments, $user);
        } catch (ToolFailure $failure) {
            return Response::error($failure->getMessage());
        }

        return Results::toResponse($result);
    }

    /**
     * Resolved per call rather than injected: `laravel/mcp` builds this class around the
     * module's tool with no container in between, the way the server's boot does.
     */
    private function grants(): Grants
    {
        return Container::getInstance()->make(Grants::class);
    }

    private function recorder(): Recorder
    {
        return Container::getInstance()->make(Recorder::class);
    }
}
