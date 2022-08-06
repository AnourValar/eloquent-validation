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

        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($closure) {
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
     * JSON mutator: casts & sorts
     *
     * @param mixed $value
     * @param array $types
     * @param array $sorts
     * @param string $parentKey
     * @return mixed
     */
    public function mutateArray(mixed $value, ?array $types, array $sorts = null, string $parentKey = null): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $currKey = (is_integer($key) && ! is_null($parentKey)) ? $parentKey : $key;

                if (is_array($item)) {
                    $value[$key] = $this->mutateArray($value[$key], $types, $sorts, $currKey);

                    if (in_array($key, (array) $sorts)) {
                        sort($value[$key]);
                    }
                } elseif (isset($types[$currKey])) {
                    $cast = $types[$currKey];
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

        return $value;
    }

    /**
     * JSONB mutator
     *
     * @param mixed $value
     * @return mixed
     */
    public function mutateJsonb(mixed $value): mixed
    {
        if (is_array($value) && ! \Arr::isList($value)) {
            uksort($value, function ($a, $b) {
                $strlenA = mb_strlen($a);
                $strlenB = mb_strlen($b);

                if ($strlenA == $strlenB) {
                    return $a <=> $b;
                }

                return $strlenA <=> $strlenB;
            });
        }

        if (is_array($value)) {
            foreach ($value as &$item) {
                $item = $this->mutateJsonb($item);
            }
            unset($item);
        }

        return $value;
    }

    /**
     * JSON mutator: '' => null
     *
     * @param mixed $value
     * @return mixed
     */
    public function mutateArrayNullable(mixed $value): mixed
    {
        if (is_string($value) && trim($value) === '') {
            $value = null;
        }

        if (is_array($value)) {
            foreach ($value as &$item) {
                $item = $this->mutateArrayNullable($item);
            }
            unset($item);
        }

        return $value;
    }
}
