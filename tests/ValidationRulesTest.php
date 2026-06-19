<?php

namespace AnourValar\EloquentValidation\Tests;

class ValidationRulesTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_config_rule_scalar()
    {
        config(['my_directory' => ['active' => 'Active', 'blocked' => 'Blocked']]);

        $this->assertTrue(\Validator::make(['status' => 'active'], ['status' => 'config:my_directory'])->passes());
        $this->assertTrue(\Validator::make(['status' => 'blocked'], ['status' => 'config:my_directory'])->passes());
        $this->assertFalse(\Validator::make(['status' => 'missing'], ['status' => 'config:my_directory'])->passes());
    }

    /**
     * @return void
     */
    public function test_config_rule_array()
    {
        config(['my_directory' => ['active' => 'Active', 'blocked' => 'Blocked']]);

        // all values present
        $this->assertTrue(\Validator::make(['status' => ['active', 'blocked']], ['status' => 'config:my_directory'])->passes());

        // one value missing
        $this->assertFalse(\Validator::make(['status' => ['active', 'missing']], ['status' => 'config:my_directory'])->passes());

        // duplicates are not allowed
        $this->assertFalse(\Validator::make(['status' => ['active', 'active']], ['status' => 'config:my_directory'])->passes());

        // non-scalar element
        $this->assertFalse(\Validator::make(['status' => [['active']]], ['status' => 'config:my_directory'])->passes());
    }

    /**
     * @return void
     */
    public function test_config_rule_message()
    {
        config(['my_directory' => ['active' => 'Active']]);

        $validator = \Validator::make(['status' => 'missing'], ['status' => 'config:my_directory']);
        $this->assertFalse($validator->passes());
        $this->assertSame(
            trans('eloquent-validation::validation.config', ['attribute' => 'status']),
            $validator->errors()->first('status')
        );
    }

    /**
     * @return void
     */
    public function test_config_rule_requires_parameter()
    {
        $this->expectException(\LogicException::class);

        \Validator::make(['status' => 'active'], ['status' => 'config'])->passes();
    }

    /**
     * @return void
     */
    public function test_array_keys_rule()
    {
        $rules = [
            'data' => ['array', 'array_keys'],
            'data.title' => ['string'],
            'data.body' => ['string'],
        ];

        // only allowed keys
        $this->assertTrue(\Validator::make(['data' => ['title' => 'x', 'body' => 'y']], $rules)->passes());

        // extra key is rejected
        $this->assertFalse(\Validator::make(['data' => ['title' => 'x', 'extra' => 'z']], $rules)->passes());
    }

    /**
     * @return void
     */
    public function test_array_keys_only_rule()
    {
        $this->assertTrue(\Validator::make(['data' => ['a' => 1, 'b' => 2]], ['data' => 'array_keys_only:a,b'])->passes());
        $this->assertTrue(\Validator::make(['data' => ['a' => 1]], ['data' => 'array_keys_only:a,b'])->passes());
        $this->assertFalse(\Validator::make(['data' => ['a' => 1, 'c' => 3]], ['data' => 'array_keys_only:a,b'])->passes());
    }

    /**
     * @return void
     */
    public function test_array_keys_id_rule()
    {
        // numeric keys >= 1
        $this->assertTrue(
            \Validator::make(['data' => [1 => 'x', 2 => 'y']], ['data' => ['array', 'array_keys_id'], 'data.1' => ['string'], 'data.2' => ['string']])->passes()
        );

        // non-numeric key
        $this->assertFalse(
            \Validator::make(['data' => ['abc' => 'x']], ['data' => ['array', 'array_keys_id'], 'data.abc' => ['string']])->passes()
        );

        // zero is not allowed
        $this->assertFalse(
            \Validator::make(['data' => [0 => 'x']], ['data' => ['array', 'array_keys_id'], 'data.0' => ['string']])->passes()
        );
    }

    /**
     * @return void
     */
    public function test_not_empty_rule()
    {
        $this->assertTrue(\Validator::make(['x' => 'foo'], ['x' => 'not_empty'])->passes());
        $this->assertTrue(\Validator::make(['x' => '0'], ['x' => 'not_empty'])->passes());
        $this->assertTrue(\Validator::make(['x' => 0], ['x' => 'not_empty'])->passes());

        $this->assertFalse(\Validator::make(['x' => ''], ['x' => 'not_empty'])->passes());
        $this->assertFalse(\Validator::make(['x' => '   '], ['x' => 'not_empty'])->passes());

        $validator = \Validator::make(['x' => ''], ['x' => 'not_empty']);
        $this->assertFalse($validator->passes());
        $this->assertSame(
            trans('eloquent-validation::validation.not_empty', ['attribute' => 'x']),
            $validator->errors()->first('x')
        );
    }
}
