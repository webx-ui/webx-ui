<?php

declare(strict_types=1);

namespace WebxUi\Admin\Uploads;

use WebxUi\Admin\Contracts\HasPermissions;

/**
 * What a module said a chunked upload may be for: who may start one, which types it takes and
 * how large it may get.
 *
 * The type is what the browser declared, checked when the session is created so that a wrong
 * file is refused before a gigabyte of it has crossed the wire. It is a courtesy and not a
 * guarantee: the consumer checks the content once the file is whole, because a declared type is
 * whatever the sender wrote.
 */
final readonly class UploadPurpose
{
    /**
     * @param  list<string>  $permissions  any of them lets an administrator start an upload
     * @param  list<string>  $types  MIME types, `video/*` for a family; empty takes anything
     * @param  int|null  $maxBytes  null leaves the size to the disk
     */
    public function __construct(
        public string $purpose,
        public array $permissions,
        public array $types = [],
        public ?int $maxBytes = null,
    ) {}

    public function allows(mixed $admin): bool
    {
        if (! $admin instanceof HasPermissions) {
            return false;
        }

        foreach ($this->permissions as $permission) {
            if ($admin->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function accepts(string $type): bool
    {
        if ($this->types === []) {
            return true;
        }

        $type = strtolower(trim(explode(';', $type)[0]));

        foreach ($this->types as $allowed) {
            $allowed = strtolower($allowed);

            if ($allowed === $type) {
                return true;
            }

            if (str_ends_with($allowed, '/*') && str_starts_with($type, substr($allowed, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
