<?php

declare(strict_types=1);

namespace WebxUi\Menu\Models;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use WebxUi\Localization\HasTranslations;
use WebxUi\Menu\Exceptions\MenuException;
use WebxUi\Menu\Menus;

/**
 * A menu: a key a template asks for, and a name the panel shows.
 *
 * A declared menu — one the configuration names — is locked in two places rather than one.
 * Its key cannot change, because `menu('header')` in a view is a reference to that spelling;
 * and it cannot be deleted, because the template would then be asking for a menu that has
 * never existed. Emptying one is allowed: a header with no items is a decision, an unknown
 * menu is a mistake.
 *
 * @property int $id
 * @property string $key
 * @property array<string, string>|string|null $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Menu extends Model
{
    use HasTranslations;

    protected $table = 'menus';

    /** @var list<string> */
    protected $fillable = ['key', 'title'];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['title'];
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_id');
    }

    /** Is this one of the menus the configuration names — that is, one a template asks for? */
    public function isDeclared(): bool
    {
        return Container::getInstance()->make(Menus::class)->isDeclared($this->key);
    }

    /**
     * The name to show, with the configuration's own as the answer until somebody has saved
     * one — which is the whole of what "the row appears on first save" means for a reader.
     */
    public function label(?string $locale = null): string
    {
        $written = $this->getTranslation('title', $locale);

        if (is_string($written) && trim($written) !== '') {
            return $written;
        }

        return Container::getInstance()->make(Menus::class)->declaredTitle($this->key) ?? $this->key;
    }

    protected static function booted(): void
    {
        static::deleting(static function (Menu $menu): void {
            if ($menu->isDeclared()) {
                throw MenuException::declaredUndeletable($menu->key);
            }
        });

        static::updating(static function (Menu $menu): void {
            if (! $menu->isDirty('key')) {
                return;
            }

            // The spelling it had, not the one being written: renaming `header` away is the
            // move that breaks a template, and renaming something else *into* `header` only
            // collides with the unique index.
            $was = (string) $menu->getRawOriginal('key');

            if (Container::getInstance()->make(Menus::class)->isDeclared($was)) {
                throw MenuException::declaredUnrenamable($was);
            }
        });
    }
}
