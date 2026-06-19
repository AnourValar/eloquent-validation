<?php

namespace AnourValar\EloquentValidation\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

abstract class AbstractSuite extends \Orchestra\Testbench\TestCase
{
    use \AnourValar\EloquentValidation\Tests\ValidationTrait;

    /**
     * Init
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app)
    {
        return [
            \AnourValar\EloquentValidation\Providers\EloquentValidationServiceProvider::class,
        ];
    }

    /**
     * @return void
     */
    protected function setUpDatabase()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->string('role')->nullable();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
        });

        Schema::create('article_tag', function (Blueprint $table) {
            $table->integer('article_id');
            $table->integer('tag_id');
        });
    }
}
