<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A page that answers 200 and says in its title or H1 that it was not found — a "soft 404" a
 * search engine keeps as a page. `host.soft_404` asks a random address; this one reads every
 * page the crawl reached, so a product page that lost its product is caught too.
 */
final class SoftNotFound extends PageCheck
{
    protected const ID = 'content.soft_404';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::WARNING;

    /** "Not found" in the languages the panel ships. */
    private const SAYS = '~\b404\b|not found|page not found|does not exist|nicht gefunden|nie znaleziono|nie istnieje|introuvable|n[\'’]existe pas|no encontrad[oa]|no existe|non trovat[oa]|non esiste|não encontrad[oa]|não existe|bulunamadı|не найден|не существует|не знайдено|не існує~iu';

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        foreach ([$page->title, $page->h1[0] ?? null] as $text) {
            if (is_string($text) && $text !== '' && preg_match(self::SAYS, $text) === 1) {
                yield $this->on($page, 'content-soft-404', ['value' => mb_substr($text, 0, 120)]);

                return;
            }
        }
    }
}
