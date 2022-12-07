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
     * JSON mutator: casts & sorts
     *
     * @param mixed $value
     * @param array $types
     * @param array $sorts
     * @param array $lists
     * @param array $purges
     * @param array $parentKeys
     * @return mixed
     */
    public function mutateArray(
        mixed $value,
        ?array $types,
        array $sorts = null,
        array $lists = null,
        array $purges = null,
        array $parentKeys = []
    ): mixed {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $path = array_merge($parentKeys, [$key]);

                if (is_array($item)) {
                    $value[$key] = $this->mutateArray($value[$key], $types, $sorts, $lists, $purges, $path);

                    foreach ((array) $sorts as $sortKey) {
                        if (! $this->isMatching($sortKey, $path)) {
                            continue;
                        }

                        sort($value[$key]);
                        break;
                    }

                    foreach ((array) $lists as $listKey) {
                        if (! $this->isMatching($listKey, $path)) {
                            continue;
                        }

                        $value[$key] = array_values($value[$key]);
                        break;
                    }
                } else {
                    foreach ((array) $types as $typeKey => $cast) {
                        if (! $this->isMatching($typeKey, $path)) {
                            continue;
                        }

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

                    if (is_null($item)) {
                        foreach ((array) $purges as $purgeKey) {
                            if (! $this->isMatching($purgeKey, $path)) {
                                continue;
                            }

                            unset($value[$key]);
                            break;
                        }
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

                if ($item === []) {
                    $item = null;
                }
            }
            unset($item);
        }

        return $value;
    }

    /**
     * Check if the key matches to the path
     *
     * @param string $key
     * @param array $path
     * @return bool
     */
    public function isMatching(string $key, array $path): bool
    {
        $key = explode('.', $key);

        if (count($key) != count($path)) {
            return false;
        }

        while ($partKey = array_pop($key)) {
            $partPath = array_pop($path);

            if (is_null($partPath)) {
                return false;
            }

            if ($partPath !== $partKey && $partKey !== '*') {
                return false;
            }
        }

        return true;
    }
}
