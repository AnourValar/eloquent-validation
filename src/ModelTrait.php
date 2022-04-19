<?php

namespace AnourValar\EloquentValidation;

use \AnourValar\EloquentValidation\Exceptions\ValidationException;
use Illuminate\Support\Str;

trait ModelTrait
{
    /**
     * Raw validation rules for all attributes
     *
     * @var mixed
     */
    private $rawRules = 'scalar';

    /**
     * Attribute names
     *
     * @var array|null
     */
    protected static $attributeNames;

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
    public function saveAfterValidation(\Illuminate\Validation\Validator $validator)
    {

    }

    /**
     * "Delete" after-validation
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function deleteAfterValidation(\Illuminate\Validation\Validator $validator)
    {

    }

    /**
     * @see \Illuminate\Database\Eloquent\Model
     *
     * @param string $key
     * @param string $value
     */
    public function setAttribute($key, $value)
    {
        if (isset($this->trim) && in_array($key, $this->trim)) {
            $value = $this->setTrim($value);
        }

        if (isset($this->nullable) && in_array($key, $this->nullable)) {
            $value = $this->setNull($value);
        }

        if ($this->isDateAttribute($key) && !is_scalar($value) && !is_null($value) && !$value instanceof \DateTimeInterface) {
            $this->attributes[$key] = $value;
            return $this;
        }

        return parent::setAttribute($key, $value);
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

        // Raw rules
        $validator = \Validator::make($this->attributes, array_fill_keys(array_keys($this->attributes), $this->rawRules));
        $validator->setAttributeNames($this->getAttributeNames());
        $passes = $validator->passes();

        // Rules
        if ($passes) {
            $attributes = $this->getAttributesForValidation();

            $validator = \Validator::make($attributes, $this->canonizedRules());
            if ($additionalRules) {
                $validator->addRules($additionalRules);
            }
            $validator->setAttributeNames($this->getAttributeNames());

            $passes = $validator->passes();
        }

        // Handles
        if ($passes) {
            $validator = \Validator::make($attributes, []);
            $validator->setAttributeNames($this->getAttributeNames());

            $validator->after(function ($validator)
            {
                if ($this->getCalculated()) {
                    $this->handleUnchangeable($this->getCalculated(), $validator, 'eloquent-validation::validation.calculated');
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

            $validator->after(function ()
            {
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
     * Scope-access for "fillable"
     *
     * @return static
     */
    public function scopeFields()
    {
        $args = func_get_args();
        array_shift($args);

        if (!isset($args[0])) {
            $args[0] = [];
        }

        if (is_array($args[0])) {
            $args = $args[0];
        }

        $this->fillable = $args;

        return $this;
    }

    /**
     * Scope-access for "visible" & "appends"
     *
     * @return void
     */
    public function scopePublishFields()
    {
        $args = func_get_args();
        array_shift($args);

        if (!isset($args[0])) {
            $args[0] = [];
        }

        if (is_array($args[0])) {
            $args = $args[0];
        }

        $this->visible = $args;

        $this->appends = [];
        foreach ($args as $arg) {
            if (! $this->hasCast($arg)) {
                $this->appends[] = $arg;
            }
        }
    }

    /**
     * @see \Illuminate\Database\Eloquent\Model::newInstance()
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     */
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
     * Set the attributes names
     *
     * @param array $attributeNames
     * @return void
     */
    public static function setAttributeNames(?array $attributeNames)
    {
        static::$attributeNames = &$attributeNames;
    }

    /**
     * Get the attributes names
     *
     * @return array
     */
    public function getAttributeNames()
    {
        if (is_null(static::$attributeNames)) {
            $attributeNames = [];

            if (\App::getLocale()) {
                $path = explode('\\', get_class($this));

                $file = Str::snake(array_pop($path));

                array_shift($path);
                $path = array_map([Str::class, 'snake'], $path);
                $dir = implode('/', $path);

                if (!$dir) {
                    $dir = 'models';
                }

                $attributeNames = trans($dir.'/'.$file.'.attributes');
                if (! is_array($attributeNames)) {
                    $attributeNames = [];
                }
            }

            static::$attributeNames = &$attributeNames;
        }

        return static::$attributeNames;
    }

    /**
     * Get calculated attributes
     *
     * @return array|null
     */
    public function getCalculated()
    {
        return ( $this->calculated ?? null );
    }

    /**
     * Get unchangeable attributes
     *
     * @return array|null
     */
    public function getUnchangeable()
    {
        return ( $this->unchangeable ?? null );
    }

    /**
     * Get unique attributes
     *
     * @return array|null
     */
    public function getUnique()
    {
        return ( $this->unique ?? null );
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
            if (array_key_exists($name, $newAttributes) && !$this->originalIsEquivalent($name, $newAttributes[$name])) {
                $validator->errors()->add(
                    $name,
                    trans($translate, ['attribute' => $this->getAttributeDisplayName($name, $validator)])
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
            $builder = new $this;

            if (!$this->isDirty($unique) && $this->exists) {
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
                    $params['attributes'][] = $this->getAttributeDisplayName($field, $validator);

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
     * Json Cast with convertation: '' => null
     *
     * @param mixed $data
     * @return string|null
     */
    protected function jsonCastEmptyStringsToNull($data): ?string
    {
        $data = $this->emptyStringsToNull($data);
        if (is_null($data)) {
            return null;
        }

        return $this->asJson($data);
    }

    /**
     * @param string $attribute
     * @param \Illuminate\Validation\Validator $validator
     * @return string
     */
    private function getAttributeDisplayName(string $attribute, \Illuminate\Validation\Validator $validator): string
    {
        if (isset($validator->customAttributes[$attribute])) {
            return $validator->customAttributes[$attribute];
        }

        return (trans('validation.attributes')[$attribute] ?? $attribute);
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    private function emptyStringsToNull($data)
    {
        if (is_null($data)) {
            return null;
        }

        if (is_string($data) && trim($data) === '') {
            return null;
        }

        if (is_scalar($data)) {
            return $data;
        }

        if (! is_array($data)) {
            return null;
        }

        foreach ($data as &$item) {
            $item = $this->emptyStringsToNull($item);
        }
        unset($item);

        return $data;
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

                    if (! $this->isDirty($rule[1][1])) {
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
     * @return array
     */
    protected function getAttributesForValidation()
    {
        $attributes = $this->attributes;

        foreach (array_keys($attributes) as $name) {
            $value = $this->getAttribute($name);

            if (is_array($value)) {
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
            'trim' => (array) ($this->trim ?? null),
            'nullable' => (array) ($this->nullable ?? null),
            'hidden' => (array) ($this->hidden ?? null),
            'attributes' => array_keys($this->attributes),
            'calculated' => (array) ($this->calculated ?? null),
            'unchangeable' => (array) ($this->unchangeable ?? null),
            'unique' => (array) ($this->unique ?? null),
        ];

        foreach (array_keys($this->saveRules()) as $attribute) {
            if (strpos($attribute, '.') === false) {
                $attributes['save_rules'][] = $attribute;
            }
        }

        return $attributes;
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
        if ((is_string($value) && trim($value) === '') || (is_array($value) && !count($value))) {
            return null;
        }

        return $value;
    }
}
