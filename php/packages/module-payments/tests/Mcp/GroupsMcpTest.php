<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Mcp;

use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Registry\BoundTool;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Mcp\Tests\Fixtures\TokenUser;
use WebxUi\Payments\Mcp\PaymentsResources;
use WebxUi\Payments\MethodGroup;
use WebxUi\Payments\MethodGroups;
use WebxUi\Payments\Methods;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\PaymentGroup;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Tests\Fakes\MarkedType;

/**
 * The groups for an agent (groups spec §8): the panel's form behind every tool, so a group is
 * edited over the config and never created or removed; a dry run answers what the write would and
 * leaves nothing, in the tables or the cache; what is refused is refused in words.
 */
final class GroupsMcpTest extends McpCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(MethodTypes::class)->register(new MarkedType);
    }

    /** @param  list<string>  $marks */
    private function marked(string $key, array $marks, bool $enabled = true): PaymentMethod
    {
        return PaymentMethod::query()->create([
            'key' => $key,
            'type' => MarkedType::KEY,
            'title' => ['en' => ucfirst($key)],
            'enabled' => $enabled,
            'position' => 50,
            'delivery_rule' => ['mode' => 'any'],
            'settings' => ['marks' => $marks],
        ]);
    }

    private function method(string $key): PaymentMethod
    {
        return PaymentMethod::query()->where('key', $key)->firstOrFail();
    }

    /** @return list<string> the groups as the site reads them, through its cache */
    private function siteOrder(): array
    {
        return array_map(static fn (MethodGroup $group): string => $group->key, $this->app->make(MethodGroups::class)->all());
    }

    /** @return array<string, array<string, mixed>> */
    private function listed(): array
    {
        return array_column($this->use('payments_groups_list')['groups'], null, 'key');
    }

    private function asToken(string $tool, string $scope): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs(new TokenUser([$scope]))->tool($bound, []);
    }

    #[Test]
    public function the_list_is_the_groups_in_their_order_with_their_methods_and_marks(): void
    {
        $this->marked('wallet', ['visa', 'google-pay']);
        $this->marked('asleep', ['mastercard'], enabled: false);

        $groups = $this->use('payments_groups_list')['groups'];

        $this->assertSame(['on_receipt', 'online', 'transfer'], array_column($groups, 'key'));

        $online = $groups[1];
        $this->assertSame(['Pay online', 'Pay online', 'card'], [$online['title'], $online['config_title'], $online['pictogram']]);
        $this->assertContains('gateway-a-card', $online['methods']);
        $this->assertSame(['bank-transfer'], $groups[2]['methods'], 'A method switched off is listed: an agent has to find it.');

        $byKey = array_column($groups, null, 'key');
        $own = $this->app->make(MethodGroups::class)->ofType(new MarkedType);
        $this->assertContains('asleep', $byKey[$own]['methods']);
        $this->assertSame(['visa', 'google-pay'], $byKey[$own]['marks'], 'The marks are what the enabled methods show.');
    }

    #[Test]
    public function a_site_without_groups_lists_none_and_has_none_to_edit(): void
    {
        config(['webx-payments.groups' => []]);

        $this->assertSame([], $this->use('payments_groups_list')['groups']);
        $this->refused('payments_groups_save', ['key' => 'online', 'values' => ['pictogram' => 'cash']], 'Unknown group: online');
        $this->refused('payments_groups_reorder', ['groups' => ['online']], 'Unknown group: online');
    }

    #[Test]
    public function a_save_answers_as_the_panel_and_a_dry_run_leaves_nothing(): void
    {
        $this->assertSame('Pay online', $this->app->make(MethodGroups::class)->all()[1]->title('en'), 'Warms the cache a rehearsal must not touch.');

        $values = ['titles' => 'Pay right now', 'descriptions' => ['en' => 'Cards and wallets'], 'pictogram' => 'wallet'];
        $dry = $this->use('payments_groups_save', ['key' => 'online', 'values' => $values, 'dry_run' => true]);

        $this->assertTrue($dry['dry_run']);
        $this->assertSame(['Pay right now', 'Cards and wallets', 'wallet'], [$dry['group']['title'], $dry['group']['description'], $dry['group']['pictogram']]);
        $this->assertSame(0, PaymentGroup::query()->count());
        $this->assertSame('Pay online', $this->app->make(MethodGroups::class)->all()[1]->title('en'));

        $saved = $this->use('payments_groups_save', ['key' => 'online', 'values' => $values]);

        $this->assertFalse($saved['dry_run']);
        $this->assertEquals($dry['group'], $saved['group']);
        $this->assertSame('Pay right now', $this->app->make(MethodGroups::class)->all()[1]->title('en'));

        $this->signIn(['payments.view']);
        $panel = array_column((array) $this->getJson($this->api('groups'))->json('data.groups'), null, 'key')['online'];
        $this->assertEquals($panel, $saved['group']);
    }

    #[Test]
    public function a_language_not_named_stays_and_an_empty_one_goes_back_to_the_config(): void
    {
        PaymentGroup::query()->create(['key' => 'online', 'title' => ['en' => 'Pay now', 'ru' => 'Оплатить'], 'pictogram' => 'wallet']);

        $this->use('payments_groups_save', ['key' => 'online', 'values' => ['titles' => ['en' => 'Pay at once']]]);
        $this->assertSame(['en' => 'Pay at once', 'ru' => 'Оплатить'], PaymentGroup::query()->where('key', 'online')->value('title'));

        $this->use('payments_groups_save', ['key' => 'online', 'values' => ['titles' => ['ru' => ' ']]]);
        $this->assertSame(['en' => 'Pay at once'], PaymentGroup::query()->where('key', 'online')->value('title'));

        $back = $this->use('payments_groups_save', ['key' => 'online', 'values' => ['titles' => null, 'pictogram' => null]]);
        $this->assertSame(['Pay online', 'card'], [$back['group']['title'], $back['group']['pictogram']]);
    }

    #[Test]
    public function what_is_not_a_group_or_a_pictogram_is_refused_in_words(): void
    {
        foreach ([[], ['dry_run' => true]] as $dry) {
            $this->refused('payments_groups_save', ['key' => 'crypto', 'values' => ['titles' => 'Crypto'], ...$dry], 'Unknown group: crypto');
            $this->refused('payments_groups_save', ['key' => 'online', 'values' => ['pictogram' => 'rocket'], ...$dry], 'Unknown pictogram: rocket');
            $this->refused('payments_groups_reorder', ['groups' => ['crypto'], ...$dry], 'Unknown group: crypto');
            $this->refused('payments_methods_save', ['key' => 'bank-transfer', 'values' => ['group' => 'crypto'], ...$dry], 'Unknown group: crypto');
            $this->refused('payments_methods_reorder', ['groups' => [['key' => 'crypto', 'methods' => []]], ...$dry], 'Unknown group: crypto');
        }

        $this->refused('payments_groups_save', ['key' => 'online', 'values' => ['key' => 'crypto']], 'key');
        $this->refused('payments_groups_save', ['key' => 'online', 'values' => ['titles' => ['xx' => 'Crypto']]], 'which this site is not published in');
        $this->assertSame(0, PaymentGroup::query()->count());
    }

    #[Test]
    public function no_tool_creates_or_removes_a_group(): void
    {
        $names = array_map(static fn (BoundTool $tool): string => $tool->fullName(), $this->app->make(ToolRegistry::class)->tools());

        $this->assertSame(
            ['payments_groups_list', 'payments_groups_save', 'payments_groups_reorder'],
            array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'payments_groups'))),
        );
    }

    #[Test]
    public function the_groups_are_put_in_order_and_the_methods_stay_in_theirs(): void
    {
        $methods = fn (): array => array_map(static fn (array $group): array => $group['methods'], $this->listed());
        $before = $methods();
        $this->siteOrder();

        $dry = $this->use('payments_groups_reorder', ['groups' => ['transfer'], 'dry_run' => true]);
        $this->assertSame(['transfer', 'on_receipt', 'online'], array_column($dry['groups'], 'key'));
        $this->assertSame(['on_receipt', 'online', 'transfer'], $this->siteOrder());
        $this->assertSame(0, PaymentGroup::query()->count());

        $this->use('payments_groups_reorder', ['groups' => ['transfer']]);
        $this->assertSame(['transfer', 'on_receipt', 'online'], $this->siteOrder(), 'Those left out follow, and the cache follows the write.');
        $this->assertEquals($before, $methods());
    }

    #[Test]
    public function a_method_is_put_into_a_group_and_its_own_group_stores_nothing(): void
    {
        $dry = $this->use('payments_methods_save', ['key' => 'gateway-a-card', 'values' => ['group' => 'transfer'], 'dry_run' => true]);
        $this->assertSame(['transfer', 'transfer'], [$dry['method']['group'], $dry['method']['group_key']]);
        $this->assertNull($this->method('gateway-a-card')->group_key);

        $moved = $this->use('payments_methods_save', ['key' => 'gateway-a-card', 'values' => ['group' => 'transfer']]);
        $this->assertSame('transfer', $moved['method']['group']);
        $this->assertSame('transfer', $this->method('gateway-a-card')->group_key);
        $this->assertSame(
            (int) PaymentMethod::query()->max('position'),
            $this->method('gateway-a-card')->position,
            'As in the panel: it goes to the end of the group.',
        );

        $this->use('payments_methods_save', ['key' => 'gateway-a-card', 'values' => ['group' => 'online']]);
        $this->assertNull($this->method('gateway-a-card')->group_key);

        $this->use('payments_methods_save', ['key' => 'gateway-a-card', 'values' => ['group' => 'transfer']]);
        $back = $this->use('payments_methods_save', ['key' => 'gateway-a-card', 'values' => ['group' => null]]);
        $this->assertSame(['online', null], [$back['method']['group'], $back['method']['group_key']]);
    }

    #[Test]
    public function the_list_of_methods_says_the_group_the_marks_and_who_is_alike(): void
    {
        $this->marked('first', ['visa']);
        $this->marked('second', ['visa']);

        $methods = array_column($this->use('payments_methods_list')['methods'], null, 'key');

        $this->assertSame(['visa'], $methods['first']['marks']);
        $this->assertNull($methods['first']['group_key']);
        $this->assertSame($methods['second']['group'], $methods['first']['group']);
        $this->assertSame('second', $methods['first']['similar_to'][0]['key']);
    }

    #[Test]
    public function a_save_warns_of_a_method_like_it_and_a_dry_run_says_so_too(): void
    {
        $this->marked('first', ['visa']);
        $this->marked('second', ['visa'], enabled: false);

        $dry = $this->use('payments_methods_save', ['key' => 'second', 'values' => ['enabled' => true], 'dry_run' => true]);
        $this->assertStringContainsString('Visa', $dry['warnings'][0] ?? '');
        $this->assertFalse($this->method('second')->enabled);

        $saved = $this->use('payments_methods_save', ['key' => 'second', 'values' => ['enabled' => true]]);
        $this->assertSame($dry['warnings'], $saved['warnings']);
        $this->assertTrue($this->method('second')->enabled, 'A warning, never a refusal.');

        $this->assertSame([], $this->use('payments_methods_save', ['key' => 'second', 'values' => ['enabled' => false]])['warnings']);
    }

    #[Test]
    public function the_methods_are_put_in_order_inside_groups_and_moved_as_in_the_panel(): void
    {
        $arguments = ['groups' => [['key' => 'transfer', 'methods' => ['gateway-a-card', 'bank-transfer']]]];

        $dry = $this->use('payments_methods_reorder', [...$arguments, 'dry_run' => true]);
        $this->assertSame(['gateway-a-card', 'bank-transfer'], array_column($dry['groups'], 'methods', 'key')['transfer']);
        $this->assertNull($this->method('gateway-a-card')->group_key);

        $online = $this->listed()['online']['methods'];
        $enabled = $this->app->make(Methods::class);
        $enabled->enabled();

        $this->use('payments_methods_reorder', $arguments);

        $groups = $this->listed();
        $this->assertSame(['gateway-a-card', 'bank-transfer'], $groups['transfer']['methods']);
        $this->assertSame(array_values(array_diff($online, ['gateway-a-card'])), $groups['online']['methods'], 'Those left out stay where they stand.');
        $this->assertSame('transfer', $this->method('gateway-a-card')->group_key);
        $this->assertSame('transfer', array_column(array_map(static fn ($method): array => ['key' => $method->key, 'group' => $method->groupKey], $enabled->enabled()), 'group', 'key')['gateway-a-card']);

        // Back in the group of its type, nothing is stored of the move.
        $this->use('payments_methods_reorder', ['groups' => [['key' => 'online', 'methods' => ['gateway-a-card']]]]);
        $this->assertNull($this->method('gateway-a-card')->group_key);
    }

    #[Test]
    public function a_wrong_order_inside_groups_is_refused_and_writes_nothing(): void
    {
        $before = PaymentMethod::query()->orderBy('id')->pluck('position', 'key')->all();

        $this->refused('payments_methods_reorder', ['groups' => [['key' => 'online', 'methods' => ['nothing']]]], 'No such method: nothing');
        $this->refused('payments_methods_reorder', ['groups' => [
            ['key' => 'online', 'methods' => ['gateway-a-card']],
            ['key' => 'transfer', 'methods' => ['gateway-a-card']],
        ]], 'Named twice: gateway-a-card');
        $this->refused('payments_methods_reorder', ['groups' => [['key' => 'online', 'methods' => []], ['key' => 'online', 'methods' => []]]], 'Named twice: online');
        $this->refused('payments_methods_reorder', ['groups' => ['online']], '`groups` is a list of');
        $this->refused('payments_methods_reorder', ['groups' => []], '`groups` is a list of');
        $this->refused('payments_methods_reorder', ['methods' => ['bank-transfer'], 'groups' => [['key' => 'online', 'methods' => []]]], 'not both');

        $this->assertSame($before, PaymentMethod::query()->orderBy('id')->pluck('position', 'key')->all());
    }

    #[Test]
    public function the_trial_says_the_group_and_leaves_apple_pay_to_the_buyers_browser(): void
    {
        $this->assertNull($this->use('payments_available', ['amount' => 4999, 'currency' => 'EUR'])['apple_pay']);

        $this->marked('apple', ['apple-pay']);
        $this->marked('card-and-apple', ['visa', 'apple-pay']);

        $answer = $this->use('payments_available', ['amount' => 4999, 'currency' => 'EUR']);
        $methods = array_column($answer['methods'], null, 'key');

        $this->assertSame('online', $methods['gateway-a-card']['group']);
        $this->assertTrue($methods['apple']['apple_pay_only']);
        $this->assertTrue($methods['apple']['available']);
        $this->assertFalse($methods['card-and-apple']['apple_pay_only']);
        $this->assertStringContainsString('browser', (string) $answer['apple_pay']);
    }

    #[Test]
    public function the_catalog_names_the_groups_the_pictograms_and_the_marks(): void
    {
        $resource = array_column($this->app->make(PaymentsResources::class)->all(), null, 'uri')['payments://catalog'];
        $catalog = ($resource->handler)();

        $this->assertSame(['on_receipt', 'online', 'transfer'], array_column($catalog['groups'], 'key'));
        $this->assertSame(MethodGroups::PICTOGRAMS, $catalog['pictograms']);
        $this->assertSame('online', array_column($catalog['types'], 'group', 'key')['gateway-a.card']);

        $marks = array_column($catalog['marks'], null, 'key');
        $this->assertSame(['Visa', false], [$marks['visa']['alt'], $marks['visa']['plate']]);
        $this->assertTrue($marks['mono']['plate']);
    }

    #[Test]
    public function reading_is_the_sections_view_and_writing_its_manage(): void
    {
        $this->agent('payments_groups_list', [], ['delivery.view'])->assertHasErrors(['[payments.view]']);
        $this->agent('payments_groups_list', [], ['payments.view'])->assertOk();

        foreach ([
            'payments_groups_save' => ['key' => 'online', 'values' => ['pictogram' => 'cash']],
            'payments_groups_reorder' => ['groups' => ['transfer']],
        ] as $tool => $arguments) {
            $this->agent($tool, $arguments, ['payments.view'])->assertHasErrors(['[payments.manage]']);
            $this->agent($tool, [...$arguments, 'dry_run' => true], ['payments.view'])->assertHasErrors(['[payments.manage]']);
        }

        $this->assertSame(0, PaymentGroup::query()->count());
    }

    #[Test]
    public function a_token_without_the_scope_is_told_which_one_is_missing(): void
    {
        $this->asToken('payments_groups_list', 'orders:read')
            ->assertHasErrors(['does not carry the [payments:read] scope, which [payments_groups_list] needs']);

        foreach (['payments_groups_save', 'payments_groups_reorder'] as $tool) {
            $this->asToken($tool, 'payments:read')->assertHasErrors(["does not carry the [payments:write] scope, which [{$tool}] needs"]);
        }

        $this->assertSame(0, PaymentGroup::query()->count());
    }
}
