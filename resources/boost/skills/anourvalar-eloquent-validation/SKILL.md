---
name: anourvalar-eloquent-validation
description: Load when working in a Laravel project that depends on anourvalar/eloquent-validation - building/updating Eloquent models that use AnourValar\EloquentValidation\ModelTrait, calling $model->validate()/validateDelete(), wiring CrudService, throwing the package's ValidationException, or using the package's custom validation rules (config, array_keys, array_keys_only, array_keys_id, not_empty). The package exposes no facades; it ships traits, service classes, custom validation rules, and artisan generators (make:model-validation, make:observer-validation, model:validate).
---

# AnourValar Eloquent Validation

`anourvalar/eloquent-validation` adds first-class validation to Eloquent models through a trait that defines per-model rules, mutators, immutable/unique columns, JSON-nested handling, and "after validation" hooks. It also ships a CRUD/sync helper, a richer `ValidationException` with key prefixing, helpers for nested JSON mutation, a PHPUnit test trait, and several custom validation rules registered globally by the service provider.

## When to use

- Authoring or modifying an Eloquent model that includes `\AnourValar\EloquentValidation\ModelTrait`.
- Calling `$model->validate()`, `$model->validateDelete()`, or `$model->beforeValidate()` in controllers/services.
- Using the package's fluent `fields()->fill()->validate()->save()` flow.
- Building bulk create/update/delete endpoints via `\AnourValar\EloquentValidation\CrudService` (`execute`, `sync`).
- Throwing/handling `\AnourValar\EloquentValidation\Exceptions\ValidationException` (especially `addPrefix()` / `replaceKey()`).
- Writing tests against models with the trait via `\AnourValar\EloquentValidation\Tests\ValidationTrait`.
- Maintaining many-to-many sync inside model observers using `\AnourValar\EloquentValidation\Features\ManyToManyTrait`.
- Using/registering the custom rules `config:<key>`, `array_keys`, `array_keys_only`, `array_keys_id`, `not_empty`.
- Generating scaffolding with `php artisan make:model-validation`, `make:observer-validation`, or running `php artisan model:validate`.

## Facades

This package does **not** register any facades (no class extends `Illuminate\Support\Facades\Facade`). All functionality is exposed through the trait, service classes, and rules below.

## Services

### `AnourValar\EloquentValidation\ModelTrait`

Trait added to Eloquent models. Reads optional model properties and methods:

- `protected array $trim` - columns trimmed on set (recursive into arrays).
- `protected array $nullable` - columns where `''`, `[]`, or empty `MapperCollection` are converted to `null`.
- `protected array $computed` - read-only columns (only listeners/observers may change them).
- `protected array $unchangeable` - immutable after creation.
- `protected array $unique` - array of column groups validated for uniqueness (soft-delete-aware).
- `protected array $jsonNested` - per-column JSON mutation config: `jsonb` (sort keys), `nullable`, `purges`, `types`, `sorts`, `lists`.
- `public function saveRules(): array` - validation rules used by `validate()`.
- `public function saveAfterValidation(\Illuminate\Validation\Validator $validator, bool $basic): void`
- `public function deleteAfterValidation(\Illuminate\Validation\Validator $validator, bool $basic): void`

Public methods added to the model:

- `validate($prefix = null, ?array $additionalRules = null, ?array $additionalAttributeNames = null, bool $basic = false, bool $skip = false): static` - run `saveRules()`, unchangeable/computed/unique checks, then `saveAfterValidation`. Throws `AnourValar\EloquentValidation\Exceptions\ValidationException` on failure. `$basic`/`$skip` are forced off when `config('app.debug')` is true.
- `validateDelete($prefix = null, ?array $additionalRules = null, ?array $additionalAttributeNames = null, bool $basic = false): static` - validates additional rules, then calls `deleteAfterValidation`.
- `beforeValidate(array $validateAttributes, $prefix = null): static` - validate a subset of fields only (throws `\LogicException` for unknown attributes outside production).
- `scopeFields(...$fields)` / `$model->fields(...)` - replace `$fillable` for one statement; accepts varargs or a single array.
- `scopePublishFields(...)` / `scopeAddPublishFields(...)` - reset/append `$visible` and `$appends` for the next response, ordering attributes by selected columns when possible.
- `authorize($abilities, ?string $relation = null)` - calls `Gate::authorize($abilities, $this|relation)` and returns `tap($this)`.
- `mergeJsonNested(array $jsonNested): self` - merge extra JSON-nested config at runtime.
- `getComputed()`, `getUnchangeable()`, `getUnique()`, `getJsonNested()` - accessors for the configured arrays (or `null`).
- `getAttributeNames(): array` / `static setAttributeNames(?array $attributeNames): void` - per-locale attribute name cache; default names come from `lang/<dir>/<file>.attributes`.
- `extractAttributesListFromConfiguration(): array` - dumps configuration map used by `model:validate`.
- `scopeWithoutTrashedOr($builder, mixed $ids): void` - soft-delete scope: returns non-trashed rows OR the given ids. Throws `\LogicException` if the model lacks `SoftDeletes`.
- Overrides: `setAttribute` (enforces declared casts/relations outside production, applies `trim`/`nullable`/`jsonNested`/safe date parsing), `newInstance`, `originalIsEquivalent`, `asDateTime`, `getJsonCastFlags` (adds `JSON_PRESERVE_ZERO_FRACTION`).

