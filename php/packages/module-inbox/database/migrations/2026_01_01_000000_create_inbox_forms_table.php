<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A form and its fields.
 *
 * A field is a row rather than a node of a described screen, and that is the one place this
 * module parts company with `module-settings` and `module-blocks` (§2.1). A field has an
 * identity that an answer refers to, a position somebody drags, and a flag saying whether it
 * becomes a column in the list of submissions — none of which a JSON array gives cheaply.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_forms', function (Blueprint $table): void {
            $table->id();

            // How the form is named everywhere outside the panel: in the component on the
            // site, in the address of the intake, in an export.
            $table->string('slug', 96)->unique();

            // Translatable: it is the heading of the notification too, and that is read by
            // whoever the notification went to.
            $table->json('title')->nullable();

            $table->boolean('is_enabled')->default(true);

            // Thank-you text, recipients, antispam — §5. Keys with dots in them are literal
            // keys, not paths, exactly as in `module-settings`.
            $table->json('options')->nullable();

            $table->integer('position')->default(0);
            $table->timestamps();
        });

        Schema::create('inbox_form_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->constrained('inbox_forms')->cascadeOnDelete();

            // The machine name: `fields[email]` in the HTML rather than `field_17`. A site
            // fills its hidden fields by it, a CSV column is headed by it, and renaming the
            // label breaks none of that. Optional — a field without one answers to `f{id}`.
            //
            // Indexed rather than unique: a field is deleted softly (§2.3), and a unique
            // index would let a field that is no longer in the form keep its name reserved
            // for good. Uniqueness among the live fields is checked where a field is written.
            $table->string('name', 64)->nullable();

            $table->string('type', 16)->default('text');

            $table->json('title')->nullable();
            $table->json('placeholder')->nullable();
            $table->json('help')->nullable();

            // Choices, limits, the accepted extensions of a file — §4.
            $table->json('options')->nullable();

            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_fullsize')->default(true);
            $table->boolean('in_table')->default(false);
            $table->integer('position')->default(0);

            // Soft, because the answers already given through this field keep pointing at it
            // and a hard delete would take their snapshot's owner with them.
            $table->softDeletes();
            $table->timestamps();

            $table->index(['form_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_form_fields');
        Schema::dropIfExists('inbox_forms');
    }
};
