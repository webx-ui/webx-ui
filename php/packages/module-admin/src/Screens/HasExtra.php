<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

use Illuminate\Database\Eloquent\Model;

/**
 * A record whose screen a project may add fields to, and the column those fields live in.
 *
 * A project that wants a "Price from" on a service writes a patch, not a migration: the field is
 * drawn by the patch, validated by its type, and stored under its name in `extra` (json). The
 * module never learns it was there, which is the point — a field for one client is not a column
 * for every site.
 *
 *     {{ $service->extra('price-from') }}
 *
 * Read through the field's type, the way `ScreenValues::resolve()` reads settings: a localized
 * field in the language of the page, a picture as an address rather than a library key. A field
 * the patch no longer draws is handed back as it was stored — the module cannot know what it was.
 *
 * @mixin Model
 */
trait HasExtra
{
    /** The screen whose nodes describe the fields in `extra`. */
    abstract public function extraScreen(): string;

    public function initializeHasExtra(): void
    {
        $this->mergeCasts([$this->extraColumn() => 'array']);
    }

    public function extraColumn(): string
    {
        return 'extra';
    }

    /**
     * What the site prints for one field of the project.
     */
    public function extra(string $name, ?string $locale = null): mixed
    {
        $stored = $this->extraRaw($name);
        $node = app(ScreenRecord::class)->field($this->extraScreen(), $name);

        return $node === null ? $stored : app(ScreenValues::class)->resolve($node, $stored, $locale);
    }

    /**
     * As it lies in the column: one field, or all of them.
     *
     * @return ($name is null ? array<string, mixed>|null : mixed)
     */
    public function extraRaw(?string $name = null): mixed
    {
        $values = $this->getAttribute($this->extraColumn());
        $values = is_array($values) ? $values : null;

        if ($name === null) {
            return $values;
        }

        return $values[$name] ?? null;
    }

    /**
     * Lay the fields that came in over the ones already there. Not saved: whoever calls this
     * decides when the record is written, and a record with a draft writes this into the draft.
     *
     * @param  array<string, mixed>  $values
     */
    public function mergeExtra(array $values): static
    {
        if ($values === []) {
            return $this;
        }

        $this->setAttribute(
            $this->extraColumn(),
            app(ScreenRecord::class)->merge($this->extraScreen(), $this->extraRaw(), $values),
        );

        return $this;
    }
}
