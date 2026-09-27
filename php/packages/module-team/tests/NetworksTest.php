<?php

declare(strict_types=1);

namespace WebxUi\Team\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Team\Models\Member;

/**
 * The networks of a social link come from the config, laid over the screen by a patch (§5.4): the
 * select inside the repeater gets them as its options, and the server's check of a `wx-select`
 * reads the same options — so a network the site adds in its config is one it accepts, with no
 * other change. This site has added one.
 */
final class NetworksTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-team.networks', [
            ...(array) $app['config']->get('webx-team.networks'),
            'vk' => 'VK',
        ]);
    }

    #[Test]
    public function the_select_inside_the_repeater_offers_the_networks_of_the_config(): void
    {
        $root = (array) $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/screens/team.form')->assertOk()->json('data.root');

        $select = $this->node($root, 'social-network');

        $this->assertIsArray($select);
        $this->assertSame(
            ['facebook', 'instagram', 'linkedin', 'x', 'telegram', 'youtube', 'tiktok', 'vk'],
            array_column((array) ($select['props']['options'] ?? []), 'value'),
        );
        $this->assertSame('VK', $select['props']['options'][7]['label'] ?? null, 'a brand, not a translation');
        $this->assertSame('Choose a network', $select['props']['placeholder'] ?? null, 'the patch keeps the props it did not name');
    }

    #[Test]
    public function the_screen_itself_refuses_a_network_the_config_does_not_have(): void
    {
        $values = $this->app->make(ScreenValues::class);

        $stored = $values->validate(Member::SCREEN, ['socials' => [['network' => 'vk', 'url' => 'https://vk.com/anna']]]);
        $this->assertSame([['network' => 'vk', 'url' => 'https://vk.com/anna']], $stored['socials'] ?? null);

        $this->expectException(ValidationException::class);
        $values->validate(Member::SCREEN, ['socials' => [['network' => 'myspace', 'url' => 'https://myspace.com/anna']]]);
    }

    #[Test]
    public function a_network_the_site_added_is_saved_and_printed(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
            'values' => [
                'name' => ['en' => 'Anna'],
                'published' => true,
                'socials' => [['network' => 'vk', 'url' => 'https://vk.com/anna']],
            ],
        ])->assertCreated()->assertJsonPath('data.values.socials', [['network' => 'vk', 'url' => 'https://vk.com/anna']]);

        $this->assertSame([['network' => 'vk', 'label' => 'VK', 'url' => 'https://vk.com/anna']], team()->first()['socials'] ?? null);
    }

    /**
     * @param  array<array-key, mixed>  $nodes
     * @return array<string, mixed>|null
     */
    private function node(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (($node['id'] ?? null) === $id) {
                return $node;
            }

            $found = $this->node((array) ($node['children'] ?? []), $id);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
