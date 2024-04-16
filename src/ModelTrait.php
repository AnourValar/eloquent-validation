<?php

namespace AnourValar\EloquentValidation;

use AnourValar\EloquentValidation\Exceptions\ValidationException;
use Illuminate\Support\Str;

trait ModelTrait
{
    /**
     * Attribute names
     *
     * @var array|null
     */
    private static $attributeNames;

    /**
     * Get the validation rules
     *
     * @return array
     */
    public function saveRules()
    {
        return [];
    }

    /**
     * "Save" after-validation
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function saveAfterValidation(\Illuminate\Validation\Validator $validator): void
    {

    }

    /**
     * "Delete" after-validation
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function deleteAfterValidation(\Illuminate\Validation\Validator $validator): void
    {

    }

    /**
     * @see \Illuminate\Database\Eloquent\Model
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    #[\Override]
    public function setAttribute($key, $value)
    {
        if (
            ! \App::isProduction()
            && ! strpos($key, '_count')
            && ! strpos($key, '_max')
            && ! strpos($key, '_min')
            && ! strpos($key, '_sum')
            && ! strpos($key, '_avg')
            && ! array_key_exists($key, $this->getAttributes())
            && ! $this->hasCast($key)
            && ! $this->hasSetMutator($key)
            && ! $this->hasAttributeSetMutator($key)
            && ! $this->relationLoaded($key)
            && ! $this->isRelation($key)
        ) {
            throw new \LogicException('Unexpected attribute "'.$key.'" was "set".');
        }

        if (isset($this->trim) && in_array($key, $this->trim)) {
            $value = $this->setTrim($value);
        }

        $value = $this->setJsonNested($value, $key);

        if (isset($this->nullable) && in_array($key, $this->nullable)) {
            $value = $this->setNull($value);
        }

        if ($this->isDateAttribute($key)) {
            if (! is_scalar($value) && ! is_null($value) && ! $value instanceof \DateTimeInterface) {
                $this->attributes[$key] = $value;
                return $this;
            }

            try {
                \Date::parse($value);
            } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                $this->attributes[$key] = $value;
                return $this;
            }
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * @see \Illuminate\Database\Eloquent\Concerns\HasAttributes::asJson()
     *
     * @param  mixed  $value
     * @return string
     */
    #[\Override]
    protected function asJson($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE); // for "json" columns
    }

    /**
     * Save validation
     *
     * @param mixed $prefix
     * @param array $additionalRules
     * @param array $additionalAttributeNames
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function validate($prefix = null, array $additionalRules = null, array $additionalAttributeNames = null)
    {
        if ($additionalAttributeNames) {
            $defaultAttributeNames = $this->getAttributeNames();
            $this->setAttributeNames(array_replace($defaultAttributeNames, $additionalAttributeNames));
        }

        // Rules
        $attributes = $this->getAttributesForValidation();

        $validator = \Validator::make($attributes, $this->canonizedRules());
        if ($additionalRules) {
            $validator->addRules($additionalRules);
        }
        $validator->setAttributeNames($this->getAttributeNames());

        $passes = $validator->passes();

        // Handles
        if ($passes) {
            $validator = \Validator::make($attributes, []);
            $validator->setAttributeNames($this->getAttributeNames());

            $validator->after(function ($validator) {
                if ($this->getComputed()) {
                    $this->handleUnchangeable($this->getComputed(), $validator, 'eloquent-validation::validation.computed');
                }

                if ($this->getUnchangeable() && $this->exists) {
                    $this->handleUnchangeable($this->getUnchangeable(), $validator);
                }

                if ($this->getUnique()) {
                    $this->handleUnique($this->getUnique(), $validator);
                }
            });

            $passes = $validator->passes();
        }

        // After validation
        if ($passes) {
            $validator = \Validator::make($attributes, []);
            $validator->setAttributeNames($this->getAttributeNames());

            $validator->after(function () {
                static $triggered;

                if (! $triggered) {
                    $triggered = true;

                    return call_user_func_array([$this, 'saveAfterValidation'], func_get_args());
                }
            });
            $passes = $validator->passes();

            if ($passes && $validator->getRules()) {
                $passes = $validator->passes();
            }

            /*if ($passes && method_exists($this, 'saveActionValidation')) {
                try {
                    $this->saveActionValidation();
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $passes = false;
                    $validator = $e->validator;
                }
            }*/
        }

        if ($additionalAttributeNames) {
            $this->setAttributeNames($defaultAttributeNames);
        }

        if (! $passes) {
            throw new ValidationException($validator, null, 'default', $prefix);
        }

        return $this;
    }

    /**
     * Delete validation
     *
     * @param mixed $prefix
     * @param array $additionalRules
     * @param array $additionalAttributeNames
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function validateDelete($prefix = null, array $additionalRules = null, array $additionalAttributeNames = null)
    {
        if ($additionalAttributeNames) {
            $defaultAttributeNames = $this->getAttributeNames();
            $this->setAttributeNames(array_replace($defaultAttributeNames, $additionalAttributeNames));
        }

        $passes = true;
        $attributes = $this->getAttributesForValidation();

        // Additional rules
        if ($additionalRules) {
            $validator = \Validator::make($attributes, $additionalRules);
            $validator->setAttributeNames($this->getAttributeNames());

            $passes = $validator->passes();
        }

        // After validation
        if ($passes) {
            $validator = \Validator::make($attributes, []);
            $validator->setAttributeNames($this->getAttributeNames());

            $validator->after([$this, 'deleteAfterValidation']);

            $passes = $validator->passes();
        }

        if ($additionalAttributeNames) {
            $this->setAttributeNames($defaultAttributeNames);
        }

        if (! $passes) {
            throw new ValidationException($validator, null, 'default', $prefix);
        }

        return $this;
    }

    /**
     * Exclude few ids from "soft delete" constraintment
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param mixed $ids
     * @return void
     */
    public function scopeWithoutTrashedOr(\Illuminate\Database\Eloquent\Builder $builder, mixed $ids)
    {
        if (! in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses($builder->getModel()))) {
            throw new \LogicException('Incorrect usage.');
        }

        $ids = array_filter((array) $ids);

        if (! $ids) {
            return;
        }

        $builder
            ->withTrashed()
            ->where(function ($query) use ($ids) {
                $query
                    ->whereNull($this->getDeletedAtColumn())
                    ->orWhereIn($this->getKeyName(), $ids);
            });
    }

    /**
     * Scope-access for "fillable"
     *
     * @return static
     */
    public function scopeFields()
    {
        $args = func_get_args();
        array_shift($args);

        if (! isset($args[0])) {
            $args[0] = [];
        }

        if (is_array($args[0])) {
            $args = $args[0];
        }

        $this->fillable = $args;

        return $this;
    }

    /**
     * Scope-helper to set "visible", "appends"
     *
     * @return void
     */
    public function scopePublishFields()
    {
        $this->visible = [];
        $this->appends = [];

        $this->scopeAddPublishFields(...func_get_args());
    }

    /**
     * Scope-helper to append "visible", "appends"
     *
     * @return void
     */
    public function scopeAddPublishFields()
    {
        $args = func_get_args();
        $query = array_shift($args);

        if (! isset($args[0])) {
            $args[0] = [];
        }

        if (is_array($args[0])) {
            $args = $args[0];
        }

        $this->visible = array_merge($this->visible, $args);

        foreach ($args as $arg) {
            if (! $this->hasCast($arg) && ($this->hasGetMutator($arg) || $this->hasAttributeGetMutator($arg))) {
                $this->appends[] = $arg;
            }
        }

        if ($this->exists && ($columns = $query->getQuery()->columns)) {
            uksort($this->attributes, function ($a, $b) use ($columns) {
                return array_search($a, $columns) <=> array_search($b, $columns);
            });
        }
    }

    /**
     * Determine if the user has the given abilities related to the entity.
     *
     * @param iterable|string|null $abilities
     * @param string|null $relation
     * @return \Illuminate\Support\HigherOrderTapProxy<\Illuminate\Database\Eloquent\Model>
     */
    public function authorize($abilities, string $relation = null)
    {
        if (isset($abilities)) {
            $arg = $this;
            if ($relation) {
                foreach (explode('.', $relation) as $item) {
                    $arg = $arg->$item;
                }
            }

            app(\Illuminate\Contracts\Auth\Access\Gate::class)->authorize($abilities, $arg);
        }

        return tap($this);
    }

    /**
     * @see \Illuminate\Database\Eloquent\Model::newInstance()
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     */
    #[\Override]
    public function newInstance($attributes = [], $exists = false)
    {
        $model = parent::newInstance([], $exists);

        $model->fillable = $this->fillable;
        $model->appends = $this->appends;
        $model->visible = $this->visible;
        $model->hidden = $this->hidden;

        if ($attributes) {
            $model->fill($attributes);
        }

        return $model;
    }

    /**
     * Set the attributes names. Careful with Octane.
     *
     * @param array|null $attributeNames
     * @return void
     */
    public static function setAttributeNames(?array $attributeNames): void
    {
        $value = static::$attributeNames;
        $value[\App::getLocale()] = $attributeNames;

        static::$attributeNames = &$value;
    }

    /**
     * Get the attributes names
     *
     * @return array
     */
    public function getAttributeNames(): array
    {
        $locale = \App::getLocale();

        if (! isset(static::$attributeNames[$locale])) {
            $value = static::$attributeNames;

            // model lang
            $value[$locale] = $this->getAttributeNamesFromModelLang();

            // handler lang
            if (method_exists($this, 'getAttributeNamesFromHandler')) {
                $value[$locale] = array_replace($value[$locale], $this->getAttributeNamesFromHandler());
            }

            static::$attributeNames = &$value;
        }

        return static::$attributeNames[$locale];
    }

    /**
     * Get calculated attributes
     *
     * @return array|null
     */
    public function getComputed()
    {
        return ($this->computed ?? null);
    }

    /**
     * Get unchangeable attributes
     *
     * @return array|null
     */
    public function getUnchangeable()
    {
        return ($this->unchangeable ?? null);
    }

    /**
     * Get unique attributes
     *
     * @return array|null
     */
    public function getUnique()
    {
        return ($this->unique ?? null);
    }

    /**
     * Get jsonNested attributes
     *
     * @return array|null
     */
    public function getJsonNested()
    {
        return ($this->jsonNested ?? null);
    }

    /**
     * Handle "unchangeable"
     *
     * @param array $unchangeable
     * @param \Illuminate\Validation\Validator $validator
     * @param string $translate
     */
    protected function handleUnchangeable(
        array $unchangeable,
        \Illuminate\Validation\Validator &$validator,
        $translate = 'eloquent-validation::validation.unchangeable'
    ) {
        if ($this->isUnguarded()) {
            return;
        }

        $newAttributes = $this->attributes;

        foreach ($unchangeable as $name) {
            if (array_key_exists($name, $newAttributes) && ! $this->originalIsEquivalent($name, $newAttributes[$name])) {
                $validator->errors()->add(
                    $name,
                    trans($translate, ['attribute' => $validator->getDisplayableAttribute($name)])
                );
            }
        }
    }

    /**
     * Handle "unique"
     *
     * @param array $uniques
     * @param \Illuminate\Validation\Validator $validator
     * @param string $translate
     */
    protected function handleUnique(
        array $uniques,
        \Illuminate\Validation\Validator &$validator,
        $translate = 'eloquent-validation::validation.unique'
    ) {
        $newAttributes = $this->attributes;

        foreach ($uniques as $unique) {
            $builder = new $this();

            if (! $this->isDirty($unique) && $this->exists) {
                continue;
            }

            foreach ($unique as $field) {
                if (isset($newAttributes[$field])) {
                    $builder = $builder->where($field, '=', $newAttributes[$field]);
                } else {
                    $builder = $builder->whereNull($field);
                }
            }

            if ($this->primaryKey && isset($newAttributes[$this->primaryKey])) {
                $builder = $builder->where($this->primaryKey, '!=', $newAttributes[$this->primaryKey]);
            }

            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses($builder->getModel()))) {
                $builder = $builder->withTrashed();
            }

            if ($builder->first()) {
                $params = ['attributes' => []];
                $key = null;
                foreach ($unique as $field) {
                    $params['attributes'][] = $validator->getDisplayableAttribute($field);

                    if (! isset($key)) {
                        $key = $field;
                    }
                }
                $params['attributes'] = implode(', ', $params['attributes']);

                $validator->errors()->add($key, trans($translate, $params));
            }
        }
    }

    /**
     * @return array
     */
    protected function canonizedRules()
    {
        $rules = $this->saveRules();

        // "unique" validation
        $hasPrimary = ($this->primaryKey && isset($this->attributes[$this->primaryKey]));

        foreach ($rules as $field => &$fieldRules) {
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            if (! is_iterable($fieldRules)) {
                continue;
            }

            foreach ($fieldRules as $key => $rule) {
                if (! is_string($rule)) {
                    continue;
                }

                $rule = explode(':', $rule, 2);

                if (mb_strtolower($rule[0]) == 'unique') {
                    if (isset($rule[1])) {
                        $rule[1] = explode(',', $rule[1]);
                    } else {
                        $rule[1] = [];
                    }

                    if (count($rule[1]) == 0) {
                        $rule[1][] = $this->getConnectionName() ?
                            $this->getConnectionName().'.'.$this->getTable() :
                            $this->getTable();
                    }

                    if (count($rule[1]) == 1) {
                        $rule[1][] = $field;
                    }

                    if (count($rule[1]) == 2 && $hasPrimary && $rule[1][1] != $this->primaryKey) {
                        $rule[1][] = $this->attributes[$this->primaryKey];
                        $rule[1][] = $this->primaryKey;
                    }

                    if (
                        (! isset($this->attributes[$rule[1][1]]) or is_scalar($this->attributes[$rule[1][1]]))
                        && $this->originalIsEquivalent($rule[1][1])
                    ) {
                        unset($fieldRules[$key]);
                        continue;
                    }

                    $rule[1] = implode(',', $rule[1]);
                    $rule = implode(':', $rule);

                    $fieldRules[$key] = $rule;
                }
            }
        }
        unset($fieldRules);

        return $rules;
    }

    /**
     * @see \Illuminate\Database\Eloquent\Model::asDateTime()
     *
     * @param  string  $value
     * @return bool
     */
    #[\Override]
    protected function asDateTime($value)
    {
        if (is_array($value)) {
            return $value;
        }

        return parent::asDateTime($value);
    }

    /**
     * @return array
     */
    protected function getAttributesForValidation()
    {
        $attributes = $this->attributes;

        foreach (array_keys($attributes) as $name) {
            if (! is_scalar($attributes[$name]) && ! is_null($attributes[$name])) {
                continue;
            }

            try {
                $value = $this->getAttribute($name);
            } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                $value = $attributes[$name];
            }

            if (
                is_array($value)
                || (is_string($attributes[$name]) && is_integer($value) && $attributes[$name] === (string) $value)
                || (is_string($attributes[$name]) && is_float($value) && $attributes[$name] === (string) $value)
                || ((is_float($attributes[$name]) || is_integer($attributes[$name])) && is_string($value) && (string) $attributes[$name] === $value)
            ) {
                $attributes[$name] = $value;
            }
        }

        return $attributes;
    }

    /**
     * @return array
     */
    public function extractAttributesListFromConfiguration(): array
    {
        $attributes = [
            'trim' => ($this->trim ?? null),
            'nullable' => ($this->nullable ?? null),
            'hidden' => ($this->hidden ?? null),
            'attributes' => array_keys($this->attributes),
            'computed' => ($this->computed ?? null),
            'unchangeable' => ($this->unchangeable ?? null),
            'unique' => ($this->unique ?? null),
            'jsonNested' => ($this->jsonNested ?? null),
            'attribute_names' => array_keys($this->getAttributeNames()),
        ];

        foreach (array_keys($this->saveRules()) as $attribute) {
            if (strpos($attribute, '.') === false) {
                $attributes['save_rules'][] = $attribute;
            }
        }

        return $attributes;
    }

    /**
     * Merge new jsonNested with existing jsonNested on the model.
     *
     * @param array $jsonNested
     * @return self
     */
    public function mergeJsonNested(array $jsonNested): self
    {
        $this->jsonNested = array_merge($this->jsonNested, $jsonNested);

        return $this;
    }

    /**
     * Get attribute names from model's lang
     *
     * @return array
     */
    protected function getAttributeNamesFromModelLang(): array
    {
        $path = explode('\\', get_class($this));

        $file = Str::snake(array_pop($path));

        array_shift($path);
        $path = array_map([Str::class, 'snake'], $path);
        $dir = implode('/', $path);

        if (! $dir) {
            $dir = 'models';
        }

        $result = trans($dir.'/'.$file.'.attributes');
        if (! is_array($result)) {
            $result = [];
        }

        return $result;
    }

    /**
     * Merge mutator
     *
     * @param string $attribute
     * @param mixed $value
     * @return array
     */
    protected function mutateMerge(string $attribute, $value): array
    {
        $this->$attribute = array_merge((array) $this->$attribute, (array) $value);
        return [$attribute => $this->getAttributes()[$attribute]];
    }

    /**
     * Diff mutator
     *
     * @param string $attribute
     * @param mixed $value
     * @return array
     */
    protected function mutateDiff(string $attribute, $value): array
    {
        $this->$attribute = array_diff((array) $this->$attribute, (array) $value);
        return [$attribute => $this->getAttributes()[$attribute]];
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function setTrim($value)
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            foreach ($value as &$item) {
                $item = $this->setTrim($item);
            }
            unset($item);
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function setNull($value)
    {
        if ((is_string($value) && trim($value) === '') || $value === []) {
            return null;
        }

        return $value;
    }

    /**
     * Handle "jsonNested"
     *
     * @param mixed $value
     * @param string $key
     * @return mixed
     */
    private function setJsonNested($value, $key)
    {
        $rules = ($this->getJsonNested()[$key] ?? null);
        if (! $rules) {
            return $value;
        }

        if (
            ! empty($rules['nullable'])
            || ! empty($rules['purges'])
            || ! empty($rules['types'])
            || ! empty($rules['sorts'])
            || ! empty($rules['lists'])
        ) {
            if (isset($this->nullable) && in_array($key, $this->nullable)) {
                $value = $this->setNull($value);
            }

            if (! isset($value)) {
                return $value;
            }

            $value = ['$' => $value];
            $value = (new ValidatorHelper())->mutateArray(
                $value,
                ($rules['nullable'] ?? null),
                ($rules['purges'] ?? null),
                ($rules['types'] ?? null),
                ($rules['sorts'] ?? null),
                ($rules['lists'] ?? null)
            );
            $value = $value['$'];
        }

        if (! empty($rules['jsonb'])) {
            $value = (new ValidatorHelper())->mutateJsonb($value);
        }

        return $value;
    }
}
