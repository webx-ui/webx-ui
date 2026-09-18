<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Support;

use WebxUi\Inbox\Models\Form;

/**
 * The enabled form a request is about, looked up once.
 *
 * Two things ask for it and both happen before the controller: the rate limiter, which has to
 * know how many submissions a minute this particular form allows, and the intake itself. A
 * `scoped` binding rather than a `singleton`, so that under Octane the second request of a
 * worker does not get the first request's form.
 */
final class Forms
{
    /** @var array<string, Form|null> */
    private array $bySlug = [];

    public function enabled(string $slug): ?Form
    {
        if (! array_key_exists($slug, $this->bySlug)) {
            $this->bySlug[$slug] = Form::query()
                ->enabled()
                ->where('slug', $slug)
                ->with('liveFields')
                ->first();
        }

        return $this->bySlug[$slug];
    }
}
