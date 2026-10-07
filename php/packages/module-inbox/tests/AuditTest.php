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
use WebxUi\Inbox\Models\Status;

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
    public function a_form_asking_for_a_captcha_without_keys_is_an_error_with_a_way_to_its_antispam(): void
    {
        config()->set('webx-inbox.captcha.recaptcha.key', 'site-key');
        config()->set('webx-inbox.captcha.recaptcha.secret', null);
        config()->set('webx-inbox.captcha.turnstile.key', 'site-key');
        config()->set('webx-inbox.captcha.turnstile.secret', 'secret');

        $broken = $this->form('contact', [], ['antispam.captcha' => 'recaptcha']);
        $this->form('callback', [], ['antispam.captcha' => 'turnstile']);
        $this->form('plain');
        $this->form('old', [], ['antispam.captcha' => 'recaptcha'])->update(['is_enabled' => false]);

        $findings = $this->findings('inbox.captcha_keys');

        $this->assertCount(1, $findings);
        $this->assertSame('error', $findings[0]->severity);
        $this->assertSame((string) $broken->getKey(), $findings[0]->key);
        $this->assertSame('/inbox/forms/'.$broken->getKey().'?tab=antispam', $findings[0]->details['table']['rows'][0]['edit'] ?? null);
        // What is missing, named the way the .env spells it.
        $this->assertSame('WEBX_INBOX_RECAPTCHA_SECRET', $findings[0]->details['table']['rows'][0]['value'] ?? null);

        foreach (['title', 'found', 'why', 'fix'] as $text) {
            $key = 'webx-inbox::checks.inbox.captcha_keys.'.$text;
            $this->assertNotSame($key, __($key), $text);
        }
    }

    #[Test]
    public function a_site_without_captcha_keys_is_not_told_its_forms_have_no_captcha(): void
    {
        // The free layers are the default protection; most sites never set a captcha up.
        $this->form('contact');

        $this->assertSame([], $this->findings('inbox.captcha_unused'));
    }

    #[Test]
    public function a_site_with_keys_hears_of_the_forms_left_without_a_captcha_as_a_notice(): void
    {
        config()->set('webx-inbox.captcha.turnstile.key', 'site-key');
        config()->set('webx-inbox.captcha.turnstile.secret', 'secret');

        $bare = $this->form('contact');
        $this->form('callback', [], ['antispam.captcha' => 'turnstile']);
        $this->form('old')->update(['is_enabled' => false]);

        $findings = $this->findings('inbox.captcha_unused');

        $this->assertCount(1, $findings);
        $this->assertSame('notice', $findings[0]->severity);
        $this->assertSame((string) $bare->getKey(), $findings[0]->key);
        $this->assertSame('Turnstile', $findings[0]->details['table']['rows'][0]['value'] ?? null);
    }

    #[Test]
    public function a_form_without_a_captcha_that_draws_spam_is_a_warning(): void
    {
        $spam = Status::spam();
        $this->assertNotNull($spam);

        $found = $this->form('contact');
        $quiet = $this->form('callback');
        $guarded = $this->form('careers', [], ['antispam.captcha' => 'recaptcha']);

        // Two marked as spam, and one the honeypot trapped: three, the default minimum.
        $this->submission($found, ['status_id' => $spam->getKey()]);
        $this->submission($found, ['status_id' => $spam->getKey()]);
        $this->postJson($this->intake('contact'), ['fields' => ['name' => 'Bot'], 'webx_hp' => 'x'])->assertOk();

        // Below the minimum: one test of the honeypot is not a finding.
        $this->submission($quiet, ['status_id' => $spam->getKey()]);

        // Long ago is not now.
        $this->submission($quiet, ['status_id' => $spam->getKey(), 'created_at' => now()->subDays(60)]);
        $this->submission($quiet, ['status_id' => $spam->getKey(), 'created_at' => now()->subDays(60)]);

        // A form with a captcha is past what this check would advise.
        foreach (range(1, 5) as $ignored) {
            $this->submission($guarded, ['status_id' => $spam->getKey()]);
        }

        $findings = $this->findings('inbox.spam_without_captcha');

        $this->assertCount(1, $findings);
        $this->assertSame('warning', $findings[0]->severity);
        $this->assertSame((string) $found->getKey(), $findings[0]->key);
        $this->assertSame('2 + 1', $findings[0]->details['table']['rows'][0]['value'] ?? null);

        foreach (['captcha_unused', 'spam_without_captcha'] as $check) {
            foreach (['title', 'found', 'why', 'fix'] as $text) {
                $key = 'webx-inbox::checks.inbox.'.$check.'.'.$text;
                $this->assertNotSame($key, __($key), $key);
            }
        }
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
    private function findings(string $id = 'inbox.no_recipients'): array
    {
        $check = $this->app->make(AuditChecks::class)->get($id);
        $this->assertNotNull($check, 'Registered by the inbox when the audit is installed.');

        $context = new AuditContext(new AuditRun, new HostClassifier(['example.test']), $this->app->make(SiteClient::class), new ProbeSet, $this->app->make('config'));

        return array_values(iterator_to_array($check->run($context), false));
    }
}
