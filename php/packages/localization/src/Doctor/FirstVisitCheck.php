<?php

declare(strict_types=1);

namespace WebxUi\Localization\Doctor;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Localization\Contracts\PageInLanguage;
use WebxUi\Localization\Locales;
use WebxUi\Localization\NoPageInLanguage;

/**
 * The language of the first visit switched on where it cannot work (ROUTING §18): it stays
 * silent then rather than send anybody to a wrong page, and this is the only place that says so.
 * Nothing at all while it is off — which is every site that never turned it on.
 */
final class FirstVisitCheck implements Check
{
    private const string SUBJECT = 'First visit';

    public function __construct(
        private readonly Config $config,
        private readonly Locales $locales,
        private readonly PageInLanguage $pages,
    ) {}

    public function run(): array
    {
        $wanted = $this->config->get('webx-localization.first_visit.locale');

        if (! is_string($wanted) || $wanted === '') {
            return [];
        }

        $found = [];

        if ((string) $this->config->get('webx-localization.strategy', 'prefix') !== 'prefix') {
            $found[] = Diagnosis::warn(self::SUBJECT, 'WEBX_LOCALE_FIRST_VISIT is set, but the strategy is not `prefix`: every language has one address and nobody is sent anywhere.');
        }

        if (! $this->locales->has($wanted)) {
            $found[] = Diagnosis::warn(self::SUBJECT, "WEBX_LOCALE_FIRST_VISIT={$wanted} is not the code of one of the site's active languages: nobody is sent anywhere. Name one of them.");
        }

        if ($this->pages instanceof NoPageInLanguage) {
            $found[] = Diagnosis::warn(self::SUBJECT, 'WEBX_LOCALE_FIRST_VISIT is set, but nothing knows the addresses of a page in another language: install webx-ui/widgets with webx-ui/routing.');
        }

        return $found;
    }
}
