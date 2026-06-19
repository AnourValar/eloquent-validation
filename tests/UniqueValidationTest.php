<?php

namespace AnourValar\EloquentValidation\Tests;

use AnourValar\EloquentValidation\Tests\Models\User;

class UniqueValidationTest extends AbstractSuite
{
    /**
     * @return void
     */
    public function test_unique_single()
    {
        User::query()->insert(['name' => 'John', 'email' => 'a@a.com', 'role' => 'admin']);

        // duplicate email => fail
        $user = new User();
        $user->forceFill(['name' => 'Jane', 'email' => 'a@a.com', 'role' => 'user']);
        $this->assertValidationFailed(
            $user,
            ['email'],
            trans('eloquent-validation::validation.unique', ['attributes' => 'email'])
        );

        // unique email => ok
        $user = new User();
        $user->forceFill(['name' => 'Bob', 'email' => 'b@b.com', 'role' => 'user']);
        $this->assertValidationSuccess($user);
    }

    /**
     * @return void
     */
    public function test_unique_composite()
    {
        User::query()->insert(['name' => 'John', 'email' => 'a@a.com', 'role' => 'admin']);

        // same name + role => fail (keyed on the first field)
        $user = new User();
        $user->forceFill(['name' => 'John', 'email' => 'x@x.com', 'role' => 'admin']);
        $this->assertValidationFailed($user, ['name']);

        // same name, different role => ok
        $user = new User();
        $user->forceFill(['name' => 'John', 'email' => 'y@y.com', 'role' => 'user']);
        $this->assertValidationSuccess($user);
    }

    /**
     * @return void
     */
    public function test_unique_ignores_self_on_update()
    {
        User::query()->insert(['name' => 'John', 'email' => 'a@a.com', 'role' => 'admin']);

        $user = User::query()->where('email', 'a@a.com')->first();
        $user->forceFill(['role' => 'manager']); // name+role changes, no collision
        $this->assertValidationSuccess($user);
    }

    /**
     * @return void
     */
    public function test_unique_collision_with_other_on_update()
    {
        User::query()->insert([
            ['name' => 'John', 'email' => 'a@a.com', 'role' => 'admin'],
            ['name' => 'Jane', 'email' => 'b@b.com', 'role' => 'user'],
        ]);

        $user = User::query()->where('email', 'a@a.com')->first();
        $user->forceFill(['name' => 'Jane', 'role' => 'user']); // collides with the other row
        $this->assertValidationFailed($user, ['name']);
    }

    /**
     * @return void
     */
    public function test_unchangeable_on_update()
    {
        User::query()->insert(['name' => 'John', 'email' => 'a@a.com', 'role' => 'admin']);

        $user = User::query()->where('email', 'a@a.com')->first();
        $user->forceFill(['email' => 'new@a.com']);
        $this->assertValidationFailed(
            $user,
            ['email'],
            trans('eloquent-validation::validation.unchangeable', ['attribute' => 'email'])
        );
    }

    /**
     * @return void
     */
    public function test_basic_skips_unique()
    {
        User::query()->insert(['name' => 'John', 'email' => 'a@a.com', 'role' => 'admin']);

        $user = new User();
        $user->forceFill(['name' => 'Jane', 'email' => 'a@a.com', 'role' => 'user']); // duplicate email

        // basic = true => unique check skipped
        $user->validate(null, null, null, true);
        $this->assertTrue(true);
    }

    /**
     * @return void
     */
    public function test_validateDelete_success()
    {
        User::query()->insert(['name' => 'John', 'email' => 'a@a.com', 'role' => 'admin']);
        $user = User::query()->first();

        $this->assertDeleteValidationSuccess($user);
    }

    /**
     * @return void
     */
    public function test_validateDelete_additional_rules()
    {
        User::query()->insert(['name' => 'John', 'email' => 'a@a.com', 'role' => 'admin']);
        $user = User::query()->first();

        try {
            $user->validateDelete(null, ['name' => ['in:NOPE']]);
            $this->assertTrue(false);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('name', $e->validator->errors()->toArray());
        }
    }

    /**
     * @return void
     */
    public function test_validateDelete_after_validation_hook()
    {
        $this->partialMock(User::class, function ($mock) {
            $mock->shouldReceive('deleteAfterValidation')->once()->andReturnUsing(function ($validator, $basic) {
                $validator->errors()->add('foo', 'bar');
            });
        });

        $user = \App::make(User::class);
        $this->assertDeleteValidationFailed($user, ['foo']);
    }

    /**
     * @return void
     */
    public function test_validateRestore_success()
    {
        User::query()->insert(['name' => 'John', 'email' => 'a@a.com', 'role' => 'admin']);
        $user = User::query()->first();

        // Empty restoreAfterValidation hook => validation passes
        $this->assertSame($user, $user->validateRestore());
    }

    /**
     * @return void
     */
    public function test_validateRestore_additional_rules()
    {
        User::query()->insert(['name' => 'John', 'email' => 'a@a.com', 'role' => 'admin']);
        $user = User::query()->first();

        try {
            $user->validateRestore(null, ['name' => ['in:NOPE']]);
            $this->assertTrue(false);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('name', $e->validator->errors()->toArray());
        }
    }

    /**
     * @return void
     */
    public function test_validateRestore_after_validation_hook()
    {
        $this->partialMock(User::class, function ($mock) {
            // validateRestore must dispatch the "restore" hook, not the "delete" one
            $mock->shouldReceive('restoreAfterValidation')->once()->andReturnUsing(function ($validator, $basic) {
                $validator->errors()->add('foo', 'bar');
            });
            $mock->shouldNotReceive('deleteAfterValidation');
        });

        $user = \App::make(User::class);

        try {
            $user->validateRestore();
            $this->assertTrue(false);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('foo', $e->validator->errors()->toArray());
        }
    }

    /**
     * @return void
     */
    public function test_validateCustom_dispatches_given_method()
    {
        $this->partialMock(User::class, function ($mock) {
            $mock->shouldReceive('myCustomAfterValidation')->once()->andReturnUsing(function ($validator, $basic) {
                $validator->errors()->add('foo', 'bar');
            });
        });

        $user = \App::make(User::class);

        try {
            $user->validateCustom('myCustomAfterValidation');
            $this->assertTrue(false);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('foo', $e->validator->errors()->toArray());
        }
    }
}
