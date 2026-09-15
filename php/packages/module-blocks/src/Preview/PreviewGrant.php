<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Preview;

use Illuminate\Http\Request;

/**
 * A verified preview token: what it opens, for whom, and until when.
 *
 * Left on the request by the preview route, the way `Resolution` is left by the resolver, so
 * that whatever answers afterwards — the handler deciding whether an unpublished entity may
 * be shown, a view printing a "draft" ribbon — can ask `PreviewGrant::of($request)`.
 */
final readonly class PreviewGrant
{
    public const ATTRIBUTE = 'webx.preview';

    public function __construct(
        public string $type,
        public string $id,
        public ?int $adminId,
        public int $expires,
    ) {}

    public static function of(Request $request): ?self
    {
        $grant = $request->attributes->get(self::ATTRIBUTE);

        return $grant instanceof self ? $grant : null;
    }
}
