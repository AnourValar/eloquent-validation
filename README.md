# Validation for Eloquent Models

## Installation

```bash
composer require anourvalar/eloquent-validation
```


## Usage

### Creating with validation

```php
\App\UserPhone
    ::fields('user_id', 'phone_number') // fillable columns (mass assignment)
    ->fill(\Request::input())
    ->validate() // will throw exception if it fails
    ->save();
}
```


### Updating with validation

```php
\App\UserPhone
    ::findOrFail(\Request::input('id'))
    ->fields(['user_id', 'phone_number']) // also might be an array
    ->fill(\Request::input())
    ->validate()
    ->save();
}
```


## Generate model

```bash
php artisan make:model-validation UserPhone
```


## Model configuration

```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserPhone extends Model
{
    use \AnourValar\EloquentValidation\ModelTrait;
    
    /**
     * Validation rules
     * 
     * @var array
     */
    protected $rules = [
        'user_id' => ['required', 'integer'],
        'phone_number' => ['required', 'string', 'min:8', 'max:13', 'unique'],
    ];
    
    /**
     * Trim columns
     * 
     * @var array
     */
    protected $trim = [
        'phone_number', // trim-mutator
    ];
    
    /**
     * '' => null convertation
     * 
     * @var array
     */
    protected $nullable = [
        // empty string to null (convertation) mutator
    ];
    
    /**
     * Calculated columns
     * 
     * @var array
     */
    protected $calculated = [
        // columns which could be changed only in listeners (observers)
    ];
    
    /**
     * Immutable columns
     * 
     * @var array
     */
    protected $unchangeable = [
        'user_id', // unchangeable columns after creation
    ];
    
    /**
     * Unique columns sets
     * 
     * @var array
     */
    protected $unique = [
        // unique sets of columns
    ];
    
    /**
     * "Save" after-validation
     * 
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function saveValidation(\Illuminate\Validation\Validator $validator)
    {
        if (!\App\User::find($this->user_id)) {
            $validator->errors()->add('user_id', trans('models/user_phone.user_id_not_exists'));
        }
    }
	
    /**
     * "Delete" after-validation
     * 
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function deleteValidation(\Illuminate\Validation\Validator $validator)
    {
        
    }
}
