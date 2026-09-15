<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Preview;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use WebxUi\Routing\RouteTypes;

/**
 * The draft of an entity, shown as the page it will be.
 *
 * `/_preview/{type}/{id}?token=…` is a service address, not a canonical one with a flag: a
 * record that was never published has no address yet, and the preview is needed most while
 * it is being made. The route does what the resolver would do for the real address — find the
 * type, load the entity, hand both to the handler with a `Resolution` — and the handler answers
 * with the same view it answers the site with. There is no rendering "for the panel": what
 * differs is the entity (its draft laid over its columns), the block types (their drafts), and
 * the headers (`no-store`, `noindex`).
 *
 *     Preview::url($page, adminId: 7);              // the link the panel puts in its <iframe>
 *     PreviewGrant::of($request) !== null;          // inside a handler: is this a preview?
 */
final class Preview
{
    public function __construct(
        private readonly RouteTypes $types,
        private readonly PreviewToken $token,
        private readonly UrlGenerator $url,
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    /**
     * A signed link to the draft of an entity, good for the configured number of minutes.
     *
     * @param  int|null  $adminId  Who asked; carried in the token for the record, and for a
     *                             handler that wants to know.
     */
    public function url(Model $entity, ?int $adminId = null, ?int $minutes = null): string
    {
        $type = $this->types->forEntity($entity);
        $id = (string) $entity->getKey();
        $expires = time() + 60 * max(1, $minutes ?? $this->minutes());

        return $this->url->route('webx.blocks.preview', [
            'type' => $type->type,
            'id' => $id,
            'token' => $this->token->make($type->type, $id, $adminId, $expires),
        ]);
    }

    /** The grant in a request's token, if the token is genuine and for this entity. */
    public function grant(Request $request, string $type, string $id): ?PreviewGrant
    {
        $token = $request->query('token');

        return is_string($token) && $token !== ''
            ? $this->token->verify($token, $type, $id)
            : null;
    }

    /** Whether the current request is a preview — for code with no request in hand. */
    public function active(): bool
    {
        if (! $this->container->bound('request')) {
            return false;
        }

        $request = $this->container->make('request');

        return $request instanceof Request && PreviewGrant::of($request) !== null;
    }

    public function minutes(): int
    {
        return max(1, (int) $this->config->get('webx-blocks.preview.ttl', 60));
    }
}