```php
use AnourValar\EloquentValidation\ModelTrait;
use Illuminate\Database\Eloquent\Model;

class UserPhone extends Model
{
    use ModelTrait;

    protected $fillable = []; // populated via ->fields(...)
    protected $trim = ['phone_number'];
    protected $nullable = ['comment'];
    protected $computed = ['verified_at'];   // only observers may set
    protected $unchangeable = ['user_id'];   // frozen after create
    protected $unique = [['user_id', 'phone_number']];

    public function saveRules(): array
    {
        return [
            'user_id'      => ['required', 'integer'],
            'phone_number' => ['required', 'string', 'min:8', 'max:13', 'unique'],
            'comment'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function saveAfterValidation(\Illuminate\Validation\Validator $validator, bool $basic): void
    {
        if ($this->isDirty('user_id') && ! \App\Models\User::find($this->user_id)) {
            $validator->errors()->add('user_id', trans('models/user_phone.user_id_not_exists'));
        }
    }
}
```

### `AnourValar\EloquentValidation\CrudService`

Helper that performs bulk create/update/delete or sync against an array request. Resolve via the container (`app(CrudService::class)`) or `new CrudService()`. The model must use `ModelTrait` and have a primary key; if a Gate policy exists for the model, `create`/`update`/`delete` are authorized automatically.

- `execute(\Illuminate\Database\Eloquent\Model $model, $request, $createTriggerFields = null, ?callable $mutator = null, $validatePrefix = null): array` - iterates `$request` (array of rows). Row without primary key -> CREATE (only when one of `$createTriggerFields` is filled). Row with primary key and `_delete=1` -> DELETE. Otherwise UPDATE. Returns `['deleted', 'created', 'updated', 'affected']` counters. Throws `\LogicException` if the model has no key.
- `sync(\Illuminate\Database\Eloquent\Builder $eloquent, $request, $triggerFields = null, $validatePrefix = null): void` - reconciles the query result with `$request`: inserts missing rows (copying `Basic` where clauses from the builder onto the new model), deletes rows not present. Throws `\LogicException` on non-`Basic` where clauses.

```php
use AnourValar\EloquentValidation\CrudService;

$counters = app(CrudService::class)->execute(
    new \App\Models\UserPhone(),
    $request->input('rows'),       // [['id'=>1,'phone_number'=>'...'], ['phone_number'=>'...'], ['id'=>2,'_delete'=>1]]
    ['phone_number'],              // create-trigger fields
    null,
    'rows'                         // validate-prefix becomes "rows.0.phone_number" etc.
);
```

### `AnourValar\EloquentValidation\ValidatorHelper`

Utility class (instantiate with `new ValidatorHelper()`).

- `afterValidate(\Illuminate\Validation\Validator $validator, callable $closure, $prefix = null): void` - runs `$closure($validator)` as an `after()` callback; if it fails throws the package's `ValidationException` (with optional key prefix). Requires `$validator->getRules()` to be empty (otherwise `\LogicException`).
- `mutateArray(mixed $value, ?array $nullable = null, ?array $purges = null, ?array $types = null, ?array $sorts = null, ?array $lists = null, array $parentKeys = []): mixed` - normalizes a nested array using JSONPath-like keys (`$.a`, `$.*.*`, `*`). `types` supports `int|integer|float|double|bool|string|array|datetime[:format]` and a `?` prefix for nullable casts.
- `mutateJsonb(mixed $value): mixed` - recursively sorts associative-array keys by length then lexicographically (matches PostgreSQL JSONB ordering).
- `isMatching(string $key, array $path): bool` - public matcher for the JSONPath used above.

