<?php

namespace AnourValar\EloquentValidation\Tests;

use Illuminate\Database\Schema\Blueprint;

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

    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            \AnourValar\EloquentValidation\Providers\EloquentValidationServiceProvider::class,
        ];
    }
}
