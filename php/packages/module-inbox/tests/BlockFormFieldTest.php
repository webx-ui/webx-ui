<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Blocks\Tests\TestCase as BlocksTestCase;
use WebxUi\Inbox\InboxServiceProvider;
use WebxUi\Inbox\Models\Form;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * `wx-inbox-form` in a block, through the doors a block is written by: the panel's save
 * (`storeBlocks`) and an agent's `blocks_set_content` / `blocks_edit_content`. A slug no form
 * has is refused when it is new and kept when the page already held it.
 */
final class BlockFormFieldTest extends BlocksTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), InboxServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);

        $this->publish('contact', '<div data-wx-block="contact">{{ $form }}</div>', [], [
            'schema' => [['id' => 'form', 'type' => 'wx-inbox-form', 'label' => 'Form']],
        ]);

        Form::query()->create(['slug' => 'contact', 'title' => ['en' => 'Contact'], 'is_enabled' => true]);
    }

    #[Test]
    public function the_panel_keeps_the_slug_and_refuses_one_no_form_has(): void
    {
        $page = Page::query()->create(['title' => 'About', 'slug' => 'about']);

        $tree = $page->storeBlocks([$this->node('contact', ['form' => ' contact '], 'k-one')]);
        $this->assertSame('contact', $tree[0]['values']['form']);

        $this->assertNull($page->storeBlocks([$this->node('contact', ['form' => ''], 'k-one')])[0]['values']['form']);

        try {
            $page->storeBlocks([$this->node('contact', ['form' => 'contakt'], 'k-one')]);
            $this->fail('A slug no form has was accepted.');
        } catch (ValidationException $refused) {
            $this->assertStringContainsString('The Inbox has no form “contakt”. It has: contact.', json_encode($refused->errors(), JSON_UNESCAPED_UNICODE) ?: '');
        }
    }

    #[Test]
    public function a_page_that_already_held_a_slug_is_saved_again_with_it(): void
    {
        // Written before the picker — or before its form was deleted.
        $page = Page::query()->create(['title' => 'About', 'slug' => 'about', 'blocks' => [
            $this->node('contact', ['form' => 'gone'], 'k-one'),
        ]]);

        $tree = $page->storeBlocks([$this->node('contact', ['form' => 'gone'], 'k-one')]);

        $this->assertSame('gone', $tree[0]['values']['form']);
    }

    #[Test]
    public function an_agent_is_told_which_forms_there_are(): void
    {
        $page = Page::query()->create(['title' => 'About', 'slug' => 'about']);

        $this->agent('set_content', ['force' => true, 'entity' => 'note', 'id' => $page->id, 'blocks' => [
            ['key' => 'k-one', 'type' => 'contact', 'values' => ['form' => 'contakt']],
        ]], $this->editor())->assertHasErrors(['The Inbox has no form “contakt”. It has: contact.']);

        $this->assertNull($page->refresh()->draft);

        $this->agent('set_content', ['force' => true, 'entity' => 'note', 'id' => $page->id, 'blocks' => [
            ['key' => 'k-one', 'type' => 'contact', 'values' => ['form' => 'contact']],
        ]], $this->editor())->assertOk();

        $draft = $this->draft($page);
        $this->assertSame('contact', $draft['blocks'][0]['values']['form']);

        $this->agent('edit_content', ['entity' => 'note', 'id' => $page->id, 'revision' => Content::revision($draft['blocks']), 'ops' => [
            ['op' => 'set', 'key' => 'k-one', 'values' => ['form' => 'nope']],
        ]], $this->editor())->assertHasErrors(['The Inbox has no form “nope”. It has: contact.']);

        $this->assertSame('contact', $this->draft($page)['blocks'][0]['values']['form'], 'a refused edit writes nothing');
    }

    /**
     * The draft as it is in the database now — read anew, not off the model the test holds.
     *
     * @return array<string, mixed>
     */
    private function draft(Page $page): array
    {
        $draft = Page::query()->findOrFail($page->id)->draft;
        $this->assertIsArray($draft);

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments, CmsUser $as): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('blocks_'.$tool));

        return WebxServer::actingAs($as, 'cms')->tool($bound, $arguments);
    }
}