### `AnourValar\EloquentValidation\Exceptions\ValidationException`

Extends `\Illuminate\Validation\ValidationException`.

- `__construct($validator, $response = null, $errorBag = 'default', $prefix = null)` - `$validator` may be an Illuminate validator, an `array` of errors, or a scalar string (auto-wrapped under key `error`). `$prefix` may be a string or an iterable of segments; it is canonised and prepended to every error key.
- `addPrefix(array|string|null $prefix): static` - returns a new exception with the prefix prepended.
- `replaceKey(string $from, ?string $to): static` - rename an error key (including dotted paths); `$to = null|''` drops the segment.

```php
use AnourValar\EloquentValidation\Exceptions\ValidationException;

throw new ValidationException(['email' => 'Already taken']);
// later, when bubbling up through a service:
throw $e->addPrefix(['rows', 3]); // -> "rows.3.email"
```

### `AnourValar\EloquentValidation\Features\ManyToManyTrait`

Mixin for observers that maintain a pivot from an attribute on the parent model.

- `protected function onChangedM2M(\Illuminate\Database\Eloquent\Model $model, string $column, string $relation, ?string $key = null): void` - reads `$column` (dot-path) from the saved model and calls `$model->{$relation}()->sync($ids)`. If the source values are arrays, `$key` picks the id field; otherwise the array key or scalar value is used.

```php
use AnourValar\EloquentValidation\Features\ManyToManyTrait;

class PostObserver
{
    use ManyToManyTrait;

    public function saved(\App\Models\Post $post): void
    {
        $this->onChangedM2M($post, 'tag_ids', 'tags');
    }

    public function deleted(\App\Models\Post $post): void
    {
        $this->onChangedM2M($post, 'tag_ids', 'tags');
    }
}
```

### `AnourValar\EloquentValidation\Tests\ValidationTrait`

Mixin for PHPUnit test cases.

- `assertValidationSuccess(\Illuminate\Database\Eloquent\Model $model)` - calls `$model->validate()`; fails the test with the validator errors on exception. Returns `tap($model)`.
- `assertDeleteValidationSuccess(\Illuminate\Database\Eloquent\Model $model)` - same for `validateDelete()`.
- `assertValidationFailed($model, $keys, $message = true): void` - asserts `validate()` threw and that each `$keys` entry is present in the error bag. Also fails if any message contains `models/` (indicates a missing translation key). When `$message` is a string, it must match the first error for the key.
- `assertDeleteValidationFailed($model, $keys, $message = true): void` - same for delete.
- `assertCustomValidationFailed(callable $closure, $message = true): void` - asserts the closure throws `\Illuminate\Validation\ValidationException` and that no error message references `::`, `services/`, `entities/`, `models/`, `controllers/`, `policies/`.

```php
use AnourValar\EloquentValidation\Tests\ValidationTrait;

class UserPhoneTest extends \Tests\TestCase
{
    use ValidationTrait;

    public function test_phone_is_required(): void
    {
        $model = new \App\Models\UserPhone()->fill(['user_id' => 1]);
        $this->assertValidationFailed($model, 'phone_number');
    }
}
```

## Custom validation rules (registered globally)

Added by `EloquentValidationServiceProvider::boot()` via `Validator::extend`. Translations come from `eloquent-validation::validation.*` (publish with `php artisan vendor:publish`).

- `config:<config.key>` - value (scalar or array of scalars) must be a key in the given config array; arrays must have unique entries.
- `array_keys` - value is an array whose keys are constrained to the dotted child rules declared in the same validator (e.g. `data.title`, `data.body`).
- `array_keys_only:<key1>,<key2>,...` - value is an array whose keys are limited to the explicit list passed in.
- `array_keys_id` - every key of the array must be a positive integer (`>= 1`).
- `not_empty` (implicit) - trimmed value must not be `''`.

The provider also overrides the `:max` / `:min` replacers so the second rule parameter can be a translation key (e.g. `'max:255,validation.attributes.title_max'`).

## Artisan commands

- `php artisan make:model-validation {Name}` - extends Laravel's `make:model` using the package stub (skips when `--pivot`).
- `php artisan make:observer-validation {Name} --model=<Model>` - extends Laravel's `make:observer` using the package stub when `--model` is set.
- `php artisan model:validate {--dirty} {--ignore-configuration} {--except=Foo,Bar}` - iterates all models under `App\Models\` (or `App\`) that use `ModelTrait`, calls `validate()` on every row, and runs configuration sanity checks (duplicates in `unique`/`computed`/`unchangeable`, missing entries in `casts`, JSONPath shape, etc.). `--dirty` re-marks all attributes dirty; `--ignore-configuration` skips the structural checks.

## Usage examples

### Create with validation

```php
\App\Models\UserPhone::query()
    ->fields('user_id', 'phone_number')   // fillable for this statement
    ->fill(\Request::input())
    ->validate()                          // throws package ValidationException
    ->save();
