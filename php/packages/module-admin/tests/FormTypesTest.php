<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenValues;

/**
 * The form controls of the core beyond the first nine, each through the path a screen saves by:
 * its rules, then its `store()`.
 */
final class FormTypesTest extends TestCase
{
    private const TREE = [
        ['id' => 'services', 'type' => 'wx-checkbox-group', 'name' => 'services', 'props' => [
            'options' => [['label' => 'Design', 'value' => 'design'], ['label' => 'Code', 'value' => 'code'], 'hosting'],
            'min' => 1,
            'max' => 2,
        ]],
        ['id' => 'align', 'type' => 'wx-segmented', 'name' => 'align', 'props' => ['options' => [['value' => 'left'], ['value' => 'right']]]],
        ['id' => 'volume', 'type' => 'wx-slider', 'name' => 'volume', 'props' => ['max' => 10]],
        ['id' => 'price', 'type' => 'wx-slider', 'name' => 'price', 'props' => ['range' => true, 'min' => 0, 'max' => 1000]],
        ['id' => 'stars', 'type' => 'wx-rate', 'name' => 'stars'],
        ['id' => 'halves', 'type' => 'wx-rate', 'name' => 'halves', 'props' => ['allowHalf' => true, 'max' => 10]],
        ['id' => 'opens', 'type' => 'wx-time-picker', 'name' => 'opens'],
        ['id' => 'starts', 'type' => 'wx-date-time-picker', 'name' => 'starts'],
        ['id' => 'season', 'type' => 'wx-date-range-picker', 'name' => 'season'],
        ['id' => 'tags', 'type' => 'wx-tags-input', 'name' => 'tags', 'props' => ['max' => 3]],
        ['id' => 'mood', 'type' => 'wx-tags-input', 'name' => 'mood', 'props' => ['allowCreate' => false, 'suggestions' => ['calm', 'loud']]],
        ['id' => 'city', 'type' => 'wx-autocomplete', 'name' => 'city', 'props' => ['options' => [['value' => 'Kyiv']]]],
        ['id' => 'icon', 'type' => 'wx-icon-picker', 'name' => 'icon'],
        ['id' => 'code', 'type' => 'wx-code-editor', 'name' => 'code', 'props' => ['language' => 'json']],
        ['id' => 'section', 'type' => 'wx-cascader', 'name' => 'section', 'props' => ['options' => [
            ['value' => 'content', 'label' => 'Content', 'children' => [
                ['value' => 'news', 'label' => 'News'],
                ['value' => 'blog', 'label' => 'Blog'],
            ]],
            ['value' => 'shop', 'label' => 'Shop'],
        ]]],
        ['id' => 'leaf', 'type' => 'wx-cascader', 'name' => 'leaf', 'props' => ['emitPath' => false, 'options' => [
            ['value' => 'content', 'label' => 'Content', 'children' => [['value' => 'news', 'label' => 'News']]],
        ]]],
        ['id' => 'part', 'type' => 'wx-tree-select', 'name' => 'part', 'props' => ['nodes' => [
            ['id' => 1, 'label' => 'Engine', 'children' => [['id' => 2, 'label' => 'Pistons']]],
        ]]],
        ['id' => 'parts', 'type' => 'wx-tree-select', 'name' => 'parts', 'props' => ['multiple' => true, 'nodeKey' => 'code', 'nodes' => [
            ['code' => 'a', 'label' => 'A', 'children' => [['code' => 'b', 'label' => 'B']]],
        ]]],
        ['id' => 'team', 'type' => 'wx-transfer', 'name' => 'team', 'props' => ['items' => [['value' => 'ann'], ['value' => 'bob']]]],
        ['id' => 'title', 'type' => 'wx-heading', 'label' => 'Draws, stores nothing'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('app.timezone', 'UTC');

        Screens::register('forms.all', [['id' => 'card', 'type' => 'wx-card', 'children' => self::TREE]]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function save(array $input): array
    {
        return $this->app->make(ScreenValues::class)->validate('forms.all', $input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, list<string>>
     */
    private function refused(array $input): array
    {
        try {
            $this->save($input);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        $this->fail('Validation should have failed.');
    }

    #[Test]
    public function every_new_control_is_a_registered_field_type(): void
    {
        $names = $this->app->make(FieldTypes::class)->names();

        foreach (self::TREE as $node) {
            if ($node['type'] !== 'wx-heading') {
                $this->assertContains($node['type'], $names);
            }
        }

        $this->assertNotContains('wx-heading', $names, 'a heading draws and holds no value');
    }

    #[Test]
    public function a_good_value_of_each_is_kept_cast(): void
    {
        $stored = $this->save([
            'services' => ['design', 'hosting'],
            'align' => 'right',
            'volume' => '7',
            'price' => ['100', 250.5],
            'stars' => 4,
            'halves' => 7.5,
            'opens' => '09:30',
            'starts' => '2026-09-25T11:06:00+03:00',
            'season' => ['2026-06-01', '2026-08-31'],
            'tags' => [' red ', 'blue'],
            'mood' => ['calm'],
            'city' => 'Lviv',
            'icon' => 'plus',
            'code' => '{"a": 1}',
            'section' => ['content', 'news'],
            'leaf' => 'news',
            'part' => '1',
            'parts' => ['a', 'b'],
            'team' => ['bob'],
        ]);

        $this->assertSame([
            'services' => ['design', 'hosting'],
            'align' => 'right',
            'volume' => 7,
            'price' => [100, 250.5],
            'stars' => 4,
            'halves' => 7.5,
            'opens' => '09:30',
            // The same moment, moved into the application's timezone.
            'starts' => '2026-09-25T08:06:00+00:00',
            'season' => ['2026-06-01', '2026-08-31'],
            'tags' => ['red', 'blue'],
            'mood' => ['calm'],
            'city' => 'Lviv',
            'icon' => 'plus',
            'code' => '{"a": 1}',
            'section' => ['content', 'news'],
            'leaf' => 'news',
            'part' => '1',
            'parts' => ['a', 'b'],
            'team' => ['bob'],
        ], $stored);
    }

    #[Test]
    public function an_emptied_control_keeps_null_rather_than_its_empty_shape(): void
    {
        $stored = $this->save([
            'volume' => null,
            'price' => null,
            'opens' => '',
            'starts' => '',
            'season' => null,
            'tags' => [],
            'section' => [],
            'leaf' => null,
            'parts' => [],
            'team' => [],
        ]);

        $this->assertSame(array_fill_keys(array_keys($stored), null), $stored);
        $this->assertCount(10, $stored);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function badValues(): iterable
    {
        yield 'a box the group does not have' => ['services', ['design', 'banking']];
        yield 'fewer ticks than props.min' => ['services', []];
        yield 'more ticks than props.max' => ['services', ['design', 'code', 'hosting']];
        yield 'a segment that is not there' => ['align', 'centre'];
        yield 'a slider past its max' => ['volume', 11];
        yield 'a range that is one number' => ['price', 5];
        yield 'a range the wrong way round' => ['price', [500, 100]];
        yield 'a range outside its bounds' => ['price', [0, 5000]];
        yield 'more stars than there are' => ['stars', 6];
        yield 'half a star where halves are off' => ['stars', 3.5];
        yield 'a third of a star' => ['halves', 3.3];
        yield 'an hour past midnight' => ['opens', '25:00'];
        yield 'a moment that is not one' => ['starts', 'soon'];
        yield 'a season that ends before it starts' => ['season', ['2026-08-31', '2026-06-01']];
        yield 'a season with one end' => ['season', ['2026-06-01']];
        yield 'more tags than props.max' => ['tags', ['a', 'b', 'c', 'd']];
        yield 'the same tag twice' => ['tags', ['a', 'a']];
        yield 'a tag that is not a word' => ['tags', [['nested']]];
        yield 'a tag outside a closed list' => ['mood', ['angry']];
        yield 'a branch where only leaves may be chosen' => ['section', ['content']];
        yield 'a path that is not in the tree' => ['section', ['shop', 'news']];
        yield 'a last value that is not a leaf' => ['leaf', 'content'];
        yield 'a node that is not in the tree' => ['part', 3];
        yield 'one of several that is not in the tree' => ['parts', ['a', 'z']];
        yield 'one key where several are expected' => ['parts', 'a'];
        yield 'someone the transfer does not list' => ['team', ['eve']];
    }

    #[Test]
    #[DataProvider('badValues')]
    public function a_bad_value_is_refused_under_its_own_name(string $name, mixed $value): void
    {
        $errors = $this->refused([$name => $value]);

        $this->assertSame([$name], array_keys($errors));
        // Laravel's own sentences, translated — not the key of one.
        $this->assertStringNotContainsString('validation.', $errors[$name][0]);
    }

    #[Test]
    public function a_lazy_tree_is_checked_for_shape_only(): void
    {
        Screens::register('forms.lazy', [
            ['id' => 'section', 'type' => 'wx-cascader', 'name' => 'section', 'props' => ['lazy' => true]],
            ['id' => 'part', 'type' => 'wx-tree-select', 'name' => 'part', 'props' => ['lazy' => true, 'multiple' => true]],
        ]);

        $stored = $this->app->make(ScreenValues::class)->validate('forms.lazy', [
            'section' => ['anything', 'fetched'],
            'part' => [7, 9],
        ]);

        $this->assertSame(['section' => ['anything', 'fetched'], 'part' => [7, 9]], $stored);
    }

    #[Test]
    public function a_moment_without_an_offset_is_read_in_the_application_timezone(): void
    {
        $this->app['config']->set('app.timezone', 'Europe/Kyiv');

        $this->assertSame(
            ['starts' => '2026-09-25T11:06:00+03:00'],
            $this->save(['starts' => '2026-09-25 11:06:00']),
        );
    }
}
