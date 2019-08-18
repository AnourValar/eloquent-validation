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
     */
    public function __construct($errors, $response = null, $errorBag = 'default')
    {
        if ($errors instanceof \Illuminate\Validation\Validator) {
            $validator = $errors;
        } else {
            if (is_scalar($errors)) {
                $errors = ['error' => $errors];
            }
            
            $validator = \Validator::make([], []);
            foreach ($errors as $key => $item) {
                foreach ((array)$item as $sub) {
                    $validator->errors()->add($key, $sub);
                }
            }
        }
        
        parent::__construct($validator, $response, $errorBag);
    }
}
