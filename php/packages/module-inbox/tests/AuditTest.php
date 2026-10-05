<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\AuditServiceProvider;
use WebxUi\Audit\Checks\AuditChecks;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Hosts\HostClassifier;
use WebxUi\Audit\Probes\ProbeSet;
use WebxUi\Audit\Probes\SiteClient;
use WebxUi\Audit\Runs\AuditRun;

/**
 * What the inbox brings to the site audit when the audit is installed: the forms that would
 * write to nobody.
 */
final class AuditTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), AuditServiceProvider::class];
    }

    #[Test]
    public function a_switched_on_form_that_writes_to_nobody_is_a_warning_with_a_way_to_its_settings(): void
    {
        $admin = $this->editor();
        $retired = $this->editor();
        $retired->update(['is_active' => false]);

        $silent = $this->form('contact');
        $gone = $this->form('careers', [], ['recipients' => [['admin_id' => $retired->getKey()], ['admin_id' => 999]]]);
        $this->form('callback', [], ['recipients' => [['admin_id' => $admin->getKey()]]]);
        $this->form('typed', [], ['recipients' => [['email' => 'sales@example.test']]]);
        // Off: nobody can send it, so nobody missing its letters is not news.
        $this->form('old')->update(['is_enabled' => false]);

        $findings = $this->findings();

        $this->assertCount(2, $findings);
        $this->assertSame(['warning', 'warning'], array_map(static fn (Finding $finding): string => $finding->severity, $findings));
        $this->assertSame([(string) $silent->getKey(), (string) $gone->getKey()], array_map(static fn (Finding $finding): string => $finding->key, $findings));

        // The way to the fix: the form's editor, opened on the tab where recipients are chosen.
        $this->assertSame([
            '/inbox/forms/'.$silent->getKey().'?tab=notifications',
            '/inbox/forms/'.$gone->getKey().'?tab=notifications',
        ], array_map(static fn (Finding $finding): mixed => $finding->details['table']['rows'][0]['edit'] ?? null, $findings));
    }

    #[Test]
    public function the_check_speaks_in_its_own_words(): void
    {
        foreach (['title', 'found', 'why', 'fix'] as $text) {
            $key = 'webx-inbox::checks.inbox.no_recipients.'.$text;
            $this->assertNotSame($key, __($key), $text);
        }

        $this->assertNotSame('webx-inbox::audit.no-recipients', __('webx-inbox::audit.no-recipients'));
    }

    /**
     * The check by itself rather than through `webx:audit:run`: the whole run wants the
     * settings and the crawler, and this check reads nothing but the forms.
     *
     * @return list<Finding>
     */
    private function findings(): array
    {
        $check = $this->app->make(AuditChecks::class)->get('inbox.no_recipients');
        $this->assertNotNull($check, 'Registered by the inbox when the audit is installed.');

        $context = new AuditContext(new AuditRun, new HostClassifier(['example.test']), $this->app->make(SiteClient::class), new ProbeSet, $this->app->make('config'));

        return array_values(iterator_to_array($check->run($context), false));
    }
}
