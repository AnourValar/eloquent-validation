<?php

namespace AnourValar\EloquentValidation\Exceptions;

class ValidationException extends \Illuminate\Validation\ValidationException
{
    /**
     * @param mixed $errors
     * @param mixed $response
     * @param string $errorBag
     * @param mixed $prefix
     * @param bool $replaceKey
     */
    public function __construct($errors, $response = null, $errorBag = 'default', $prefix = null, bool $replaceKey = false)
    {
        $prefix = $this->canonizePrefix($prefix, $replaceKey);

        if ($errors instanceof \Illuminate\Validation\Validator) {
            if (is_null($prefix)) {
                $validator = $errors;
            } else {
                $validator = \Validator::make([], []);
                foreach ($errors->errors()->messages() as $key => $items) {
                    if ($replaceKey) {
                        $key = '';
                    }

                    foreach ((array) $items as $item) {
                        $validator->errors()->add($prefix.$key, $item);
                    }
                }
            }
        } else {
            if (is_scalar($errors)) {
                $errors = ['error' => $errors];
            }

            $validator = \Validator::make([], []);
            foreach ($errors as $key => $items) {
                if ($replaceKey && ! is_null($prefix)) {
                    $key = '';
                }

                foreach ((array) $items as $item) {
                    $validator->errors()->add($prefix.$key, $item);
                }
            }
        }

        parent::__construct($validator, $response, $errorBag);
    }

    /**
     * Add prefix to keys
     *
     * @param mixed $prefix
     * @return \AnourValar\EloquentValidation\Exceptions\ValidationException
     */
    public function addPrefix($prefix): ValidationException
    {
        $prefix = $this->canonizePrefix($prefix, false);

        $validator = \Validator::make([], []);

        foreach ($this->validator->errors()->messages() as $key => $items) {
            foreach ((array) $items as $item) {
                $validator->errors()->add($prefix.$key, $item);
            }
        }

        $this->validator = $validator;

        return $this;
    }

    /**
     * @param mixed $prefix
     * @param bool $replaceKey
     * @return string|null
     */
    protected function canonizePrefix($prefix, bool $replaceKey)
    {
        if (! is_iterable($prefix)) {
            return $prefix;
        }

        foreach ($prefix as $key => $item) {
            if (! is_scalar($item) || ! mb_strlen($item)) {
                unset($prefix[$key]);
            }
        }

        if ($prefix) {
            $prefix = implode('.', $prefix);

            if (! $replaceKey) {
                $prefix .= '.';
            }
        } else {
            $prefix = null;
        }

        return $prefix;
    }
}
