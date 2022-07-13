<?php

namespace AnourValar\EloquentValidation;

class ValidatorHelper
{
    /**
     * After-validation with extensible rules
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param callable $closure
     * @param mixed $prefix
     * @throws \LogicException
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     * @return void
     */
    public function afterValidate(\Illuminate\Validation\Validator $validator, callable $closure, $prefix = null): void
    {
        if ($validator->getRules()) {
            throw new \LogicException('Incorrect usage.');
        }

        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($closure)
        {
            static $triggered;

            if (! $triggered) {
                $triggered = true;

                return $closure($validator);
            }
        });

        $passes = $validator->passes();
        if ($passes && $validator->getRules()) {
            $passes = $validator->passes();
        }

        if (! $passes) {
            throw new \AnourValar\EloquentValidation\Exceptions\ValidationException($validator, null, 'default', $prefix);
        }
    }

    /**
     * Check if value should be null
     *
     * @param mixed $value
     * @return bool
     */
    public function isEmpty($value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! $this->isEmpty($item)) {
                    return false;
                }
            }

            return true;
        }

        return (is_null($value) || ((is_string($value) && trim($value) === '')));
    }

    /**
     * JSON nested mutator (helper)
     *
     * @param mixed $value
     * @param array $schema
     * @param string $parentKey
     * @return mixed
     */
    public function canonizeArray(mixed $value, array $schema, string $parentKey = null): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $currKey = (is_integer($key) && !is_null($parentKey)) ? $parentKey : $key;

                if (is_array($item)) {
                    $value[$key] = $this->canonizeArray($value[$key], $schema, $currKey);
                } elseif (isset($schema[$currKey])) {
                    $cast = $schema[$currKey];
                    if (stripos($cast, '?') === 0) {
                        if (is_null($item)) {
                            $cast = null;
                        } else {
                            $cast = mb_substr($cast, 1);
                        }
                    }

                    if ($cast) {
                        settype($item, $cast);
                        $value[$key] = $item;
                    }
                }
            }
        }

        if (is_null($parentKey) && !is_null($value)) {
            $value = json_encode($value);
        }

        return $value;
    }
}
