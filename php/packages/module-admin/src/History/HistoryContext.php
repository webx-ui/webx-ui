<?php

declare(strict_types=1);

namespace WebxUi\Admin\History;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Who is changing things right now, and through which door (WEBX_UI_HISTORY.md §4).
 *
 * A module saving a product does not know whether the editor clicked Save, an agent called a
 * tool or a nightly import ran — and should not have to: the doors say it. The panel's API
 * middleware sets `panel` and the administrator, the MCP server sets `mcp` with the
 * administrator who connected the agent and the grant, and a run (`History::run`) sets itself
 * as the parent of everything written inside it.
 *
 * Nobody having said anything is an answer too: a console command is `console`, and an HTTP
 * request that came through none of the panel's doors — a site's own API — is `api`, with
 * whoever the default guard knows.
 *
 * Scoped, not a singleton: a long-lived worker serves many requests, and the second one must
 * not inherit the first one's administrator.
 */
final class HistoryContext
{
    public const PANEL = 'panel';

    public const MCP = 'mcp';

    public const IMPORT = 'import';

    public const BULK = 'bulk';

    public const API = 'api';

    public const CONSOLE = 'console';

    public const SOURCES = [self::PANEL, self::MCP, self::IMPORT, self::BULK, self::API, self::CONSOLE];

    private ?string $source = null;

    private ?Authenticatable $admin = null;

    private bool $adminKnown = false;

    private ?int $grantId = null;

    private ?int $runId = null;

    public function __construct(private readonly Application $app) {}

    /**
     * Say who is acting for the rest of the request. What the doors call; a module never does.
     */
    public function set(string $source, ?Authenticatable $admin = null, ?int $grantId = null): void
    {
        $this->source = self::checked($source);
        $this->admin = $admin;
        $this->adminKnown = true;
        $this->grantId = $grantId;
    }

    /**
     * Act as somebody for the length of `$work`, and be who we were afterwards — for a door
     * inside a door, an MCP call made on a request that already had a context.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function during(string $source, ?Authenticatable $admin, ?int $grantId, Closure $work): mixed
    {
        $was = [$this->source, $this->admin, $this->adminKnown, $this->grantId];

        $this->set($source, $admin, $grantId);

        try {
            return $work();
        } finally {
            [$this->source, $this->admin, $this->adminKnown, $this->grantId] = $was;
        }
    }

    /**
     * Put everything written inside `$work` under a run, and optionally under another source:
     * the rows of an import are `import` even when the import was started from the panel.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function inRun(?int $runId, ?string $source, Closure $work): mixed
    {
        $was = [$this->runId, $this->source];

        $this->runId = $runId;

        if ($source !== null) {
            $this->source = self::checked($source);
        }

        try {
            return $work();
        } finally {
            [$this->runId, $this->source] = $was;
        }
    }

    public function source(): string
    {
        return $this->source ?? ($this->app->runningInConsole() ? self::CONSOLE : self::API);
    }

    public function admin(): ?Authenticatable
    {
        if ($this->adminKnown) {
            return $this->admin;
        }

        // Nobody said: whoever the application's own guard has, which is nobody in a console.
        return $this->app->runningInConsole() ? null : $this->app->make(AuthFactory::class)->guard()->user();
    }

    public function adminId(): ?int
    {
        $id = $this->admin()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    public function adminName(): string
    {
        $admin = $this->admin();

        if ($admin === null) {
            return '';
        }

        $name = $admin instanceof Model ? $admin->getAttribute('name') : (isset($admin->name) ? $admin->name : null);

        return is_scalar($name) ? mb_substr((string) $name, 0, 255) : '';
    }

    public function grantId(): ?int
    {
        return $this->grantId;
    }

    public function runId(): ?int
    {
        return $this->runId;
    }

    private static function checked(string $source): string
    {
        if (! in_array($source, self::SOURCES, true)) {
            throw new InvalidArgumentException(
                "[{$source}] is not a source of the journal; one of ".implode(', ', self::SOURCES).'.'
            );
        }

        return $source;
    }
}
