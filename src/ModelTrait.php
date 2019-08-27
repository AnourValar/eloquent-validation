<?php

namespace AnourValar\EloquentValidation;

trait ModelTrait
{
    /**
     * @var array
     */
    private static $fillableDynamic = [];
    
    /**
     * Preliminary Validation Rules for All Attributes
     * 
     * @var mixed
     */
    protected $defaultRules = 'scalar';
    
    /**
     * Columns names
     *
     * @var array
     */
    protected $names = null;
    
    /**
     * @see \Validator::after()
     * 
     * @param \Illuminate\Validation\Validator $validator
     */
    public function afterValidation(\Illuminate\Validation\Validator $validator)
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
        if (isset($this->trim) && in_array($key, $this->trim) && is_scalar($value) && mb_strlen($value)) {
            $value = trim($value);
        }
        
        if (isset($this->nullable) && in_array($key, $this->nullable) && $value === '') {
            $value = null;
        }
        
        if (isset($value) && in_array($key, $this->getDates())) {
            if (! is_object($value)) {
                if (!is_numeric($value)) {
                    $value = strtotime($value);
                }
                
                $value = date($this->getDateFormat(), $value);
            }
        }
        
        return parent::setAttribute($key, $value);
    }
    
    /**
     * Validation
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $prefix
     * @param array $additionalRules
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function scopeValidate(\Illuminate\Database\Eloquent\Builder $query, $prefix = null, array $additionalRules = [])
    {
        $this->fillNames();
        
        // default rules
        $validator = \Validator::make($this->attributes, array_fill_keys(array_keys($this->attributes), $this->defaultRules));
        if (isset($this->names)) {
            $validator->setAttributeNames($this->names);
        }
        $passes = $validator->passes();
        
        // additional rules
        if ($passes && $additionalRules) {
            $validator = \Validator::make($this->attributes, $additionalRules);
            if (isset($this->names)) {
                $validator->setAttributeNames($this->names);
            }
            
            $passes = $validator->passes();
        }
        
        // rules
        if ($passes) {
            $validator = \Validator::make($this->attributes, $this->canonizeRules());
            if (isset($this->names)) {
                $validator->setAttributeNames($this->names);
            }
            
            $passes = $validator->passes();
        }
        
        // additional rules
        if ($passes) {
            $validator = \Validator::make($this->attributes, []);
            if (isset($this->names)) {
                $validator->setAttributeNames($this->names);
            }
            
            $validator->after([$this, 'afterValidation']);
            
            $passes = $validator->passes();
        }
        
        if (!$passes) {
            if ($prefix) {
                $prefix = $this->canonizePrefix($prefix);
                
                $errors = [];
                foreach ($validator->errors()->messages() as $key => $error) {
                    $errors[$prefix.$key] = $error;
                }
                
                $validator = $errors;
            }
            
            throw new \AnourValar\EloquentValidation\Exceptions\ValidationException($validator);
        }
        
        return $this;
    }
    
    /**
     * Temporary (for one query) list of "fillables" columns
     * 
     * @return \Illuminate\Database\Eloquent\Model
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
        
        static::$fillableDynamic = &$args;
        
        return $this;
    }
    
    /**
     * @see \Illuminate\Database\Eloquent\Model::getFillable()
     *
     * @return array
     */
    public function getFillable()
    {
        if (!static::$fillableDynamic) {
            return parent::getFillable();
        }
        
        return static::$fillableDynamic;
    }
    
    /**
     * @see \Illuminate\Database\Eloquent\Model::save()
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = [])
    {
        if (!empty($this->calculated)) {
            $this->handleUnchangeable($this->calculated, $this->getAttributes(), 'eloquent-validation::validation.calculated');
        }
        
        if (!empty($this->unchangeable) && $this->exists) {
            $this->handleUnchangeable($this->unchangeable, $this->getAttributes());
        }
        
        if (!empty($this->unique)) {
            $this->handleUnique($this->unique, $this->getAttributes());
        }
        
        $list = [];
        static::$fillableDynamic = &$list;
        
        return parent::save($options);
    }
    
    /**
     * @param array $unchangeable
     * @param array $newAttributes
     * @param string $translate
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     */
    protected function handleUnchangeable(
        array $unchangeable,
        array $newAttributes,
        $translate = 'eloquent-validation::validation.unchangeable'
    ) {
        if ($this->isUnguarded()) {
            return;
        }
        
        $this->fillNames();
        
        foreach ($unchangeable as $name) {
            if (array_key_exists($name, $newAttributes) && !$this->originalIsEquivalent($name, $newAttributes[$name])) {
                $fieldName = $name;
                if (isset($this->names[$fieldName])) {
                    $fieldName = $this->names[$fieldName];
                }
                
                throw new \AnourValar\EloquentValidation\Exceptions\ValidationException(
                    [$name => trans($translate, ['attribute' => $fieldName])]
                );
            }
        }
    }
    
    /**
     * @param array $uniques
     * @param array $newAttributes
     * @param string $translate
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     */
    protected function handleUnique(array $uniques, array $newAttributes, $translate = 'eloquent-validation::validation.unique')
    {
        $this->fillNames();
        
        foreach ($uniques as $unique) {
            $builder = new $this;
            
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
            
            if ($builder->first()) {
                $params = ['attributes' => []];
                foreach ($unique as $field) {
                    if (isset($this->names[$field])) {
                        $params['attributes'][] = $this->names[$field];
                    } else {
                        $params['attributes'][] = $field;
                    }
                }
                $params['attributes'] = implode(', ', $params['attributes']);
                
                throw new \AnourValar\EloquentValidation\Exceptions\ValidationException(
                    [$field => trans($translate, $params)]
                );
            }
        }
    }
    
    /**
     * @return void
     */
    protected function fillNames()
    {
        if ($this->names !== null) {
            return;
        }
        $this->names = [];
        
        if (\App::getLocale()) {
            $file = get_class($this);
            $file = explode('\\', $file);
            $file = array_pop($file);
            $file = snake_case($file);
            
            $names = trans('model/'.$file.'.fields');
            if (is_array($names)) {
                $this->names = $names;
            }
        }
    }
    
    /**
     * @return array
     */
    private function canonizeRules()
    {
        $rules = $this->rules ?? [];
        
        // "unique" validation
        $hasPrimary = ($this->primaryKey && isset($this->attributes[$this->primaryKey]));
        
        foreach ($rules as $field => &$fieldRules) {
            if (! is_array($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }
            
            foreach ($fieldRules as $key => $rule) {
                $rule = explode(':', $rule, 2);
                
                if (mb_strtolower($rule[0]) == 'unique') {
                    if (isset($rule[1])) {
                        $rule[1] = explode(',', $rule[1]);
                    } else {
                        $rule[1] = [];
                    }
                    
                    if (count($rule[1]) == 0) {
                        $rule[1][] = $this->getConnectionName() ? $this->getConnectionName().'.'.$this->getTable() : $this->getTable();
                    }
                    
                    if (count($rule[1]) == 1) {
                        $rule[1][] = $field;
                    }
                    
                    if (count($rule[1]) == 2 && $hasPrimary) {
                        $rule[1][] = $this->attributes[$this->primaryKey];
                        $rule[1][] = $this->primaryKey;
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
     * @param mixed $prefix
     * @return string
     */
    private function canonizePrefix($prefix)
    {
        if (is_iterable($prefix)) {
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
        }
        
        return $prefix;
    }
}
