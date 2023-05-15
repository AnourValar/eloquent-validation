<?php

namespace AnourValar\EloquentValidation\Exceptions;

class ValidationException extends \Illuminate\Validation\ValidationException
{
    /**
     * @param mixed $validator
     * @param mixed $response
     * @param string $errorBag
     * @param mixed $prefix
     * @return void
     */
    public function __construct($validator, $response = null, $errorBag = 'default', $prefix = null)
    {
        if (is_string($validator)) {
            $validator = trans($validator);
        }

        $prefix = $this->canonizePrefix($prefix);

        if ($validator instanceof \Illuminate\Validation\Validator) {
            if (is_null($prefix)) {
                $canonizedValidator = $validator;
            } else {
                $canonizedValidator = \Validator::make([], []);
                foreach ($validator->errors()->messages() as $key => $items) {
                    foreach ((array) $items as $item) {
                        $canonizedValidator->errors()->add($prefix.$key, $item);
                    }
                }
            }
        } else {
            if (is_scalar($validator)) {
                $validator = ['error' => $validator];
            }

            $canonizedValidator = \Validator::make([], []);
            foreach ($validator as $key => $items) {
                foreach ((array) $items as $item) {
                    $canonizedValidator->errors()->add($prefix.$key, $item);
                }
            }
        }

        parent::__construct($canonizedValidator, $response, $errorBag);
    }

    /**
     * Add prefix to the keys
     *
     * @param array|string $prefix
     * @return static
     */
    public function addPrefix(array|string|null $prefix): ValidationException
    {
        return new static($this->validator, $this->response, $this->errorBag, $prefix);
    }

    /**
     * Replace the keys
     *
     * @param string $from
     * @param string|null $to
     * @return static
     */
    public function replaceKey(string $from, ?string $to): ValidationException
    {
        if (is_null($to) || $from === $to) {
            return new static($this->validator, $this->response, $this->errorBag);
        }

        $validator = \Validator::make([], []);

        foreach ($this->validator->errors()->messages() as $key => $items) {
            if ($key == $from) {
                $key = $to;
            }

            if (strpos($key, "$from.") === 0) {
                $key = mb_substr($key, mb_strlen($from));
                $key = $to . $key;

                if (! mb_strlen($to)) {
                    $key = mb_substr($key, 1);
                }
            }

            if (strpos($key, ".$from") === (mb_strlen($key) - mb_strlen($from) - 1)) {
                $key = mb_substr($key, 0, -mb_strlen($from));
                $key = $key . $to;

                if (! mb_strlen($to)) {
                    $key = mb_substr($key, 0, -1);
                }
            }

            if (! mb_strlen($to)) {
                $key = str_replace(".$from.", '.', $key);
            } else {
                $key = str_replace(".$from.", ".$to.", $key);
            }

            foreach ((array) $items as $item) {
                $validator->errors()->add($key, $item);
            }
        }

        return new static($validator, $this->response, $this->errorBag);
    }

    /**
     * @param mixed $prefix
     * @return string|null
     */
    protected function canonizePrefix(mixed $prefix): ?string
    {
        if (! is_iterable($prefix)) {
            if (is_string($prefix) && mb_strlen($prefix)) {
                $prefix .= '.';
            }

            return $prefix;
        }

        foreach ($prefix as $key => $item) {
            if (! is_scalar($item) || ! mb_strlen($item)) {
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
