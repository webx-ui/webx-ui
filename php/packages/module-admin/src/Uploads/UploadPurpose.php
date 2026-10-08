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
 *
 * A purpose may name extensions instead, or as well — a library whose white list is written in
 * extensions, and a browser that declares no type at all for a HEIC. Either list letting the
 * file through is enough.
 */
final readonly class UploadPurpose
{
    /**
     * @param  list<string>  $permissions  any of them lets an administrator start an upload
     * @param  list<string>  $types  MIME types, `video/*` for a family; empty takes anything
     * @param  int|null  $maxBytes  null leaves the size to the disk
     * @param  list<string>  $extensions  without the dot; matched against the file's name
     */
    public function __construct(
        public string $purpose,
        public array $permissions,
        public array $types = [],
        public ?int $maxBytes = null,
        public array $extensions = [],
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

    public function accepts(string $type, string $name = ''): bool
    {
        if ($this->types === [] && $this->extensions === []) {
            return true;
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($extension !== '' && in_array($extension, array_map('strtolower', $this->extensions), true)) {
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
