<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Config;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/**
 * `APP_URL` is not the scheme and host the site really answers on: every absolute address the
 * site prints — the sitemap, canonical, letters, file links — then points somewhere else.
 */
final class AppUrl extends Check
{
    protected const ID = 'config.app_url';

    protected const GROUP = 'config';

    protected const SEVERITY = Severity::ERROR;

    protected const NEEDS = ['config', 'probes'];

    public function run(AuditContext $context): iterable
    {
        $appUrl = rtrim((string) $context->config('app.url', ''), '/');

        if (self::origin($appUrl) !== self::origin($context->base())) {
            yield $this->found('app-url-differs', ['app_url' => $appUrl, 'base' => $context->base()]);

            return;
        }

        $home = $context->probes->get('home');

        if ($home === null) {
            return;
        }

        if ($home->status === null) {
            yield $this->found('unreachable', ['url' => $home->url, 'error' => $home->error]);

            return;
        }

        $location = $home->location();

        if ($home->redirect() && $location !== null && self::origin($location) !== self::origin($appUrl)) {
            yield $this->found('app-url-redirects', ['app_url' => $appUrl, 'location' => $location]);
        }
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url);

        return strtolower(($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : ''));
    }
}
