<?php

declare(strict_types=1);

namespace WebxUi\Faq\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Faq\Models\Question;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * A dry run is the write, rolled back; a name two categories share is refused; what the tools do
 * not take is refused; the bin can be undone and emptied by an agent too.
 */
final class McpDryRunTest extends TestCase
{
    #[Test]
    public function the_dry_run_of_create_answers_what_create_would_and_keeps_nothing(): void
    {
        $dry = $this->content($this->agent('faq_create', ['question' => 'Do you take cards?', 'dry_run' => true]));

        $this->assertTrue($dry['dry_run']);
        $this->assertNull($dry['question']['id']);
        $this->assertSame('do-you-take-cards', $dry['question']['anchor']);
        $this->assertSame(0, Question::query()->withTrashed()->count());
    }

    #[Test]
    public function a_category_name_two_share_is_refused_with_both_ids(): void
    {
        $first = $this->category('Payment');
        $second = $this->category('Payment');

        $this->agent('faq_list', ['category' => 'payment'])->assertHasErrors(["#{$first->id}, #{$second->id}"]);
        $this->agent('faq_categories_create', ['title' => 'Delivery'])->assertOk();
        $this->agent('faq_categories_create', ['title' => 'DELIVERY'])->assertHasErrors(['already exists']);
    }

    #[Test]
    public function unknown_arguments_and_fields_are_refused(): void
    {
        $question = $this->question('Do you take cards?');

        $this->agent('faq_create', ['question' => 'New', 'anchor' => 'mine'])->assertHasErrors(['faq_create has no argument [anchor]']);
        $this->agent('faq_update', ['question' => $question->id, 'values' => ['anchor' => 'mine']])->assertHasErrors(['faq_update has no field [anchor]']);
    }

    #[Test]
    public function a_question_in_the_bin_is_restored_with_its_anchor_or_purged(): void
    {
        $kept = $this->question('Do you take cards?');
        $gone = $this->question('Do you deliver?');

        $kept->delete();
        $gone->delete();

        $restored = $this->content($this->agent('faq_restore', ['question' => $kept->anchor]));

        $this->assertSame($kept->anchor, $restored['question']['anchor']);
        $this->assertFalse($kept->refresh()->trashed());

        $this->agent('faq_purge', ['question' => $gone->id, 'dry_run' => true])->assertOk();
        $this->assertNotNull(Question::withTrashed()->find($gone->id));

        $this->agent('faq_purge', ['question' => $gone->id])->assertOk();
        $this->assertNull(Question::withTrashed()->find($gone->id));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
