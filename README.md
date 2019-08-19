# Eloquent validation

Validation feature for eloquent model (Laravel)

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
php artisan make:model-validated UserPhone
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
     * @see \Validator::after()
     *
     * @param \Illuminate\Validation\Validator $validator
     */
    public function afterValidation(\Illuminate\Validation\Validator $validator)
    {
        if (!\App\User::find($this->user_id)) {
            $validator->errors()->add('user_id', trans('model/user_phone.user_id_not_exists'));
        }
    }
}
