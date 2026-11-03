<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository;

/**
 * Who clicked (spec §20.7): an administrator of the panel or the agent — «admin #N», which the
 * journal's screen turns into the name — or the source as it is, code or the poll.
 */
final readonly class Actors
{
    public function __construct(
        private Repository $config,
        private Auth $auth,
    ) {}

    public function adminId(string $source): ?int
    {
        $guard = $this->config->get('webx-auth.guard');

        if (! in_array($source, ['panel', 'mcp'], true) || ! is_string($guard) || $guard === '' || $this->config->get("auth.guards.{$guard}") === null) {
            return null;
        }

        $id = $this->auth->guard($guard)->id();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    /** «admin #5» for the journal, or the source where no administrator is known. */
    public function who(string $source): string
    {
        $id = $this->adminId($source);

        return $id !== null ? "admin #{$id}" : $source;
    }
}
