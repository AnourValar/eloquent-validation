<?php

namespace AnourValar\EloquentValidation\Exceptions;

class ValidationException extends \Illuminate\Validation\ValidationException
{
    /**
     * The status code to use for the response.
     *
     * @var int
     */
    public $status = 400;

    /**
     * @param mixed $errors
     * @param mixed $response
     * @param string $errorBag
     * @param mixed $prefix
     */
    public function __construct($errors, $response = null, $errorBag = 'default', $prefix = null)
    {
        $prefix = $this->canonizePrefix($prefix);

        if ($errors instanceof \Illuminate\Validation\Validator) {
            if (is_null($prefix)) {
                $validator = $errors;
            } else {
                $validator = \Validator::make([], []);
                foreach ($errors->errors()->messages() as $key => $items) {
                    foreach ((array)$items as $item) {
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
                foreach ((array)$items as $item) {
                    $validator->errors()->add($prefix.$key, $item);
                }
            }
        }

        parent::__construct($validator, $response, $errorBag);
    }

    /**
     * @param mixed $prefix
     * @return string|NULL
     */
    protected function canonizePrefix($prefix)
    {
        if (! is_iterable($prefix)) {
            return $prefix;
        }

        foreach ($prefix as $key => $item) {
            if (!is_scalar($item) || !mb_strlen($item)) {
                unset($prefix[$key]);
            }
        }

        if ($prefix) {
            $prefix = implode('.', $prefix) . '.';
        } else {
            $prefix = null;
        }

        return $prefix;
    }
}
