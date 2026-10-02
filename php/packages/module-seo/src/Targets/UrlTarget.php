<?php

declare(strict_types=1);

namespace WebxUi\Seo\Targets;

/**
 * An address on this site, and the entity behind it when the registry knew one (§18.2).
 *
 * Shared by donors, acceptors and exact rules. `path` is the address without its language
 * prefix — leading slash kept, query kept — as it was when the target was saved. For a bound
 * target it is only the last known spelling: the address that counts is the entity's canonical
 * one, which {@see UrlTargets::href()} works out on reading.
 */
final class UrlTarget
{
    public function __construct(
        public readonly string $locale,
        public readonly string $path,
        public readonly ?string $entityType = null,
        public readonly ?int $entityId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $row  a row with `locale`, `path`, `entity_type`, `entity_id`
     */
    public static function fromRow(array $row): self
    {
        $type = $row['entity_type'] ?? null;
        $id = $row['entity_id'] ?? null;

        return new self(
            is_string($row['locale'] ?? null) ? $row['locale'] : '',
            is_string($row['path'] ?? null) ? $row['path'] : '/',
            is_string($type) && $type !== '' ? $type : null,
            is_numeric($id) ? (int) $id : null,
        );
    }

    public function isBound(): bool
    {
        return $this->entityType !== null && $this->entityId !== null;
    }

    /**
     * The same page: the same entity in the same language, or — for addresses nobody owns — the
     * same path. A bound and an unbound target are never the same, even on one path, because
     * the bound one moves with its entity and the other does not.
     */
    public function sameAs(self $other): bool
    {
        if ($this->locale !== $other->locale || $this->isBound() !== $other->isBound()) {
            return false;
        }

        return $this->isBound()
            ? $this->entityType === $other->entityType && $this->entityId === $other->entityId
            : $this->path === $other->path;
    }

    /**
     * @return array{locale: string, path: string, entity_type: string|null, entity_id: int|null}
     */
    public function toRow(): array
    {
        return [
            'locale' => $this->locale,
            'path' => $this->path,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
        ];
    }
}
