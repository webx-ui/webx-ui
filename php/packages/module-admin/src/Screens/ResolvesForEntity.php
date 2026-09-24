<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * A field type whose reading depends on the record whose page the value is printed on.
 *
 * `wx-collection` with "related to this page": the recipes of *this* service, in a block the
 * editor put on every service at once. Only the renderer of blocks knows the page — it is handed
 * the entity whose content it prints — so it asks a type that says this, and no other, with the
 * entity. Every other door (a settings value, a block drawn on its own sample) goes through
 * {@see FieldType::resolve()}, which is the reading with no page.
 */
interface ResolvesForEntity extends FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @param  object|null  $entity  The record whose page this is; null — no page.
     */
    public function resolveFor(mixed $stored, array $node, ?object $entity, ?string $locale = null): mixed;
}
