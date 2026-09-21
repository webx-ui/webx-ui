<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Calls;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Throwable;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Grants\Grants;
use WebxUi\Mcp\Registry\BoundTool;

/**
 * Writes the call log, around every tool call, in one place.
 *
 * Wrapped around the whole of a call rather than around the handler: a refusal at the door —
 * a missing scope, a read-only connection, a permission the administrator does not hold — is
 * the row a person most wants to see, because it is an agent trying what it may not. The
 * handler's own failure and anything it threw are rows too; a thrown exception is written
 * down and thrown on, so that what the transport does with it is unchanged.
 *
 * What never lands here is a secret. The arguments are the agent's own words, and no tool
 * asks for a password — but a blanket over anything named like one costs nothing, and the
 * token and the headers the call came in with are not looked at in the first place.
 */
final class Recorder
{
    /** Argument names whose values are blanked before the arguments are written down. */
    private const SECRET = '/(password|passwd|secret|token|api[_-]?key|authorization|cookie|credential)/i';

    private const REDACTED = '[redacted]';

    /** How much of a refusal's text is kept. */
    private const ERROR_LENGTH = 2000;

    public function __construct(
        private readonly Config $config,
        private readonly Grants $grants,
    ) {}

    /**
     * Run the call and write down how it went.
     *
     * @param  array<string, mixed>  $arguments
     * @param  Closure(): (Response|ResponseFactory)  $attempt
     */
    public function record(
        BoundTool $tool,
        ?Authenticatable $user,
        array $arguments,
        Closure $attempt,
    ): Response|ResponseFactory {
        if (! $this->enabled()) {
            return $attempt();
        }

        $started = hrtime(true);

        try {
            $response = $attempt();
        } catch (Throwable $failure) {
            $this->write($tool, $user, $arguments, $started, false, $this->describe($failure));

            throw $failure;
        }

        [$ok, $error] = $this->outcome($response);

        $this->write($tool, $user, $arguments, $started, $ok, $error);

        return $response;
    }

    public function enabled(): bool
    {
        return (bool) $this->config->get('webx-mcp.calls.enabled', true);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function write(
        BoundTool $tool,
        ?Authenticatable $user,
        array $arguments,
        int $started,
        bool $ok,
        ?string $error,
    ): void {
        Call::query()->create([
            'cms_user_id' => $this->userId($user),
            'grant_id' => $this->grants->forUser($user)?->id,
            'tool' => mb_substr($tool->fullName(), 0, 128),
            'arguments' => $this->arguments($arguments),
            'dry_run' => $tool->tool->mutating && $tool->tool->isDryRun($arguments),
            'ok' => $ok,
            'error' => $error === null ? null : mb_substr($error, 0, self::ERROR_LENGTH),
            'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Whether the agent got an answer, and if not, the words it was refused with.
     *
     * Two shapes of "no" reach the agent: the protocol's error, which is every refusal the
     * door makes and every {@see ToolFailure}; and a handler that
     * answered `['ok' => false, 'reason' => …]`, which the protocol calls a success and the
     * person reading the log would not.
     *
     * @return array{bool, string|null}
     */
    private function outcome(Response|ResponseFactory $response): array
    {
        if ($response instanceof Response) {
            return $response->isError() ? [false, $this->text($response)] : [true, null];
        }

        foreach ($response->responses() as $one) {
            if ($one instanceof Response && $one->isError()) {
                return [false, $this->text($one)];
            }
        }

        $structured = $response->getStructuredContent();

        if (is_array($structured) && ($structured['ok'] ?? null) === false) {
            $reason = $structured['reason'] ?? $structured['message'] ?? $structured['error'] ?? null;

            return [false, is_string($reason) ? $reason : null];
        }

        return [true, null];
    }

    private function text(Response $response): string
    {
        return (string) $response->content();
    }

    private function describe(Throwable $failure): string
    {
        $class = (new \ReflectionClass($failure))->getShortName();
        $message = trim($failure->getMessage());

        return $message === '' ? $class : "{$class}: {$message}";
    }

    private function userId(?Authenticatable $user): ?int
    {
        $id = $user?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    /**
     * The arguments as JSON, secrets blanked, cut to the configured length.
     *
     * Cut as text rather than as structure: what is kept is for reading, and a call whose
     * `body` was a page of Markdown is a call to remember, not a page to store twice.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function arguments(array $arguments): ?string
    {
        if ($arguments === []) {
            return null;
        }

        $json = json_encode(
            $this->scrub($arguments),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        if ($json === false) {
            return null;
        }

        $limit = max(0, (int) $this->config->get('webx-mcp.calls.arguments_length', 4000));

        return mb_strlen($json) > $limit ? mb_substr($json, 0, $limit).'…' : $json;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function scrub(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET, $key) === 1) {
                $values[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $values[$key] = $this->scrub($value);
            }
        }

        return $values;
    }
}
