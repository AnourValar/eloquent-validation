<?php

namespace AnourValar\EloquentValidation\Tests;

use AnourValar\EloquentValidation\Exceptions\ValidationException;
use AnourValar\EloquentValidation\ValidatorHelper;

class ValidatorHelperTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_afterValidate_success()
    {
        $validator = \Validator::make([], []);

        (new ValidatorHelper())->afterValidate($validator, function ($validator) {
            // no errors added
        });

        $this->assertTrue(true);
    }

    /**
     * @return void
     */
    public function test_afterValidate_failure()
    {
        $validator = \Validator::make([], []);

        $this->expectException(ValidationException::class);

        (new ValidatorHelper())->afterValidate($validator, function ($validator) {
            $validator->errors()->add('foo', 'bar');
        });
    }

    /**
     * @return void
     */
    public function test_afterValidate_failure_prefix()
    {
        $validator = \Validator::make([], []);

        try {
            (new ValidatorHelper())->afterValidate(
                $validator,
                function ($validator) {
                    $validator->errors()->add('foo', 'bar');
                },
                'prefix'
            );
            $this->assertTrue(false);
        } catch (ValidationException $e) {
            $this->assertSame(['prefix.foo'], $e->validator->errors()->keys());
        }
    }

    /**
     * @return void
     */
    public function test_afterValidate_incorrect_usage()
    {
        $validator = \Validator::make([], ['foo' => ['required']]);

        $this->expectException(\LogicException::class);

        (new ValidatorHelper())->afterValidate($validator, function ($validator) {
            // ...
        });
    }
}
