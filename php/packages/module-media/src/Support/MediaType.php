<?php

declare(strict_types=1);

namespace WebxUi\Media\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * The handful of groups a person filters a library by.
 *
 * Deliberately coarse: "images, video, audio, documents, the rest" is how an editor looks for
 * something, and a filter listing forty mime types is a filter nobody uses.
 */
final class MediaType
{
    public const IMAGE = 'image';

    public const VIDEO = 'video';

    public const AUDIO = 'audio';

    public const DOCUMENT = 'document';

    public const OTHER = 'other';

    /** @var list<string> */
    private const PREFIXES = ['image/', 'video/', 'audio/'];

    /** @var list<string> */
    private const DOCUMENTS = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'text/markdown',
        'application/rtf',
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::IMAGE, self::VIDEO, self::AUDIO, self::DOCUMENT, self::OTHER];
    }

    public static function of(string $mime): string
    {
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($mime, $prefix)) {
                return rtrim($prefix, '/');
            }
        }

        return in_array($mime, self::DOCUMENTS, true) ? self::DOCUMENT : self::OTHER;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function filter(Builder $query, string $type): void
    {
        match ($type) {
            self::IMAGE, self::VIDEO, self::AUDIO => $query->where('mime', 'like', $type.'/%'),
            self::DOCUMENT => $query->whereIn('mime', self::DOCUMENTS),
            self::OTHER => $query
                ->whereNotIn('mime', self::DOCUMENTS)
                ->where(function (Builder $query): void {
                    foreach (self::PREFIXES as $prefix) {
                        $query->where('mime', 'not like', $prefix.'%');
                    }
                }),
            default => null,
        };
    }
}
