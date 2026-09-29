<?php

declare(strict_types=1);

namespace WebxUi\Admin\Uploads;

use Closure;
use InvalidArgumentException;

/**
 * What chunked uploads may be for, filled from service providers — the register `HistoryTypes`
 * is for the journal.
 *
 * The purpose comes from the browser, so this is the white list of what may be named there, and
 * the place where the module that will take the file says who may send it and what it has to be:
 *
 *     $purposes->register('catalog.video', permission: 'catalog.manage',
 *         types: ['video/mp4', 'video/webm'], maxBytes: 2048 * 1024 * 1024);
 *
 * A panel that registers none has no chunked uploads at all: the endpoint refuses every one.
 */
final class UploadPurposes
{
    /** @var array<string, array{list<string>, list<string>|(Closure(): list<string>), int|(Closure(): ?int)|null}> */
    private array $purposes = [];

    /**
     * @param  string|list<string>  $permission  the permission, or several of which any will do
     * @param  list<string>|(Closure(): list<string>)  $types  MIME types, `video/*` for a family
     * @param  int|(Closure(): ?int)|null  $maxBytes  a closure reads the config when it is asked
     */
    public function register(
        string $purpose,
        string|array $permission,
        array|Closure $types = [],
        int|Closure|null $maxBytes = null,
    ): void {
        if (preg_match('/^[a-z0-9_-]+(\.[a-z0-9_-]+)*$/', $purpose) !== 1 || strlen($purpose) > 64) {
            throw new InvalidArgumentException("[{$purpose}] is not an upload purpose: lowercase words joined by dots, at most 64 characters.");
        }

        $permissions = array_values(array_filter((array) $permission, static fn (string $one): bool => trim($one) !== ''));

        // An upload anybody signed in may start is a disk anybody signed in may fill.
        if ($permissions === []) {
            throw new InvalidArgumentException("The upload purpose [{$purpose}] needs the permission an upload for it is started behind.");
        }

        $this->purposes[$purpose] = [$permissions, $types, $maxBytes];
    }

    public function find(string $purpose): ?UploadPurpose
    {
        if (! isset($this->purposes[$purpose])) {
            return null;
        }

        [$permissions, $types, $maxBytes] = $this->purposes[$purpose];

        // Read when asked rather than when registered: a module's limits live in its config, and
        // a provider runs before a test or a site has had its say about them.
        return new UploadPurpose(
            $purpose,
            $permissions,
            array_values($types instanceof Closure ? $types() : $types),
            $maxBytes instanceof Closure ? $maxBytes() : $maxBytes,
        );
    }

    /** @return array<string, UploadPurpose> */
    public function all(): array
    {
        $all = [];

        foreach (array_keys($this->purposes) as $purpose) {
            $found = $this->find($purpose);

            if ($found !== null) {
                $all[$purpose] = $found;
            }
        }

        return $all;
    }

    public function forget(): void
    {
        $this->purposes = [];
    }
}
