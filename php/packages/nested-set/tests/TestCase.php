<?php

declare(strict_types=1);

namespace WebxUi\NestedSet\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\NestedSet\NestedSet;
use WebxUi\NestedSet\NestedSetServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [NestedSetServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            NestedSet::columns($table);
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('name');
            NestedSet::columns($table);
            $table->timestamps();
        });
    }
}
