<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();

            // 1, 2, 3… within the block. The whole snapshot, not a diff: rolling back is then
            // trivial, and there is nothing to save — a version is a few kilobytes of text.
            $table->unsignedInteger('number');

            // The fields, as screen nodes of `@webx-ui/schema`.
            $table->json('schema');
            $table->longText('template');
            $table->longText('styles');
            // The body of the initialiser, not a script run once (§10 of the spec).
            $table->longText('script')->nullable();
            // Sample values: the preview in the picker, the render check before publishing,
            // and the plainest documentation of the data shape an agent gets.
            $table->json('sample');

            $table->unsignedBigInteger('author_id')->nullable();
            // `panel`, `mcp` or `import`. 16, so that every one of them fits with room; the
            // default has to fit too, and MySQL refuses one that does not at CREATE time.
            $table->string('source', 16)->default('panel');
            $table->string('comment', 255)->nullable();

            // A snapshot never changes, so it never has an `updated_at`.
            $table->timestamp('created_at')->nullable();

            $table->unique(['block_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_versions');
    }
};
