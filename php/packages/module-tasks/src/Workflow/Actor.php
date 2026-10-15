<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Workflow;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Tasks\Exceptions\TaskRefused;

/**
 * Who is doing something to a task: what the journal writes down, and what the rules about
 * people are checked against.
 *
 * The difference between a person and an agent is decided by whoever builds the actor — the
 * panel builds a person, the MCP tools decide by the token — and never by the operation: an
 * operation only asks {@see self::isPerson()}.
 */
final readonly class Actor
{
    private function __construct(
        public ActorType $type,
        public string $name,
        public ?int $adminId = null,
    ) {}

    /** A person — signed in to the panel, or acting through an assistant they agreed to. */
    public static function admin(Authenticatable $admin): self
    {
        $name = $admin instanceof Model ? (string) $admin->getAttribute('name') : '';
        $id = $admin->getAuthIdentifier();

        return new self(
            ActorType::Admin,
            trim($name) !== '' ? trim($name) : 'admin #'.(is_scalar($id) ? (string) $id : '?'),
            is_numeric($id) ? (int) $id : null,
        );
    }

    /**
     * An agent under the name of its session. `$as` is the administrator whose token it holds,
     * kept for `created_by` and nothing else: what the agent did is the agent's.
     */
    public static function agent(string $name, ?Authenticatable $as = null): self
    {
        $name = trim($name);

        if ($name === '') {
            throw TaskRefused::because('agent-name');
        }

        $id = $as?->getAuthIdentifier();

        return new self(ActorType::Agent, mb_substr($name, 0, 255), is_numeric($id) ? (int) $id : null);
    }

    /** The module itself. */
    public static function system(): self
    {
        return new self(ActorType::System, 'system');
    }

    public function isPerson(): bool
    {
        return $this->type === ActorType::Admin;
    }

    public function isAgent(): bool
    {
        return $this->type === ActorType::Agent;
    }
}