```

### Update with validation and prefixed errors

```php
\App\Models\UserPhone::findOrFail($id)
    ->fields(['phone_number', 'comment'])
    ->fill(request()->input())
    ->validate('phones.0')                // errors keyed under "phones.0.phone_number" etc.
    ->save();
```

### Partial pre-validation (e.g. a wizard step)

```php
$model = new \App\Models\UserPhone()->fill(request()->input());
$model->beforeValidate(['phone_number']); // only validate this field
```

### Bulk write through CrudService

```php
$counters = app(\AnourValar\EloquentValidation\CrudService::class)->execute(
    new \App\Models\UserPhone(),
    request()->input('rows'),
    ['phone_number'],         // CREATE only when phone_number is set
    null,
    'rows'
);
// $counters => ['deleted'=>..., 'created'=>..., 'updated'=>..., 'affected'=>...]
```

### Custom rules in `saveRules`

```php
public function saveRules(): array
{
    return [
        'status'      => ['required', 'string', 'config:enums.user_phone.statuses'],
        'data'        => ['nullable', 'array', 'array_keys'],
            'data.title' => ['required', 'string', 'not_empty'],
            'data.tags'  => ['nullable', 'array', 'array_keys_id'],
        'options'     => ['nullable', 'array', 'array_keys_only:sms,push'],
    ];
}
```

### JSON-nested mutation

```php
protected array $nullable = ['data'];

protected $jsonNested = [
    'data' => [
        'jsonb'    => true,                    // sort keys (JSONB-style)
        'nullable' => ['$.title', '$.tags.*'], // '' or [] -> null at these paths
        'purges'   => ['$.tags.*'],            // drop nulls at these paths
        'types'    => ['$.count' => '?int'],   // cast; ? marks nullable
        'sorts'    => ['$.tags'],              // asort()
        'lists'    => ['$.tags'],              // array_values()
    ],
];
```

## Conventions / gotchas

- Models must `use \AnourValar\EloquentValidation\ModelTrait;` for any of the above behaviour to apply.
- Outside production, `setAttribute()` throws `\LogicException` for any attribute that is not in `$attributes`, casts, mutators, or relations. Always declare new columns in `$casts` or `$fillable`.
- `validate()` runs `unique` checks itself (soft-delete aware) - prefer the bare `unique` string rule; the trait canonises it (`unique` -> `unique:<connection>.<table>,<column>,<id>,<primaryKey>`) when the model has a primary key. Do NOT hand-write `unique:table` when using the trait.
- The configuration arrays must not overlap (`computed` vs `unchangeable`), every attribute referenced from `trim`/`nullable`/`computed`/`unchangeable`/`unique`/`jsonNested`/attribute names must exist in `$casts`. `php artisan model:validate` enforces this.
- `validate($prefix, ...)` and `ValidationException` accept either a string prefix or an iterable of segments (joined with `.`). When wrapping nested calls, prefer arrays: `->validate(['rows', $i])`.
- `validate()` ignores `$basic = true` and `$skip = true` when `app.debug` is on, so dev runs always perform full validation.
- Attribute names default to `trans('<snake-case namespace>/<snake model>.attributes')`; supply them via `lang/<locale>/models/<model>.php` (`'attributes' => [...]`) or `static::setAttributeNames([...])`. Cache is per locale - be careful with Octane.
- `scopeFields()` mutates `$fillable` on the instance; chain it before `fill()`/`create()`. After saving, the instance still carries the modified `$fillable`.
- `CrudService::execute` only authorizes when a Gate policy exists for the model class. `sync()` does not call Gate.
- `ManyToManyTrait::onChangedM2M` calls `$model->{$relation}()->sync([])` when the model no longer exists - register it on both `saved` and `deleted` to keep pivots consistent.
- Service provider `EloquentValidationServiceProvider` is auto-discovered (`extra.laravel.providers` in `composer.json`); no manual registration is needed. Translations: `php artisan vendor:publish --provider="AnourValar\EloquentValidation\Providers\EloquentValidationServiceProvider"`.
- Requires PHP `^8.3` and Laravel `^8.0`-`^13.0`.
