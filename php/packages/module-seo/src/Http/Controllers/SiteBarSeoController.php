<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Controllers;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\SiteBar\SiteBar;
use WebxUi\Routing\RouteTypes;
use WebxUi\Seo\Fields;
use WebxUi\Seo\Panel\SiteBarSeo;
use WebxUi\Seo\Screens\SeoFieldType;

/**
 * The two addresses the SEO tab of the panel on the site needs (WEBX_UI_SITE_BAR.md, §7).
 *
 * The rule half writes through `seo/urls`, which already exists and already asks for
 * `seo.manage`. What did not exist is a way to write one record's card outside of its form —
 * every section saved `saveSeo()` from its own controller — so that is the second address here,
 * behind the right of the record's section rather than anything about SEO: the card in the
 * form is saved with that right too, and the bar must not be a door the form is not.
 */
final class SiteBarSeoController
{
    public function __construct(
        private readonly SiteBarSeo $tab,
        private readonly SiteBar $bar,
        private readonly RouteTypes $types,
    ) {}

    /** `GET /api/cms/seo/site-bar?url=/pricing&locale=en` — what the tab shows. */
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'string', 'max:2048', 'starts_with:/', 'not_regex:~^/[/\\\\]~'],
            'locale' => ['nullable', 'string', 'max:16'],
        ]);

        $locale = $request->query('locale');
        $report = $this->tab->report(
            (string) parse_url((string) $request->query('url'), PHP_URL_PATH),
            is_string($locale) && $locale !== '' ? $locale : null,
            $this->user($request),
        );

        if ($report === null) {
            throw new AccessDeniedHttpException((string) __('webx-seo::site-bar.forbidden'));
        }

        return ApiResponse::data($report);
    }

    /**
     * `PUT /api/cms/seo/entity/{type}/{id}` — the named fields of one record's card.
     *
     * The keys sent are laid over the card the record has, the way an agent's partial edit is
     * (`SeoFieldType::merge()`): `{"title": {"en": "…"}}` is the English title and nothing else,
     * and `null` empties. The bar edits four fields in one language, and replacing the whole card
     * with them would quietly drop the other languages and the share picture.
     *
     * Saved at once, never into a draft: `HasSeo` stays out of drafts on purpose (its docblock),
     * and the bar does not change that.
     */
    public function entity(Request $request, SeoFieldType $card, ValidationFactory $validation, string $type, string $id): JsonResponse
    {
        $request->validate(['seo' => ['required', 'array']]);

        $entity = $this->record($type, $id);
        $link = $this->bar->link($type);
        $user = $this->user($request);

        if (! SiteBarSeo::mayEditEntity($user, $entity, $link)) {
            throw new AccessDeniedHttpException((string) __('webx-seo::site-bar.forbidden'));
        }

        $sent = array_intersect_key((array) $request->input('seo'), array_flip([...Fields::TRANSLATED, ...Fields::PLAIN]));
        /** @var array<string, mixed> $current */
        $current = method_exists($entity, 'seoValue') ? $entity->seoValue() : [];
        $merged = $card->merge($current, $sent, []);

        $validator = $validation->make(['seo' => $merged], ['seo' => $card->rules([])]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $stored = $card->store($merged, []);

        if (method_exists($entity, 'saveSeo')) {
            $entity->saveSeo($stored === [] ? null : $stored);
        }

        return ApiResponse::data(['seo' => method_exists($entity, 'seoValue') ? $entity->seoValue() : []]);
    }

    /** The record by the type its addresses carry — the bar's own vocabulary, not a class name. */
    private function record(string $type, string $id): Model
    {
        $found = $this->types->find($type);

        if ($found === null || ! is_subclass_of($found->model, Model::class)) {
            throw new NotFoundHttpException;
        }

        $entity = $found->model::query()->find($id);

        if (! $entity instanceof Model || ! SiteBarSeo::hasCard($entity)) {
            throw new NotFoundHttpException;
        }

        return $entity;
    }

    private function user(Request $request): HasPermissions
    {
        $user = $request->user();

        if (! $user instanceof HasPermissions) {
            throw new AccessDeniedHttpException((string) __('webx-seo::site-bar.forbidden'));
        }

        return $user;
    }
}
