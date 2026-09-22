<?php

declare(strict_types=1);

namespace WebxUi\Admin\Contracts;

use WebxUi\Admin\Demo\DemoLedger;

/**
 * A module with something to show on a site nobody has written anything into yet.
 *
 * Optional, the way {@see HasPermissions} is: a module implements it when it has content worth
 * seeding, and `webx:demo` walks past the ones that do not rather than asking every module to
 * carry an empty method.
 */
interface ProvidesDemo
{
    /**
     * The modules this one has nothing to seed without — blog without media, products without
     * categories. Ids, as {@see Module::id()} spells them.
     *
     * They are the order as well as the condition: a module is seeded after everything it
     * names, whatever the navigation order says, because an article's cover has to be in the
     * library before the article can point at it.
     *
     * @return list<string>
     */
    public function requires(): array;

    /**
     * Create the content, and tell the ledger about every piece of it.
     *
     * What is not recorded is not removed: `webx:demo --remove` plays the journal backwards and
     * knows nothing else about what a module did.
     */
    public function seed(DemoLedger $ledger): void;
}
