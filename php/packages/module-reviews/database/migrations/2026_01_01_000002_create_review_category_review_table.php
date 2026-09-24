<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which categories a review is in, and where it stands inside each (decision 6).
 *
 * After both tables it links, which the filenames guarantee inside this package.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_category_review', function (Blueprint $table): void {
            $table->categoryLinks('review', 'review_categories', 'reviews');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_category_review');
    }
};
