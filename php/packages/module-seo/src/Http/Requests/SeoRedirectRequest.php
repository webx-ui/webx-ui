<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Rendering\UrlNormaliser;
use WebxUi\Seo\Rules\ValidRegex;

/**
 * An address that has moved, and where to.
 *
 * A loop is not refused here. `/a` to `/a` obviously is one, but a mask redirect is a loop only
 * for some of the addresses it covers, and the answer to that is to skip it when it happens
 * rather than to make the rule unwritable — so the middleware does the skipping and the panel
 * says which rows look wrong.
 */
final class SeoRedirectRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'match_type' => ['required', Rule::in(UrlMatcher::types())],
            'pattern' => ['required', 'string', 'max:2048'],
            // May name what the pattern captured: `/catalog/*` to `/shop/$1`. An absolute
            // address is allowed too — a redirect to another host is a redirect.
            'target' => ['required', 'string', 'max:2048'],
            'status' => ['nullable', Rule::in([301, 302])],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($this->input('match_type') === UrlMatcher::REGEX) {
            $rules['pattern'][] = new ValidRegex;
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $pattern = (string) $this->string('pattern');
        $target = trim((string) $this->string('target'));

        return [
            'match_type' => (string) $this->string('match_type'),
            'pattern' => $this->input('match_type') === UrlMatcher::REGEX ? $pattern : UrlNormaliser::normalise($pattern),
            // Left as written when it points somewhere else entirely, normalised when it is a
            // path on this site.
            'target' => str_contains($target, '://') ? $target : UrlNormaliser::normalise($target),
            'status' => (int) $this->input('status', 301),
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
