<?php

declare(strict_types=1);

namespace WebxUi\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use WebxUi\NestedSet\HasNestedSet;

/**
 * A folder in the library.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $title
 */
class MediaDirectory extends Model
{
    use HasNestedSet;

    protected $fillable = ['title'];

    /**
     * @return HasMany<MediaFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(MediaFile::class, 'directory_id');
    }

    /**
     * The one folder that cannot be renamed away, moved or deleted.
     *
     * Every file belongs to a folder, so the library has to have a floor; the panel shows it
     * under a translated name rather than the one stored here.
     */
    public function isLibraryRoot(): bool
    {
        return $this->parent_id === null;
    }
}
