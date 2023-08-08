<?php

namespace AnourValar\EloquentValidation\Tests;

trait ValidationTrait
{
    /**
     * Asserts that validation succeeded
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Support\HigherOrderTapProxy<\Illuminate\Database\Eloquent\Model>
     */
    protected function assertValidationSuccess(\Illuminate\Database\Eloquent\Model $model)
    {
        try {
            $model->validate();
            $this->assertTrue(true);

            return tap($model);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertFalse(
                true,
                'Validation failed: ' . json_encode($e->validator->errors()->toArray(), JSON_UNESCAPED_UNICODE)
            );
            throw $e;
        }
    }

    /**
     * Asserts that delete validation succeeded
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Support\HigherOrderTapProxy<\Illuminate\Database\Eloquent\Model>
     */
    protected function assertDeleteValidationSuccess(\Illuminate\Database\Eloquent\Model $model)
    {
        try {
            $model->validateDelete();
            $this->assertTrue(true);

            return tap($model);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertFalse(true, 'Validation failed: ' . json_encode($e->validator->errors()->keys()));
            throw $e;
        }
    }

    /**
     * Asserts that validation failed
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param mixed $keys
     * @param mixed $message
     * @return void
     */
    protected function assertValidationFailed(\Illuminate\Database\Eloquent\Model $model, $keys, $message = true): void
    {
        try {
            $model->validate();
            $this->assertFalse(true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->validator->errors()->toArray();
            foreach ($e->validator->errors()->all() as $error) {
                $this->assertStringNotContainsString('models/', $error);
            }

            foreach ((array) $keys as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $errors,
                    'Validation: ' . json_encode($e->validator->errors()->toArray(), JSON_UNESCAPED_UNICODE)
                );

                if ($message === true) {
                    continue;
                }

                $this->assertStringNotContainsString('models/', $message);
                $this->assertEquals($message, \Arr::first($errors[$key]));
            }
        }
    }

    /**
     * Asserts that delete validation failed
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param mixed $keys
     * @param mixed $message
     * @return void
     */
    protected function assertDeleteValidationFailed(\Illuminate\Database\Eloquent\Model $model, $keys, $message = true): void
    {
        try {
            $model->validateDelete();
            $this->assertFalse(true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->validator->errors()->toArray();
            foreach ($e->validator->errors()->all() as $error) {
                $this->assertStringNotContainsString('models/', $error);
            }

            foreach ((array) $keys as $key) {
                $this->assertArrayHasKey($key, $errors, 'Validation passed.');

                if ($message === true) {
                    continue;
                }

                $this->assertStringNotContainsString('models/', $message);
                $this->assertEquals($message, \Arr::first($errors[$key]));
            }
        }
    }
}
