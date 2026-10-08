<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/**
 * A public folder answers with a list of its files — `/storage/`, `/build/`: every upload, draft
 * attachment and old export is one click away for anyone, search engines included.
 */
final class DirectoryListing extends Check
{
    protected const ID = 'host.directory_listing';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::WARNING;

    /** What Apache, nginx, LiteSpeed and IIS put at the top of a listing. */
    private const LISTING = '~<title>\s*(Index of /|Directory listing for /)|<h1>\s*Index of /|\[To Parent Directory\]~i';

    public function run(AuditContext $context): iterable
    {
        foreach ($context->probes->prefixed('listing:') as $key => $answer) {
            if ($answer->status === 200 && preg_match(self::LISTING, $answer->body) === 1) {
                $path = substr($key, strlen('listing:'));

                yield $this->found('directory-listing', ['path' => $path], $answer->url, key: $path);
            }
        }
    }
}
