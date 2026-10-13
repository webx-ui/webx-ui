<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Editing\EditedRecords;
use WebxUi\Admin\Editing\LastChange;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\SiteBar\Loader;
use WebxUi\Admin\SiteBar\Publishing;
use WebxUi\Admin\SiteBar\SiteBar;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Resolver;
use WebxUi\Routing\RouteTypes;

/**
 * The three addresses of the panel on the site (WEBX_UI_SITE_BAR.md): the script a page loads,
 * the screen its frame opens, and what that screen asks about the page.
 */
final class SiteBarController
{
    private const IMMUTABLE = 'public, max-age=31536000, immutable';

    public function __construct(
        private readonly Container $container,
        private readonly SiteBar $bar,
    ) {}

    /**
     * `/cms/_bar/loader.{hash}.js`. Under the hash it was asked for it is cached for good; a page
     * cached with an older hash still gets a working script, only not one to keep.
     */
    public function loader(Loader $loader, string $hash): Response
    {
        return new Response($loader->body(), 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'Cache-Control' => $hash === $loader->hash() ? self::IMMUTABLE : 'no-cache',
        ]);
    }

    /**
     * `/cms/_bar` — the panel itself, the same page and bundle as every other address of it; the
     * front end draws this route without the navigation. Framed by the site only: the header
     * keeps anybody else from putting the panel under their own page.
     */
    public function screen(ShellController $shell): Response
    {
        return response($shell(), 200, ['X-Frame-Options' => 'SAMEORIGIN']);
    }

    /**
     * `GET /api/cms/site-bar/context?url=/pricing` — what the page in front of the bar is (§6).
     *
     * Read with the same registry the page itself was answered with, so the bar and the site
     * never disagree about whose address it is. Only an address of this site: a host, a scheme
     * or `//` is refused rather than stripped, since a path cut out of somebody else's address
     * is a question nobody asked.
     */
    public function context(Request $request): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'locale' => ['nullable', 'string', 'max:16'],
        ]);

        $path = $this->path((string) $request->query('url'));
        $user = $request->user();

        if (! $user instanceof HasPermissions) {
            throw new AccessDeniedHttpException((string) __('webx-admin::site-bar.forbidden'));
        }

        $locale = $request->query('locale');
        [$type, $entity, $locale] = $this->find($path, is_string($locale) && $locale !== '' ? $locale : null);
        $link = $type === null ? null : $this->bar->link($type);
        $sections = $this->bar->sections($user, $entity, $type);

        // Nothing here this administrator may use. Said rather than drawn as an empty bar —
        // which is also how a record they have no right to stays unnamed.
        if ($sections === []) {
            throw new AccessDeniedHttpException((string) __('webx-admin::site-bar.forbidden'));
        }

        return ApiResponse::data([
            'url' => $path,
            'locale' => $locale,
            'entity' => $type !== null && $link !== null && $entity instanceof Model && $this->bar->mayOpen($user, $entity, $link)
                ? $this->bar->describe($type, $entity, $link, $user, $locale)
                : null,
            'sections' => $sections,
        ]);
    }

    /**
     * `POST /api/cms/site-bar/publish` — «Publish» in the head of the bar (§9): the record's
     * draft on the site, the way the section's own button puts it there ({@see Publishing}).
     *
     * The revision the bar held, when it sends one: an edit made since — a colleague's in the
     * panel, an agent's — is not published under a click that never saw it. Answered 409 with
     * who made it, as the editor in the panel is.
     */
    public function publish(Request $request): JsonResponse
    {
        [$entity, $publishing] = $this->publishable($request);

        $sent = $request->input('revision');
        $now = $this->container->make(EditedRecords::class)->revisionOf($entity);

        if (is_string($sent) && $sent !== '' && $now !== null && $sent !== $now) {
            $changed = LastChange::of($entity, $request->user());

            return new JsonResponse([
                'message' => (string) __('webx-admin::editing.stale-publish', [
                    'name' => $changed['author'] ?? __('webx-admin::editing.somebody'),
                ]),
                'revision' => $now,
                'changed' => $changed,
            ], 409);
        }

        $publishing->publish($entity, $this->adminId($request));

        return ApiResponse::data(['published' => true]);
    }

    /** `POST /api/cms/site-bar/discard` — «Discard»: the draft goes, the site's version stays. */
    public function discard(Request $request): JsonResponse
    {
        [$entity, $publishing] = $this->publishable($request);

        $publishing->discard($entity);

        return ApiResponse::data(['discarded' => true]);
    }

    /**
     * The record named in the request, and what publishes it — for an administrator who may.
     * The same right the bar shows the buttons by (`can.publish`): the section's, on a record
     * whose section said how it is published.
     *
     * @return array{0: Model, 1: Publishing}
     */
    private function publishable(Request $request): array
    {
        $request->validate([
            'type' => ['required', 'string', 'max:64'],
            'id' => ['required'],
            'revision' => ['nullable', 'string', 'max:128'],
        ]);

        $id = $request->input('id');
        $found = is_int($id) || is_string($id) ? $this->bar->record((string) $request->input('type'), $id) : null;

        if ($found === null) {
            throw new NotFoundHttpException;
        }

        [$entity, $link] = $found;
        $user = $request->user();

        if (! $user instanceof HasPermissions || ! $user->hasPermission($link->permission)) {
            throw new AccessDeniedHttpException;
        }

        $publishing = $link->publish === null ? null : $this->container->make($link->publish);

        if (! $publishing instanceof Publishing) {
            throw new NotFoundHttpException;
        }

        return [$entity, $publishing];
    }

    private function adminId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    /** The path of an address of this site, without its query; anything else is refused. */
    private function path(string $url): string
    {
        $foreign = ! str_starts_with($url, '/')
            || str_starts_with($url, '//')
            || str_starts_with($url, '/\\')
            || parse_url($url, PHP_URL_SCHEME) !== null
            || parse_url($url, PHP_URL_HOST) !== null;

        if ($foreign) {
            throw ValidationException::withMessages(['url' => (string) __('webx-admin::site-bar.foreign')]);
        }

        return (string) (parse_url($url, PHP_URL_PATH) ?: '/');
    }

    /**
     * The record behind a path: its type, the record, and the language of the address.
     *
     * An alias is followed to the address it stands for — an old link still names the record.
     * A site without the address registry has records without addresses, and so nothing here.
     *
     * @return array{0: string|null, 1: Model|null, 2: string|null}
     */
    private function find(string $path, ?string $locale): array
    {
        if (! class_exists(Resolver::class)) {
            return [null, null, $locale];
        }

        $found = $this->container->make(Resolver::class)->lookup($path, $locale);
        $route = $found?->route;

        if ($route instanceof Route && $route->isAlias()) {
            $route = $route->target;
        }

        if (! $route instanceof Route) {
            return [null, null, $locale];
        }

        $type = $this->container->make(RouteTypes::class)->find($route->entity_type);

        if ($type === null) {
            return [null, null, $route->locale];
        }

        /** @var Model $model */
        $model = $this->container->make($type->model);
        $entity = $model->newQuery()->find($route->entity_id);

        return [$type->type, $entity instanceof Model ? $entity : null, $route->locale];
    }
}
