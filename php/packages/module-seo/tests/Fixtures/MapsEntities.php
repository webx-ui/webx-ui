<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WebxUi\Routing\Formatters\Slug;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;

/**
 * Entities with addresses in the registry, for the tests that bind an address to one (§18.2).
 */
trait MapsEntities
{
    protected function mapEntities(): void
    {
        Schema::create('mapped_entities', function (Blueprint $table): void {
            $table->id();
            $table->json('slug')->nullable();
            $table->boolean('published')->default(true);
            $table->timestamps();
        });

        app(RouteTypes::class)->register(new RouteType(type: 'mapped', model: MappedEntity::class, formatter: Slug::class));
    }

    /**
     * @param  string|array<string, string>  $slug
     */
    protected function entity(string|array $slug, bool $published = true): MappedEntity
    {
        return MappedEntity::query()->create([
            'slug' => is_string($slug) ? ['ru' => $slug] : $slug,
            'published' => $published,
        ]);
    }

    /** A new slug, the way an editor renames a page: the old address becomes an alias. */
    protected function rename(MappedEntity $entity, string $slug, string $locale = 'ru'): void
    {
        $entity->setTranslation('slug', $locale, $slug);
        $entity->save();
    }
}
