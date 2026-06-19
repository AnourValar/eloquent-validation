<?php

namespace AnourValar\EloquentValidation\Tests;

use AnourValar\EloquentValidation\Exceptions\ValidationException;

class ValidationExceptionTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_construct_from_scalar()
    {
        $e = new ValidationException('hello');

        $this->assertSame(['error'], $e->validator->errors()->keys());
        $this->assertSame('hello', $e->validator->errors()->first('error'));
    }

    /**
     * @return void
     */
    public function test_construct_from_array()
    {
        $e = new ValidationException(['name' => 'is bad', 'email' => ['a', 'b']]);

        $this->assertSame(['name', 'email'], $e->validator->errors()->keys());
        $this->assertSame('is bad', $e->validator->errors()->first('name'));
        $this->assertSame(['a', 'b'], $e->validator->errors()->get('email'));
    }

    /**
     * @return void
     */
    public function test_construct_with_prefix()
    {
        // scalar prefix
        $e = new ValidationException(['name' => 'bad'], null, 'default', 'foo');
        $this->assertSame(['foo.name'], $e->validator->errors()->keys());

        // array prefix
        $e = new ValidationException(['name' => 'bad'], null, 'default', ['foo', 'bar']);
        $this->assertSame(['foo.bar.name'], $e->validator->errors()->keys());

        // array prefix with empty parts filtered out
        $e = new ValidationException(['name' => 'bad'], null, 'default', ['foo', '', null, 'bar']);
        $this->assertSame(['foo.bar.name'], $e->validator->errors()->keys());

        // empty prefix => no prefix
        $e = new ValidationException(['name' => 'bad'], null, 'default', '');
        $this->assertSame(['name'], $e->validator->errors()->keys());

        $e = new ValidationException(['name' => 'bad'], null, 'default', []);
        $this->assertSame(['name'], $e->validator->errors()->keys());
    }

    /**
     * @return void
     */
    public function test_construct_from_validator_with_prefix()
    {
        $validator = \Validator::make([], []);
        $validator->errors()->add('name', 'bad');
        $validator->errors()->add('name', 'worse');

        $e = new ValidationException($validator, null, 'default', 'foo');
        $this->assertSame(['foo.name'], $e->validator->errors()->keys());
        $this->assertSame(['bad', 'worse'], $e->validator->errors()->get('foo.name'));
    }

    /**
     * @return void
     */
    public function test_add_prefix()
    {
        $e = new ValidationException('hello');

        $this->assertSame(['foo.error'], $e->addPrefix('foo')->validator->errors()->keys());
        $this->assertSame(['foo.bar.error'], $e->addPrefix(['foo', 'bar'])->validator->errors()->keys());
        $this->assertSame(['foo.bar.error'], $e->addPrefix(['foo', '', 'bar'])->validator->errors()->keys());

        // no prefix keeps keys intact
        $this->assertSame(['error'], $e->addPrefix(null)->validator->errors()->keys());
        $this->assertSame(['error'], $e->addPrefix('')->validator->errors()->keys());

        // original is not mutated
        $this->assertSame(['error'], $e->validator->errors()->keys());
    }
}
