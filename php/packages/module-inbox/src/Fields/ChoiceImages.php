<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Fields;

use Illuminate\Container\Container;
use Throwable;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Media\Screens\MediaValues;

/**
 * The picture of a choice drawn as a card (FORM_STEPS §5.1): a file of the media library, kept
 * as `{ path }` and turned into an address only when the page is drawn — as every other picture
 * of the site is, so a library moved to another disk changes nothing written before.
 *
 * The library is optional. Without it a key already written is kept as it is and shows nothing,
 * and nothing new can be picked: the panel has no field to pick with, an agent has no file to name.
 * A file gone from the library is an empty place on the card, never a 500.
 */
final class ChoiceImages
{
    /** Whether the site has the library: `module-media` installed and its provider loaded. */
    public static function available(): bool
    {
        return class_exists(MediaValues::class) && Container::getInstance()->bound(MediaFiles::class);
    }

    /**
     * What is kept of a picture as it arrived — `{ id }`, `{ path }`, a key, the whole object of
     * `media_get_file` — : the key alone, the card's label says what the choice is.
     *
     * @return array{path: string}|null
     */
    public static function store(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (self::available()) {
            $path = self::values()->normalize($value)['path'] ?? null;
        } elseif (is_array($value) && ! array_is_list($value)) {
            $path = $value['path'] ?? null;
        } else {
            $path = is_string($value) && ! ctype_digit($value) ? trim($value) : null;
        }

        return is_string($path) && $path !== '' ? ['path' => $path] : null;
    }

    /**
     * The addresses of the pictures of these choices, by key — one query of the library for the
     * whole form, however many cards it has.
     *
     * @param  list<string>  $paths
     * @return array<string, array{src: string, srcset: ?string, width: ?int, height: ?int}>
     */
    public static function resolve(array $paths): array
    {
        $paths = array_values(array_unique(array_filter($paths, static fn (string $path): bool => $path !== '')));

        if ($paths === [] || ! self::available()) {
            return [];
        }

        try {
            $media = self::values();
            $stored = array_map(static fn (string $path): array => ['path' => $path], $paths);
            $media->preload($stored);
            $found = [];

            foreach ($stored as $one) {
                $picture = $media->resolve($one);
                $src = is_string($picture['url'] ?? null) && $picture['url'] !== '' ? $picture['url'] : null;

                if ($src === null) {
                    continue;
                }

                $thumb = is_string($picture['thumb'] ?? null) && $picture['thumb'] !== $src ? $picture['thumb'] : null;
                $width = is_int($picture['width'] ?? null) ? $picture['width'] : null;

                $found[$one['path']] = [
                    'src' => $src,
                    'srcset' => $thumb !== null && $width !== null && $width > 320 ? "{$thumb} 320w, {$src} {$width}w" : null,
                    'width' => $width,
                    'height' => is_int($picture['height'] ?? null) ? $picture['height'] : null,
                ];
            }

            return $found;
        } catch (Throwable $e) {
            // A library that cannot be read takes the pictures off the cards, not the form off the page.
            report($e);

            return [];
        }
    }

    private static function values(): MediaValues
    {
        return Container::getInstance()->make(MediaValues::class);
    }
}
